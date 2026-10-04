<?php
/**
 * Career module: post type, ACF fields, settings tab and listing data.
 *
 * Field values are read with get_post_meta() (ACF stores plain values under the
 * field name), so data stays readable even if ACF is temporarily inactive.
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

class NBY_IT_Options_Career extends NBY_IT_Options_Module {

	const POST_TYPE = 'career';

	/** Hidden, always-numeric deadline used for filtering and sorting (Ymd, 99991231 = none). */
	const SORT_META = '_nby_deadline_sort';
	const NO_DEADLINE = '99991231';

	/**
	 * @var NBY_IT_Options_Settings
	 */
	private $settings;

	/**
	 * @param NBY_IT_Options_Settings $settings Settings service.
	 */
	public function __construct( NBY_IT_Options_Settings $settings ) {
		$this->settings = $settings;
	}

	public function id() {
		return 'career';
	}

	public function register() {
		$this->register_settings_tab();

		add_action( 'acf/init', array( $this, 'register_acf_fields' ) );
		add_action( 'added_post_meta', array( $this, 'sync_deadline_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'sync_deadline_meta' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'sync_deadline_on_delete' ), 10, 3 );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'ensure_deadline_meta' ), 20 );

		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'admin_columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'admin_column_content' ), 10, 2 );

		// Late priority: runs after Elementor Pro's theme builder, whose generic
		// "All Singular" templates would otherwise take over career pages.
		add_filter( 'template_include', array( $this, 'single_template' ), 99 );
		add_action( 'wp', array( $this, 'hide_id_links' ) );
	}

	/* ---------------------------------------------------------------------
	 * Single career page
	 * ------------------------------------------------------------------- */

	/**
	 * Whether the plugin's two-column single layout is active.
	 *
	 * @return bool
	 */
	public function single_layout_enabled() {
		return (bool) apply_filters( 'nby_it_options_single_layout', (int) $this->setting( 'single_layout' ) );
	}

	/**
	 * Use the plugin's single template for careers, unless the theme ships its own
	 * single-career.php or an Elementor Pro single template specifically targets
	 * Careers (generic "All Singular" templates are ignored for careers).
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function single_template( $template ) {
		if ( ! is_singular( self::POST_TYPE ) || ! $this->single_layout_enabled() ) {
			return $template;
		}
		if ( 'single-' . self::POST_TYPE . '.php' === wp_basename( (string) $template ) || $this->has_elementor_career_template() ) {
			return $template;
		}
		$override = locate_template( 'nby-it-options/single-career.php' );
		return $override ? $override : NBY_IT_OPTIONS_PATH . 'templates/single-career.php';
	}

	/**
	 * Whether a published Elementor Pro template has a display condition for careers.
	 *
	 * @return bool
	 */
	private function has_elementor_career_template() {
		if ( ! defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			return false;
		}
		$ids = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_elementor_conditions',
						'value'   => 'include/singular/' . self::POST_TYPE,
						'compare' => 'LIKE',
					),
				),
			)
		);
		return (bool) apply_filters( 'nby_it_options_has_elementor_career_template', ! empty( $ids ) );
	}

	/**
	 * Remove head links/headers that reveal the numeric post ID (?p=123 shortlink,
	 * /wp-json/wp/v2/career/123) on career pages.
	 */
	public function hide_id_links() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}
		remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
	}

	/**
	 * Find a published career by slug.
	 *
	 * @param string $slug Career slug (post_name).
	 * @return int Career ID or 0.
	 */
	public static function find_by_slug( $slug ) {
		$slug = sanitize_title( (string) $slug );
		if ( '' === $slug ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				'name'             => $slug,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Public identifier of a career (its slug).
	 *
	 * @param int $career_id Career ID.
	 * @return string
	 */
	public static function slug( $career_id ) {
		return (string) get_post_field( 'post_name', $career_id );
	}

	/**
	 * Other open careers for the single page sidebar.
	 *
	 * @param int $exclude_id Current career ID.
	 * @return array[] Career data.
	 */
	public function other_jobs( $exclude_id ) {
		$limit = max( 0, (int) $this->setting( 'single_other_jobs' ) );
		if ( ! $limit ) {
			return array();
		}
		$query = $this->query(
			array(
				'per_page'     => $limit,
				'orderby'      => $this->setting( 'orderby' ),
				'order'        => $this->setting( 'order' ),
				'show_expired' => false,
				'paged'        => 1,
				'exclude'      => array( (int) $exclude_id ),
			)
		);
		$jobs = array();
		foreach ( $query->posts as $post ) {
			$jobs[] = $this->get_data( $post );
		}
		return $jobs;
	}

	public function post_types() {
		return array(
			self::POST_TYPE => array(
				'labels'          => NBY_IT_Options_Post_Types::labels( __( 'Career', 'nby-it-options' ), __( 'Careers', 'nby-it-options' ) ),
				'public'          => true,
				'show_ui'         => true,
				'show_in_menu'    => NBY_IT_Options_Settings::PAGE_SLUG,
				'show_in_rest'    => true,
				'has_archive'     => false,
				'rewrite'         => array(
					'slug'       => 'career',
					'with_front' => false,
				),
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
				'menu_icon'       => 'dashicons-businessman',
				// Editors and administrators manage careers (not authors/contributors).
				'capability_type' => 'page',
				'map_meta_cap'    => true,
			),
		);
	}

	public function activate() {
		$this->backfill_deadline_meta();
	}

	/* ---------------------------------------------------------------------
	 * Field definitions
	 * ------------------------------------------------------------------- */

	/**
	 * @return array<string, string>
	 */
	public static function employment_types() {
		return apply_filters(
			'nby_it_options_employment_types',
			array(
				'full-time'  => __( 'Full Time', 'nby-it-options' ),
				'part-time'  => __( 'Part Time', 'nby-it-options' ),
				'contract'   => __( 'Contract', 'nby-it-options' ),
				'internship' => __( 'Internship', 'nby-it-options' ),
				'freelance'  => __( 'Freelance', 'nby-it-options' ),
			)
		);
	}

	/**
	 * @return array<string, string>
	 */
	public static function experience_levels() {
		return apply_filters(
			'nby_it_options_experience_levels',
			array(
				'fresher'   => __( 'Fresher', 'nby-it-options' ),
				'junior'    => __( 'Junior', 'nby-it-options' ),
				'mid-level' => __( 'Mid-Level', 'nby-it-options' ),
				'senior'    => __( 'Senior', 'nby-it-options' ),
				'expert'    => __( 'Expert', 'nby-it-options' ),
			)
		);
	}

	public function register_acf_fields() {
		if ( ! function_exists( 'acf_add_local_field_group' ) ) {
			return;
		}

		acf_add_local_field_group(
			array(
				'key'      => 'group_nby_career_details',
				'title'    => __( 'Career Details', 'nby-it-options' ),
				'fields'   => array(
					array(
						'key'           => 'field_nby_employment_type',
						'label'         => __( 'Employment Type', 'nby-it-options' ),
						'name'          => 'employment_type',
						'type'          => 'select',
						'choices'       => self::employment_types(),
						'default_value' => 'full-time',
						'return_format' => 'value',
						'required'      => 1,
						'wrapper'       => array( 'width' => '50' ),
					),
					array(
						'key'           => 'field_nby_experience_level',
						'label'         => __( 'Experience Level', 'nby-it-options' ),
						'name'          => 'experience_level',
						'type'          => 'select',
						'choices'       => self::experience_levels(),
						'allow_null'    => 1,
						'return_format' => 'value',
						'wrapper'       => array( 'width' => '50' ),
					),
					array(
						'key'         => 'field_nby_job_location',
						'label'       => __( 'Job Location', 'nby-it-options' ),
						'name'        => 'job_location',
						'type'        => 'text',
						'placeholder' => __( 'USA, California', 'nby-it-options' ),
						'wrapper'     => array( 'width' => '50' ),
					),
					array(
						'key'         => 'field_nby_salary',
						'label'       => __( 'Salary', 'nby-it-options' ),
						'name'        => 'salary',
						'type'        => 'text',
						'placeholder' => __( '$45k–$60k/year', 'nby-it-options' ),
						'wrapper'     => array( 'width' => '50' ),
					),
					array(
						'key'            => 'field_nby_application_deadline',
						'label'          => __( 'Application Deadline', 'nby-it-options' ),
						'name'           => 'application_deadline',
						'type'           => 'date_picker',
						'instructions'   => __( 'Applications are accepted until the end of this day. Leave empty for no deadline.', 'nby-it-options' ),
						'display_format' => 'F j, Y',
						'return_format'  => 'Ymd',
						'first_day'      => 1,
						'wrapper'        => array( 'width' => '50' ),
					),
				),
				'location' => array(
					array(
						array(
							'param'    => 'post_type',
							'operator' => '==',
							'value'    => self::POST_TYPE,
						),
					),
				),
				'position' => 'acf_after_title',
				'style'    => 'default',
				'active'   => true,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

	private function register_settings_tab() {
		$this->settings->add_tab(
			'careers',
			__( 'Careers', 'nby-it-options' ),
			array(
				'listing'     => array(
					'title'  => __( 'Career Listing', 'nby-it-options' ),
					'fields' => array(
						'enable_listing' => array(
							'label'   => __( 'Enable Career Listing', 'nby-it-options' ),
							'type'    => 'checkbox',
							'default' => 1,
						),
						'careers_page'   => array(
							'label'       => __( 'Careers Page', 'nby-it-options' ),
							'type'        => 'page',
							'default'     => 0,
							'description' => __( 'The page containing [nby_careers]. Used for "View Career Opportunities" links.', 'nby-it-options' ),
						),
						'per_page'       => array(
							'label'   => __( 'Jobs Per Page', 'nby-it-options' ),
							'type'    => 'number',
							'default' => 10,
							'min'     => 1,
							'max'     => 100,
						),
						'orderby'        => array(
							'label'   => __( 'Order By', 'nby-it-options' ),
							'type'    => 'select',
							'default' => 'date',
							'options' => array(
								'date'       => __( 'Date', 'nby-it-options' ),
								'title'      => __( 'Title', 'nby-it-options' ),
								'deadline'   => __( 'Application Deadline', 'nby-it-options' ),
								'menu_order' => __( 'Menu Order', 'nby-it-options' ),
							),
						),
						'order'          => array(
							'label'   => __( 'Order', 'nby-it-options' ),
							'type'    => 'select',
							'default' => 'DESC',
							'options' => array(
								'DESC' => __( 'Descending', 'nby-it-options' ),
								'ASC'  => __( 'Ascending', 'nby-it-options' ),
							),
						),
						'show_expired'   => array(
							'label'          => __( 'Show Expired Jobs', 'nby-it-options' ),
							'type'           => 'checkbox',
							'default'        => 0,
							'checkbox_label' => __( 'Yes — show expired jobs with an "Applications Closed" button', 'nby-it-options' ),
						),
					),
				),
				'single'      => array(
					'title'  => __( 'Job Single Page', 'nby-it-options' ),
					'fields' => array(
						'single_layout'     => array(
							'label'          => __( 'Two-Column Layout', 'nby-it-options' ),
							'type'           => 'checkbox',
							'default'        => 1,
							'checkbox_label' => __( 'Show other open jobs on the left and the job details with "Apply Now" on the right', 'nby-it-options' ),
							'description'    => __( 'Keep this on. If turned off, job pages fall back to your theme/Elementor single template (e.g. a blog post template), which does not show job details or the Apply button. Only turn it off if you build an Elementor single template for Careers, and place [nby_career_single] in it.', 'nby-it-options' ),
						),
						'single_other_jobs' => array(
							'label'   => __( 'Other Jobs to Show', 'nby-it-options' ),
							'type'    => 'number',
							'default' => 8,
							'min'     => 0,
							'max'     => 50,
						),
					),
				),
				'application' => array(
					'title'  => __( 'Application Settings', 'nby-it-options' ),
					'fields' => array(
						'application_page'  => array(
							'label'       => __( 'Application Page', 'nby-it-options' ),
							'type'        => 'page',
							'default'     => 0,
							'description' => __( 'The page containing [nby_career_application]. Apply buttons link here with ?job=job-slug.', 'nby-it-options' ),
						),
						'recruitment_email' => array(
							'label'       => __( 'Recruitment Email', 'nby-it-options' ),
							'type'        => 'email',
							'default'     => '',
							'placeholder' => get_option( 'admin_email' ),
							'description' => __( 'Receives new application notifications. Defaults to the site admin email.', 'nby-it-options' ),
						),
						'send_confirmation' => array(
							'label'   => __( 'Send Applicant Confirmation', 'nby-it-options' ),
							'type'    => 'checkbox',
							'default' => 1,
						),
						'max_resume_mb'     => array(
							'label'       => __( 'Maximum Resume Size', 'nby-it-options' ),
							'type'        => 'number',
							'default'     => 5,
							'min'         => 1,
							'max'         => 50,
							'suffix'      => 'MB',
							/* translators: %s: server upload limit. */
							'description' => sprintf( __( 'Your server allows uploads up to %s; the lower value applies.', 'nby-it-options' ), size_format( wp_max_upload_size() ) ),
						),
						'allowed_types'     => array(
							'label'   => __( 'Allowed Resume Types', 'nby-it-options' ),
							'type'    => 'multicheck',
							'default' => array( 'pdf', 'doc', 'docx' ),
							'options' => array(
								'pdf'  => 'PDF',
								'doc'  => 'DOC',
								'docx' => 'DOCX',
							),
						),
					),
				),
			),
			10
		);
	}

	/**
	 * Read a careers setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public function setting( $key ) {
		return $this->settings->get( 'careers', $key );
	}

	/**
	 * Effective maximum resume size in bytes.
	 *
	 * @return int
	 */
	public function max_resume_bytes() {
		$bytes = max( 1, (int) $this->setting( 'max_resume_mb' ) ) * MB_IN_BYTES;
		return (int) min( $bytes, wp_max_upload_size() );
	}

	/**
	 * Allowed resume extensions.
	 *
	 * @return string[]
	 */
	public function allowed_resume_types() {
		$types = array_values( array_intersect( (array) $this->setting( 'allowed_types' ), array_keys( NBY_IT_Options_File_Upload::supported_types() ) ) );
		return $types ? $types : array( 'pdf', 'doc', 'docx' );
	}

	/* ---------------------------------------------------------------------
	 * Deadline handling
	 * ------------------------------------------------------------------- */

	/**
	 * Normalize a stored deadline to Ymd, or '' if empty/invalid.
	 *
	 * @param mixed $value Raw value (ACF stores Ymd; Y-m-d also accepted).
	 * @return string
	 */
	public static function normalize_deadline( $value ) {
		$value = preg_replace( '/[^0-9]/', '', (string) $value );
		if ( 8 !== strlen( $value ) ) {
			return '';
		}
		$date = DateTime::createFromFormat( '!Ymd', $value, wp_timezone() );
		return ( $date && $date->format( 'Ymd' ) === $value ) ? $value : '';
	}

	/**
	 * Deadline (Ymd) of a career, or ''.
	 *
	 * @param int $career_id Career id.
	 * @return string
	 */
	public static function deadline( $career_id ) {
		return self::normalize_deadline( get_post_meta( $career_id, 'application_deadline', true ) );
	}

	/**
	 * Today's date in the site timezone (Ymd).
	 *
	 * @return string
	 */
	public static function today() {
		return wp_date( 'Ymd' );
	}

	/**
	 * Whether the application deadline has passed (the deadline day itself is still open).
	 *
	 * @param int $career_id Career id.
	 * @return bool
	 */
	public static function is_expired( $career_id ) {
		$deadline = self::deadline( $career_id );
		return '' !== $deadline && $deadline < self::today();
	}

	/**
	 * Human readable deadline, e.g. "September 18, 2024".
	 *
	 * @param string $ymd Deadline in Ymd.
	 * @return string
	 */
	public static function format_deadline( $ymd ) {
		if ( '' === $ymd ) {
			return '';
		}
		$date = DateTime::createFromFormat( '!Ymd', $ymd, wp_timezone() );
		if ( ! $date ) {
			return '';
		}
		return wp_date( apply_filters( 'nby_it_options_deadline_format', 'F j, Y' ), $date->getTimestamp() + 12 * HOUR_IN_SECONDS );
	}

	/**
	 * Keep the hidden sort meta in sync whenever the deadline is written (ACF, REST, import...).
	 *
	 * @param int    $meta_id    Meta id.
	 * @param int    $object_id  Post id.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function sync_deadline_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( 'application_deadline' !== $meta_key || self::POST_TYPE !== get_post_type( $object_id ) ) {
			return;
		}
		$deadline = self::normalize_deadline( $meta_value );
		update_post_meta( $object_id, self::SORT_META, '' !== $deadline ? $deadline : self::NO_DEADLINE );
	}

	/**
	 * @param array  $meta_ids  Meta ids.
	 * @param int    $object_id Post id.
	 * @param string $meta_key  Meta key.
	 */
	public function sync_deadline_on_delete( $meta_ids, $object_id, $meta_key ) {
		if ( 'application_deadline' === $meta_key && self::POST_TYPE === get_post_type( $object_id ) ) {
			update_post_meta( $object_id, self::SORT_META, self::NO_DEADLINE );
		}
	}

	/**
	 * Ensure every career has the sort meta (e.g. created without a deadline).
	 *
	 * @param int $post_id Post id.
	 */
	public function ensure_deadline_meta( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$deadline = self::deadline( $post_id );
		update_post_meta( $post_id, self::SORT_META, '' !== $deadline ? $deadline : self::NO_DEADLINE );
	}

	/**
	 * Populate the sort meta for existing careers (activation / upgrade).
	 */
	public function backfill_deadline_meta() {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			$this->ensure_deadline_meta( $id );
		}
	}

	/* ---------------------------------------------------------------------
	 * URLs and data
	 * ------------------------------------------------------------------- */

	/**
	 * ID of the page that renders the application form.
	 *
	 * @return int
	 */
	public function application_page_id() {
		$page_id = absint( $this->setting( 'application_page' ) );
		if ( $page_id && 'publish' === get_post_status( $page_id ) ) {
			return $page_id;
		}

		// Fallback: first published page using the shortcode.
		static $found = null;
		if ( null === $found ) {
			global $wpdb;
			$found = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
					'%' . $wpdb->esc_like( '[nby_career_application' ) . '%'
				)
			);
		}
		return $found;
	}

	/**
	 * Apply URL for a career, e.g. /career-application/?job=senior-interior-designer.
	 * The numeric ID is never exposed publicly; the slug identifies the job.
	 *
	 * @param int $career_id Career id.
	 * @return string
	 */
	public function apply_url( $career_id ) {
		$page_id = $this->application_page_id();
		$slug    = self::slug( $career_id );
		$base    = $page_id ? get_permalink( $page_id ) : get_permalink( $career_id );
		$url     = ( $page_id && '' !== $slug ) ? add_query_arg( 'job', rawurlencode( $slug ), $base ) : $base;
		return (string) apply_filters( 'nby_it_options_apply_url', $url, $career_id );
	}

	/**
	 * URL of the careers listing page.
	 *
	 * @return string
	 */
	public function careers_url() {
		$page_id = absint( $this->setting( 'careers_page' ) );
		return $page_id && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : home_url( '/' );
	}

	/**
	 * Whether a career exists, is published and is a career.
	 *
	 * @param int $career_id Career id.
	 * @return bool
	 */
	public static function is_open_post( $career_id ) {
		$post = $career_id ? get_post( $career_id ) : null;
		return $post && self::POST_TYPE === $post->post_type && 'publish' === $post->post_status && empty( $post->post_password );
	}

	/**
	 * Display data for a career.
	 *
	 * @param int|WP_Post $post Career.
	 * @return array
	 */
	public function get_data( $post ) {
		$post     = get_post( $post );
		$id       = $post->ID;
		$types    = self::employment_types();
		$levels   = self::experience_levels();
		$type     = (string) get_post_meta( $id, 'employment_type', true );
		$level    = (string) get_post_meta( $id, 'experience_level', true );
		$deadline = self::deadline( $id );

		return array(
			'id'               => $id,
			'slug'             => $post->post_name,
			'title'            => get_the_title( $post ),
			'permalink'        => get_permalink( $post ),
			'employment_type'  => isset( $types[ $type ] ) ? $types[ $type ] : $type,
			'experience_level' => isset( $levels[ $level ] ) ? $levels[ $level ] : $level,
			'location'         => (string) get_post_meta( $id, 'job_location', true ),
			'salary'           => (string) get_post_meta( $id, 'salary', true ),
			'deadline'         => $deadline,
			'deadline_display' => self::format_deadline( $deadline ),
			'is_expired'       => self::is_expired( $id ),
			'apply_url'        => $this->apply_url( $id ),
		);
	}

	/**
	 * Query careers for the listing.
	 *
	 * @param array $args { per_page, orderby, order, show_expired, paged, exclude? }.
	 * @return WP_Query
	 */
	public function query( array $args ) {
		$query_args = array(
			'post_type'           => self::POST_TYPE,
			'post_status'         => 'publish',
			'has_password'        => false,
			'posts_per_page'      => max( 1, (int) $args['per_page'] ),
			'paged'               => max( 1, (int) $args['paged'] ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => false,
		);

		if ( ! empty( $args['exclude'] ) ) {
			$query_args['post__not_in'] = array_map( 'absint', (array) $args['exclude'] ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
		}

		$order = 'ASC' === strtoupper( (string) $args['order'] ) ? 'ASC' : 'DESC';

		if ( empty( $args['show_expired'] ) ) {
			// Posts without the sort meta (created outside WP hooks) are treated as open.
			$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array(
					'key'     => self::SORT_META,
					'value'   => self::today(),
					'compare' => '>=',
					'type'    => 'NUMERIC',
				),
				array(
					'key'     => self::SORT_META,
					'compare' => 'NOT EXISTS',
				),
			);
		}

		switch ( $args['orderby'] ) {
			case 'title':
				$query_args['orderby'] = array( 'title' => $order );
				break;
			case 'menu_order':
				$query_args['orderby'] = array(
					'menu_order' => $order,
					'date'       => 'DESC',
				);
				break;
			case 'deadline':
				$query_args['meta_key'] = self::SORT_META; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = array(
					'meta_value_num' => $order,
					'date'           => 'DESC',
				);
				break;
			default:
				$query_args['orderby'] = array( 'date' => $order );
		}

		return new WP_Query( apply_filters( 'nby_it_options_careers_query_args', $query_args, $args ) );
	}

	/* ---------------------------------------------------------------------
	 * Admin list columns
	 * ------------------------------------------------------------------- */

	/**
	 * @param array $columns Columns.
	 * @return array
	 */
	public function admin_columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : __( 'Date', 'nby-it-options' );
		unset( $columns['date'] );
		$columns['nby_type']         = __( 'Type', 'nby-it-options' );
		$columns['nby_location']     = __( 'Location', 'nby-it-options' );
		$columns['nby_deadline']     = __( 'Deadline', 'nby-it-options' );
		$columns['nby_applications'] = __( 'Applications', 'nby-it-options' );
		$columns['date']             = $date;
		return $columns;
	}

	/**
	 * @param string $column  Column key.
	 * @param int    $post_id Post id.
	 */
	public function admin_column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'nby_type':
				$types = self::employment_types();
				$type  = (string) get_post_meta( $post_id, 'employment_type', true );
				echo esc_html( isset( $types[ $type ] ) ? $types[ $type ] : $type );
				break;

			case 'nby_location':
				echo esc_html( (string) get_post_meta( $post_id, 'job_location', true ) );
				break;

			case 'nby_deadline':
				$deadline = self::deadline( $post_id );
				if ( '' === $deadline ) {
					echo '&mdash;';
					break;
				}
				echo esc_html( self::format_deadline( $deadline ) );
				if ( self::is_expired( $post_id ) ) {
					echo ' <span class="nby-badge nby-badge--expired">' . esc_html__( 'Expired', 'nby-it-options' ) . '</span>';
				}
				break;

			case 'nby_applications':
				if ( ! current_user_can( 'view_career_applications' ) ) {
					echo '&mdash;';
					break;
				}
				$count = NBY_IT_Options_Application::count_for_career( $post_id );
				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url(
						add_query_arg(
							array(
								'post_type'  => NBY_IT_Options_Application::POST_TYPE,
								'nby_career' => $post_id,
							),
							admin_url( 'edit.php' )
						)
					),
					esc_html( number_format_i18n( $count ) )
				);
				break;
		}
	}
}
