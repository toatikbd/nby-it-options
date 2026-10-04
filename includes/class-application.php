<?php
/**
 * Career application records: post type, statuses, admin screens, resume access.
 *
 * Applications are private `career_application` posts. All data lives in post
 * meta (independent of ACF). The post type is not public and not exposed via
 * REST; access is controlled by dedicated capabilities.
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

class NBY_IT_Options_Application extends NBY_IT_Options_Module {

	const POST_TYPE = 'career_application';

	const META_PREFIX = '_nby_app_';

	const DOWNLOAD_ACTION = 'nby_download_resume';

	/**
	 * @var NBY_IT_Options_Career
	 */
	private $career;

	/**
	 * @param NBY_IT_Options_Career $career Career module.
	 */
	public function __construct( NBY_IT_Options_Career $career ) {
		$this->career = $career;
	}

	public function id() {
		return 'application';
	}

	/**
	 * Stored fields: key => label.
	 *
	 * @return array<string, string>
	 */
	public static function fields() {
		return array(
			'full_name'     => __( 'Applicant', 'nby-it-options' ),
			'email'         => __( 'Email', 'nby-it-options' ),
			'phone'         => __( 'Phone', 'nby-it-options' ),
			'location'      => __( 'Location', 'nby-it-options' ),
			'linkedin_url'  => __( 'LinkedIn', 'nby-it-options' ),
			'portfolio_url' => __( 'Portfolio', 'nby-it-options' ),
			'cover_letter'  => __( 'Cover Letter', 'nby-it-options' ),
			'resume_file'   => __( 'Resume', 'nby-it-options' ),
			'resume_mime'   => __( 'Resume Type', 'nby-it-options' ),
			'career_id'     => __( 'Career ID', 'nby-it-options' ),
			'career_title'  => __( 'Position', 'nby-it-options' ),
			'status'        => __( 'Status', 'nby-it-options' ),
		);
	}

	/**
	 * Application statuses: key => label.
	 *
	 * @return array<string, string>
	 */
	public static function statuses() {
		return apply_filters(
			'nby_it_options_application_statuses',
			array(
				'new'         => __( 'New', 'nby-it-options' ),
				'reviewing'   => __( 'Reviewing', 'nby-it-options' ),
				'shortlisted' => __( 'Shortlisted', 'nby-it-options' ),
				'interview'   => __( 'Interview', 'nby-it-options' ),
				'rejected'    => __( 'Rejected', 'nby-it-options' ),
				'hired'       => __( 'Hired', 'nby-it-options' ),
			)
		);
	}

	/**
	 * Primitive capabilities granted to managing roles.
	 *
	 * @return string[]
	 */
	public static function capabilities() {
		return array(
			'manage_career_applications',
			'view_career_applications',
			'edit_career_applications',
			'edit_others_career_applications',
			'edit_published_career_applications',
			'edit_private_career_applications',
			'read_private_career_applications',
			'delete_career_applications',
			'delete_others_career_applications',
			'delete_published_career_applications',
			'delete_private_career_applications',
			'publish_career_applications',
		);
	}

	public function post_types() {
		return array(
			self::POST_TYPE => array(
				'labels'              => array_merge(
					NBY_IT_Options_Post_Types::labels( __( 'Application', 'nby-it-options' ), __( 'Applications', 'nby-it-options' ) ),
					array(
						'all_items' => __( 'Applications', 'nby-it-options' ),
						'edit_item' => __( 'Application Details', 'nby-it-options' ),
					)
				),
				'public'              => false,
				'publicly_queryable'  => false,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => NBY_IT_Options_Settings::PAGE_SLUG,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => false,
				'show_in_rest'        => false,
				'query_var'           => false,
				'rewrite'             => false,
				'has_archive'         => false,
				'can_export'          => false,
				'delete_with_user'    => false,
				'supports'            => false,
				'map_meta_cap'        => true,
				'capability_type'     => array( 'career_application', 'career_applications' ),
				'capabilities'        => array(
					'create_posts' => 'do_not_allow',
					'read'         => 'view_career_applications',
				),
			),
		);
	}

	public function register() {
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . self::POST_TYPE . '_sortable_columns', array( $this, 'sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'filters' ) );
		add_action( 'pre_get_posts', array( $this, 'admin_query' ) );
		add_filter( 'views_edit-' . self::POST_TYPE, array( $this, 'status_views' ) );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . self::POST_TYPE, array( $this, 'bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . self::POST_TYPE, array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_notice' ) );

		add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_status' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'menu_bubble' ), 99 );

		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'download_resume' ) );
		add_action( 'admin_post_nopriv_' . self::DOWNLOAD_ACTION, array( $this, 'download_requires_login' ) );

		add_action( 'before_delete_post', array( $this, 'delete_resume_with_post' ) );

		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );

		add_action( 'nby_it_options_settings_before_general', array( $this, 'render_overview' ) );
		add_action( 'nby_it_options_settings_before_careers', array( $this, 'render_storage_notice' ) );
	}

	/**
	 * Warn when resumes could not be stored outside the web root.
	 */
	public function render_storage_notice() {
		if ( 'uploads' !== NBY_IT_Options_File_Upload::location() ) {
			return;
		}
		echo '<div class="notice notice-warning inline"><p>'
			. esc_html__( 'Resumes are stored in a protected folder inside wp-content/uploads because the folder above your WordPress installation is not writable. Apache and IIS block direct access automatically; on nginx, add a rule denying access to /wp-content/uploads/nby-it-options-private/ (see README).', 'nby-it-options' )
			. '</p></div>';
	}

	public function activate() {
		self::add_capabilities();
		NBY_IT_Options_File_Upload::directory();
	}

	/**
	 * Grant application capabilities to administrators and editors.
	 */
	public static function add_capabilities() {
		$roles = apply_filters( 'nby_it_options_application_roles', array( 'administrator', 'editor' ) );
		foreach ( $roles as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::capabilities() as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Data access
	 * ------------------------------------------------------------------- */

	/**
	 * Read a stored application field.
	 *
	 * @param int    $application_id Application id.
	 * @param string $key            Field key (see fields()).
	 * @return string
	 */
	public static function get( $application_id, $key ) {
		return (string) get_post_meta( $application_id, self::META_PREFIX . $key, true );
	}

	/**
	 * Current status key (defaults to "new").
	 *
	 * @param int $application_id Application id.
	 * @return string
	 */
	public static function status( $application_id ) {
		$status = self::get( $application_id, 'status' );
		return array_key_exists( $status, self::statuses() ) ? $status : 'new';
	}

	/**
	 * Status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public static function status_label( $status ) {
		$statuses = self::statuses();
		return isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status;
	}

	/**
	 * Create an application. Data must already be validated and sanitized.
	 *
	 * @param array $data Field values keyed as in fields().
	 * @return int|WP_Error Application id.
	 */
	public static function create( array $data ) {
		$meta = array();
		foreach ( array_keys( self::fields() ) as $key ) {
			if ( isset( $data[ $key ] ) ) {
				$meta[ self::META_PREFIX . $key ] = $data[ $key ];
			}
		}
		$meta[ self::META_PREFIX . 'status' ] = 'new';

		$title = sprintf(
			/* translators: 1: applicant name, 2: position. */
			__( '%1$s — %2$s', 'nby-it-options' ),
			$data['full_name'],
			$data['career_title']
		);

		$id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => '',
				'post_author'  => 0,
				'meta_input'   => $meta,
			),
			true
		);

		if ( ! is_wp_error( $id ) ) {
			do_action( 'nby_it_options_application_created', $id, $data );
		}

		return $id;
	}

	/**
	 * Whether this email already applied for this career (trashed applications excluded).
	 *
	 * @param string $email     Normalized (lowercase) email.
	 * @param int    $career_id Career id.
	 * @return bool
	 */
	public static function exists_for( $email, $career_id ) {
		$ids = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => array( 'publish', 'private', 'draft', 'pending', 'future' ),
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_query'       => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => self::META_PREFIX . 'career_id',
						'value' => (string) absint( $career_id ),
					),
					array(
						'key'   => self::META_PREFIX . 'email',
						'value' => $email,
					),
				),
			)
		);
		return ! empty( $ids );
	}

	/**
	 * Number of applications for a career.
	 *
	 * @param int $career_id Career id.
	 * @return int
	 */
	public static function count_for_career( $career_id ) {
		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => self::META_PREFIX . 'career_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => (string) absint( $career_id ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return (int) $query->found_posts;
	}

	/**
	 * Counts per status (published applications).
	 *
	 * @return array<string, int>
	 */
	public static function status_counts() {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS status, COUNT(*) AS total
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
				WHERE p.post_type = %s AND p.post_status = 'publish'
				GROUP BY pm.meta_value",
				self::META_PREFIX . 'status',
				self::POST_TYPE
			)
		);

		$counts = array_fill_keys( array_keys( self::statuses() ), 0 );
		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row->status ] ) ) {
				$counts[ $row->status ] = (int) $row->total;
			}
		}
		return $counts;
	}

	/**
	 * Secure admin URL for downloading a resume.
	 *
	 * @param int $application_id Application id.
	 * @return string
	 */
	public static function download_url( $application_id ) {
		return add_query_arg(
			array(
				'action'         => self::DOWNLOAD_ACTION,
				'application_id' => absint( $application_id ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Admin URL of the application details screen.
	 *
	 * @param int $application_id Application id.
	 * @return string
	 */
	public static function admin_url( $application_id ) {
		return add_query_arg(
			array(
				'post'   => absint( $application_id ),
				'action' => 'edit',
			),
			admin_url( 'post.php' )
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin list screen
	 * ------------------------------------------------------------------- */

	/**
	 * @return array
	 */
	public function columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'title'        => __( 'Applicant', 'nby-it-options' ),
			'nby_position' => __( 'Position', 'nby-it-options' ),
			'nby_email'    => __( 'Email', 'nby-it-options' ),
			'nby_phone'    => __( 'Phone', 'nby-it-options' ),
			'nby_status'   => __( 'Status', 'nby-it-options' ),
			'date'         => __( 'Applied Date', 'nby-it-options' ),
		);
	}

	/**
	 * @param string $column  Column.
	 * @param int    $post_id Application id.
	 */
	public function column_content( $column, $post_id ) {
		switch ( $column ) {
			case 'nby_position':
				$career_id = absint( self::get( $post_id, 'career_id' ) );
				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url(
						add_query_arg(
							array(
								'post_type'  => self::POST_TYPE,
								'nby_career' => $career_id,
							),
							admin_url( 'edit.php' )
						)
					),
					esc_html( self::get( $post_id, 'career_title' ) )
				);
				break;

			case 'nby_email':
				$email = self::get( $post_id, 'email' );
				printf( '<a href="%1$s">%2$s</a>', esc_url( 'mailto:' . $email ), esc_html( $email ) );
				break;

			case 'nby_phone':
				echo esc_html( self::get( $post_id, 'phone' ) );
				break;

			case 'nby_status':
				$status = self::status( $post_id );
				printf(
					'<span class="nby-status nby-status--%1$s">%2$s</span>',
					esc_attr( $status ),
					esc_html( self::status_label( $status ) )
				);
				break;
		}
	}

	/**
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function sortable_columns( $columns ) {
		$columns['title']        = 'title';
		$columns['nby_position'] = 'nby_position';
		$columns['nby_email']    = 'nby_email';
		$columns['nby_status']   = 'nby_status';
		$columns['date']         = 'date';
		return $columns;
	}

	/**
	 * Career and status dropdowns (WordPress adds the month filter).
	 *
	 * @param string $post_type Current post type.
	 */
	public function filters( $post_type ) {
		if ( self::POST_TYPE !== $post_type ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$current_career = isset( $_GET['nby_career'] ) ? absint( $_GET['nby_career'] ) : 0;
		$current_status = isset( $_GET['nby_status'] ) ? sanitize_key( wp_unslash( $_GET['nby_status'] ) ) : '';
		// phpcs:enable

		$careers = get_posts(
			array(
				'post_type'      => NBY_IT_Options_Career::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		echo '<label class="screen-reader-text" for="nby-filter-career">' . esc_html__( 'Filter by career', 'nby-it-options' ) . '</label>';
		echo '<select name="nby_career" id="nby-filter-career"><option value="0">' . esc_html__( 'All careers', 'nby-it-options' ) . '</option>';
		foreach ( $careers as $career ) {
			printf(
				'<option value="%1$d" %2$s>%3$s</option>',
				(int) $career->ID,
				selected( $current_career, $career->ID, false ),
				esc_html( get_the_title( $career ) )
			);
		}
		echo '</select>';

		echo '<label class="screen-reader-text" for="nby-filter-status">' . esc_html__( 'Filter by status', 'nby-it-options' ) . '</label>';
		echo '<select name="nby_status" id="nby-filter-status"><option value="">' . esc_html__( 'All statuses', 'nby-it-options' ) . '</option>';
		foreach ( self::statuses() as $key => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $key ),
				selected( $current_status, $key, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Apply list filters and meta sorting.
	 *
	 * @param WP_Query $query Query.
	 */
	public function admin_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || self::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		$meta_query = array();

		if ( ! empty( $_GET['nby_career'] ) ) {
			$meta_query[] = array(
				'key'   => self::META_PREFIX . 'career_id',
				'value' => (string) absint( $_GET['nby_career'] ),
			);
		}

		$status = isset( $_GET['nby_status'] ) ? sanitize_key( wp_unslash( $_GET['nby_status'] ) ) : '';
		if ( $status && array_key_exists( $status, self::statuses() ) ) {
			$meta_query[] = array(
				'key'   => self::META_PREFIX . 'status',
				'value' => $status,
			);
		}
		// phpcs:enable

		if ( $meta_query ) {
			$query->set( 'meta_query', $meta_query );
		}

		$sort_keys = array(
			'nby_position' => 'career_title',
			'nby_email'    => 'email',
			'nby_status'   => 'status',
		);
		$orderby   = $query->get( 'orderby' );
		if ( is_string( $orderby ) && isset( $sort_keys[ $orderby ] ) ) {
			$query->set( 'meta_key', self::META_PREFIX . $sort_keys[ $orderby ] );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Status links above the list (All | New | Reviewing | ...).
	 *
	 * @param array $views Views.
	 * @return array
	 */
	public function status_views( $views ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current = isset( $_GET['nby_status'] ) ? sanitize_key( wp_unslash( $_GET['nby_status'] ) ) : '';
		$counts  = self::status_counts();
		$base    = add_query_arg( 'post_type', self::POST_TYPE, admin_url( 'edit.php' ) );

		$new_views = array();
		foreach ( $views as $key => $view ) {
			// Drop the "Published" view; it duplicates "All".
			if ( 'publish' === $key ) {
				continue;
			}
			if ( 'all' === $key && $current ) {
				$view = str_replace( array( ' class="current"', 'aria-current="page"' ), '', $view );
			}
			$new_views[ $key ] = $view;
			if ( 'all' === $key ) {
				foreach ( self::statuses() as $status => $label ) {
					$new_views[ 'nby_' . $status ] = sprintf(
						'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
						esc_url( add_query_arg( 'nby_status', $status, $base ) ),
						$current === $status ? ' class="current" aria-current="page"' : '',
						esc_html( $label ),
						esc_html( number_format_i18n( $counts[ $status ] ) )
					);
				}
			}
		}
		return $new_views;
	}

	/**
	 * @param array   $actions Row actions.
	 * @param WP_Post $post    Post.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( self::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		unset( $actions['inline hide-if-no-js'], $actions['view'] );
		if ( isset( $actions['edit'] ) ) {
			$actions['edit'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::admin_url( $post->ID ) ), esc_html__( 'View', 'nby-it-options' ) );
		}
		if ( self::get( $post->ID, 'resume_file' ) && current_user_can( 'view_career_applications' ) ) {
			$actions['nby_resume'] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( self::download_url( $post->ID ) ), esc_html__( 'Download Resume', 'nby-it-options' ) );
		}
		return $actions;
	}

	/**
	 * @param array $actions Bulk actions.
	 * @return array
	 */
	public function bulk_actions( $actions ) {
		unset( $actions['edit'] );
		foreach ( self::statuses() as $key => $label ) {
			/* translators: %s: status label. */
			$actions[ 'nby_set_' . $key ] = sprintf( __( 'Set status: %s', 'nby-it-options' ), $label );
		}
		return $actions;
	}

	/**
	 * Bulk status change. Nonce is verified by WordPress (bulk-posts) before this runs.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action.
	 * @param int[]  $ids      Post ids.
	 * @return string
	 */
	public function handle_bulk_actions( $redirect, $action, $ids ) {
		if ( 0 !== strpos( $action, 'nby_set_' ) ) {
			return $redirect;
		}
		$status = substr( $action, 8 );
		if ( ! array_key_exists( $status, self::statuses() ) ) {
			return $redirect;
		}
		$changed = 0;
		foreach ( array_map( 'absint', (array) $ids ) as $id ) {
			if ( self::POST_TYPE === get_post_type( $id ) && current_user_can( 'edit_post', $id ) ) {
				self::set_status( $id, $status );
				++$changed;
			}
		}
		return add_query_arg( 'nby_status_changed', $changed, $redirect );
	}

	public function bulk_notice() {
		$screen = get_current_screen();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $screen || 'edit-' . self::POST_TYPE !== $screen->id || ! isset( $_GET['nby_status_changed'] ) ) {
			return;
		}
		$count = absint( $_GET['nby_status_changed'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			/* translators: %d: number of applications. */
			esc_html( sprintf( _n( 'Status updated for %d application.', 'Status updated for %d applications.', $count, 'nby-it-options' ), $count ) )
		);
	}

	/**
	 * Add a "new applications" count bubble to the Applications menu item.
	 */
	public function menu_bubble() {
		global $submenu;
		if ( empty( $submenu[ NBY_IT_Options_Settings::PAGE_SLUG ] ) || ! current_user_can( 'view_career_applications' ) ) {
			return;
		}
		$counts = self::status_counts();
		$new    = $counts['new'] ?? 0;
		if ( ! $new ) {
			return;
		}
		foreach ( $submenu[ NBY_IT_Options_Settings::PAGE_SLUG ] as $index => $item ) {
			if ( 'edit.php?post_type=' . self::POST_TYPE === $item[2] ) {
				$submenu[ NBY_IT_Options_Settings::PAGE_SLUG ][ $index ][0] .= sprintf( // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					' <span class="awaiting-mod"><span class="pending-count">%s</span></span>',
					esc_html( number_format_i18n( $new ) )
				);
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Details screen
	 * ------------------------------------------------------------------- */

	/**
	 * @param WP_Post $post Application.
	 */
	public function meta_boxes( $post ) {
		remove_meta_box( 'slugdiv', self::POST_TYPE, 'normal' );
		add_meta_box( 'nby_application_details', __( 'Application Details', 'nby-it-options' ), array( $this, 'render_details' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'nby_application_status', __( 'Application Status', 'nby-it-options' ), array( $this, 'render_status_box' ), self::POST_TYPE, 'side', 'high' );
	}

	/**
	 * @param WP_Post $post Application.
	 */
	public function render_details( $post ) {
		$id        = $post->ID;
		$career_id = absint( self::get( $id, 'career_id' ) );
		$career    = $career_id ? get_post( $career_id ) : null;
		$linkedin  = self::get( $id, 'linkedin_url' );
		$portfolio = self::get( $id, 'portfolio_url' );
		$email     = self::get( $id, 'email' );
		$phone     = self::get( $id, 'phone' );
		$resume    = self::get( $id, 'resume_file' );
		?>
		<div class="nby-app">
			<dl class="nby-app__grid">
				<div class="nby-app__item">
					<dt><?php esc_html_e( 'Applicant', 'nby-it-options' ); ?></dt>
					<dd class="nby-app__name"><?php echo esc_html( self::get( $id, 'full_name' ) ); ?></dd>
				</div>
				<div class="nby-app__item">
					<dt><?php esc_html_e( 'Position', 'nby-it-options' ); ?></dt>
					<dd>
						<?php if ( $career && current_user_can( 'edit_post', $career_id ) ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $career_id ) ); ?>"><?php echo esc_html( self::get( $id, 'career_title' ) ); ?></a>
						<?php else : ?>
							<?php echo esc_html( self::get( $id, 'career_title' ) ); ?>
						<?php endif; ?>
					</dd>
				</div>
				<div class="nby-app__item">
					<dt><?php esc_html_e( 'Email', 'nby-it-options' ); ?></dt>
					<dd><a href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo esc_html( $email ); ?></a></dd>
				</div>
				<div class="nby-app__item">
					<dt><?php esc_html_e( 'Phone', 'nby-it-options' ); ?></dt>
					<dd><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></dd>
				</div>
				<div class="nby-app__item">
					<dt><?php esc_html_e( 'Location', 'nby-it-options' ); ?></dt>
					<dd><?php echo esc_html( self::get( $id, 'location' ) ); ?></dd>
				</div>
				<div class="nby-app__item">
					<dt><?php esc_html_e( 'Applied', 'nby-it-options' ); ?></dt>
					<dd><?php echo esc_html( get_the_date( 'F j, Y \a\t g:i a', $post ) ); ?></dd>
				</div>
				<div class="nby-app__item">
					<dt><?php esc_html_e( 'LinkedIn', 'nby-it-options' ); ?></dt>
					<dd>
						<?php if ( $linkedin ) : ?>
							<a href="<?php echo esc_url( $linkedin ); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo esc_html( $linkedin ); ?></a>
						<?php else : ?>
							&mdash;
						<?php endif; ?>
					</dd>
				</div>
				<div class="nby-app__item">
					<dt><?php esc_html_e( 'Portfolio', 'nby-it-options' ); ?></dt>
					<dd>
						<?php if ( $portfolio ) : ?>
							<a href="<?php echo esc_url( $portfolio ); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo esc_html( $portfolio ); ?></a>
						<?php else : ?>
							&mdash;
						<?php endif; ?>
					</dd>
				</div>
			</dl>

			<h3 class="nby-app__heading"><?php esc_html_e( 'Cover Letter', 'nby-it-options' ); ?></h3>
			<div class="nby-app__letter"><?php echo nl2br( esc_html( self::get( $id, 'cover_letter' ) ) ); ?></div>

			<h3 class="nby-app__heading"><?php esc_html_e( 'Resume', 'nby-it-options' ); ?></h3>
			<?php if ( $resume && NBY_IT_Options_File_Upload::path( $resume ) ) : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( self::download_url( $id ) ); ?>">
						<span class="dashicons dashicons-download" aria-hidden="true"></span>
						<?php esc_html_e( 'Download Resume', 'nby-it-options' ); ?>
					</a>
					<span class="description"><?php echo esc_html( strtoupper( pathinfo( $resume, PATHINFO_EXTENSION ) ) ); ?></span>
				</p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'The resume file is not available.', 'nby-it-options' ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * @param WP_Post $post Application.
	 */
	public function render_status_box( $post ) {
		$status = self::status( $post->ID );
		wp_nonce_field( 'nby_application_status_' . $post->ID, 'nby_status_nonce' );
		?>
		<p>
			<label for="nby-application-status" class="nby-status-label"><?php esc_html_e( 'Status', 'nby-it-options' ); ?></label>
			<select name="nby_application_status" id="nby-application-status" class="nby-status-select nby-status--<?php echo esc_attr( $status ); ?>">
				<?php foreach ( self::statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p class="description"><?php esc_html_e( 'Click "Update" to save the status. Applicants are not notified of status changes.', 'nby-it-options' ); ?></p>
		<?php
	}

	/**
	 * Save status from the details screen.
	 *
	 * @param int     $post_id Application id.
	 * @param WP_Post $post    Post.
	 */
	public function save_status( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['nby_status_nonce'], $_POST['nby_application_status'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nby_status_nonce'] ) ), 'nby_application_status_' . $post_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$status = sanitize_key( wp_unslash( $_POST['nby_application_status'] ) );
		if ( array_key_exists( $status, self::statuses() ) ) {
			self::set_status( $post_id, $status );
		}
	}

	/**
	 * @param int    $application_id Application id.
	 * @param string $status         Status key.
	 */
	public static function set_status( $application_id, $status ) {
		$old = self::status( $application_id );
		update_post_meta( $application_id, self::META_PREFIX . 'status', $status );
		if ( $old !== $status ) {
			do_action( 'nby_it_options_application_status_changed', $application_id, $status, $old );
		}
	}

	/* ---------------------------------------------------------------------
	 * Resume download / cleanup
	 * ------------------------------------------------------------------- */

	/**
	 * Stream a resume to an authorized user.
	 */
	public function download_resume() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only, capability-checked download.
		$application_id = isset( $_GET['application_id'] ) ? absint( $_GET['application_id'] ) : 0;

		if ( ! $application_id || self::POST_TYPE !== get_post_type( $application_id ) ) {
			wp_die( esc_html__( 'Application not found.', 'nby-it-options' ), '', array( 'response' => 404 ) );
		}
		if ( ! current_user_can( 'view_career_applications' ) || ! current_user_can( 'edit_post', $application_id ) ) {
			wp_die( esc_html__( 'You are not allowed to access this file.', 'nby-it-options' ), '', array( 'response' => 403 ) );
		}

		$filename = self::get( $application_id, 'resume_file' );
		$path     = NBY_IT_Options_File_Upload::path( $filename );
		if ( ! $path ) {
			wp_die( esc_html__( 'The resume file is not available.', 'nby-it-options' ), '', array( 'response' => 404 ) );
		}

		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		$name = sanitize_file_name( sanitize_title( self::get( $application_id, 'full_name' ) ) . '-resume.' . $ext );

		while ( ob_get_level() ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: ' . NBY_IT_Options_File_Upload::canonical_mime( $ext ) );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( "Content-Security-Policy: default-src 'none'" );

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Send logged-out visitors (e.g. from the notification email) to the login screen.
	 */
	public function download_requires_login() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$application_id = isset( $_GET['application_id'] ) ? absint( $_GET['application_id'] ) : 0;
		wp_safe_redirect( wp_login_url( self::download_url( $application_id ) ) );
		exit;
	}

	/**
	 * Remove the resume file when an application is permanently deleted.
	 *
	 * @param int $post_id Post id.
	 */
	public function delete_resume_with_post( $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$file = self::get( $post_id, 'resume_file' );
		if ( $file ) {
			NBY_IT_Options_File_Upload::delete( $file );
		}
	}

	/* ---------------------------------------------------------------------
	 * Privacy tools (Tools → Export/Erase Personal Data)
	 * ------------------------------------------------------------------- */

	/**
	 * Application ids for an email.
	 *
	 * @param string $email Email.
	 * @return int[]
	 */
	private static function ids_for_email( $email ) {
		return get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'meta_key'       => self::META_PREFIX . 'email', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => strtolower( $email ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
	}

	/**
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['nby-career-applications'] = array(
			'exporter_friendly_name' => __( 'Career Applications', 'nby-it-options' ),
			'callback'               => function ( $email ) {
				$data = array();
				foreach ( self::ids_for_email( $email ) as $id ) {
					$item = array();
					foreach ( array( 'full_name', 'email', 'phone', 'location', 'linkedin_url', 'portfolio_url', 'cover_letter', 'career_title' ) as $key ) {
						$item[] = array(
							'name'  => self::fields()[ $key ],
							'value' => self::get( $id, $key ),
						);
					}
					$item[] = array(
						'name'  => __( 'Applied', 'nby-it-options' ),
						'value' => get_the_date( 'Y-m-d H:i', $id ),
					);
					$data[] = array(
						'group_id'    => 'nby-career-applications',
						'group_label' => __( 'Career Applications', 'nby-it-options' ),
						'item_id'     => 'nby-application-' . $id,
						'data'        => $item,
					);
				}
				return array(
					'data' => $data,
					'done' => true,
				);
			},
		);
		return $exporters;
	}

	/**
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['nby-career-applications'] = array(
			'eraser_friendly_name' => __( 'Career Applications', 'nby-it-options' ),
			'callback'             => function ( $email ) {
				$removed = false;
				foreach ( self::ids_for_email( $email ) as $id ) {
					$removed = (bool) wp_delete_post( $id, true ) || $removed; // Also deletes the resume.
				}
				return array(
					'items_removed'  => $removed,
					'items_retained' => false,
					'messages'       => array(),
					'done'           => true,
				);
			},
		);
		return $erasers;
	}

	/* ---------------------------------------------------------------------
	 * Overview on the settings page
	 * ------------------------------------------------------------------- */

	public function render_overview() {
		global $wpdb;

		$active  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = %s AND p.post_status = 'publish' AND ( m.meta_value IS NULL OR CAST( m.meta_value AS UNSIGNED ) >= %d )",
				NBY_IT_Options_Career::SORT_META,
				NBY_IT_Options_Career::POST_TYPE,
				(int) NBY_IT_Options_Career::today()
			)
		);
		$total   = (int) wp_count_posts( NBY_IT_Options_Career::POST_TYPE )->publish;
		$expired = max( 0, $total - $active );
		$counts  = self::status_counts();
		$base    = add_query_arg( 'post_type', self::POST_TYPE, admin_url( 'edit.php' ) );
		?>
		<div class="nby-overview">
			<h2><?php esc_html_e( 'Career Overview', 'nby-it-options' ); ?></h2>
			<div class="nby-overview__cards">
				<a class="nby-overview__card" href="<?php echo esc_url( add_query_arg( 'post_type', NBY_IT_Options_Career::POST_TYPE, admin_url( 'edit.php' ) ) ); ?>">
					<span class="nby-overview__num"><?php echo esc_html( number_format_i18n( $active ) ); ?></span>
					<span class="nby-overview__label"><?php esc_html_e( 'Active Jobs', 'nby-it-options' ); ?></span>
				</a>
				<div class="nby-overview__card">
					<span class="nby-overview__num"><?php echo esc_html( number_format_i18n( $expired ) ); ?></span>
					<span class="nby-overview__label"><?php esc_html_e( 'Expired Jobs', 'nby-it-options' ); ?></span>
				</div>
				<a class="nby-overview__card" href="<?php echo esc_url( $base ); ?>">
					<span class="nby-overview__num"><?php echo esc_html( number_format_i18n( array_sum( $counts ) ) ); ?></span>
					<span class="nby-overview__label"><?php esc_html_e( 'Total Applications', 'nby-it-options' ); ?></span>
				</a>
			</div>
			<ul class="nby-overview__statuses">
				<?php foreach ( self::statuses() as $key => $label ) : ?>
					<li>
						<a href="<?php echo esc_url( add_query_arg( 'nby_status', $key, $base ) ); ?>">
							<span class="nby-status nby-status--<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></span>
							<strong><?php echo esc_html( number_format_i18n( $counts[ $key ] ) ); ?></strong>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}
}
