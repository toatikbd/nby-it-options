<?php
/**
 * Shortcodes and their (conditionally loaded) assets.
 *
 * Assets are registered globally but only enqueued when a shortcode is present
 * in the queried post content, or when the shortcode actually renders (covers
 * page builders such as Elementor that do not store shortcodes in post_content).
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

class NBY_IT_Options_Shortcodes {

	/**
	 * @var NBY_IT_Options_Career
	 */
	private $career;

	/**
	 * @var NBY_IT_Options_Application_Form
	 */
	private $form;

	/**
	 * @var NBY_IT_Options_Settings
	 */
	private $settings;

	/**
	 * @param NBY_IT_Options_Career           $career   Career module.
	 * @param NBY_IT_Options_Application_Form $form     Application form.
	 * @param NBY_IT_Options_Settings         $settings Settings.
	 */
	public function __construct( NBY_IT_Options_Career $career, NBY_IT_Options_Application_Form $form, NBY_IT_Options_Settings $settings ) {
		$this->career   = $career;
		$this->form     = $form;
		$this->settings = $settings;
	}

	public function register() {
		add_shortcode( 'nby_careers', array( $this, 'careers' ) );
		add_shortcode( 'nby_career_application', array( $this, 'application' ) );
		add_shortcode( 'nby_career_single', array( $this, 'career_single' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets() {
		$version = NBY_IT_OPTIONS_VERSION;

		wp_register_style( 'nby-careers', NBY_IT_OPTIONS_URL . 'public/css/careers.css', array(), $version );
		wp_register_style( 'nby-application', NBY_IT_OPTIONS_URL . 'public/css/application.css', array( 'nby-careers' ), $version );
		wp_register_style( 'nby-career-single', NBY_IT_OPTIONS_URL . 'public/css/career-single.css', array( 'nby-careers' ), $version );
		wp_register_script( 'nby-application', NBY_IT_OPTIONS_URL . 'public/js/application.js', array(), $version, true );

		$color = sanitize_hex_color( (string) $this->settings->get( 'general', 'primary_color' ) );
		if ( $color ) {
			wp_add_inline_style( 'nby-careers', ':root{--nby-primary:' . $color . ';}' );
		}

		if ( is_singular( NBY_IT_Options_Career::POST_TYPE ) && $this->career->single_layout_enabled() ) {
			wp_enqueue_style( 'nby-career-single' );
		}

		// Enqueue early (in <head>) when the shortcode is in the post content.
		$post = is_singular() ? get_post( get_queried_object_id() ) : null;
		if ( $post ) {
			if ( has_shortcode( $post->post_content, 'nby_careers' ) ) {
				wp_enqueue_style( 'nby-careers' );
			}
			if ( has_shortcode( $post->post_content, 'nby_career_application' ) || $post->ID === $this->career->application_page_id() ) {
				$this->enqueue_application_assets();
			}
		}
	}

	private function enqueue_application_assets() {
		if ( wp_script_is( 'nby-application', 'enqueued' ) ) {
			return;
		}
		wp_enqueue_style( 'nby-application' );
		wp_enqueue_script( 'nby-application' );
		wp_localize_script(
			'nby-application',
			'nbyApplication',
			array(
				'maxBytes' => $this->career->max_resume_bytes(),
				'types'    => $this->career->allowed_resume_types(),
				'i18n'     => array(
					'required'   => __( 'This field is required.', 'nby-it-options' ),
					'email'      => __( 'Please enter a valid email address.', 'nby-it-options' ),
					'url'        => __( 'Please enter a valid URL.', 'nby-it-options' ),
					'fileType'   => __( 'Invalid file type.', 'nby-it-options' ),
					/* translators: %s: maximum size. */
					'fileSize'   => sprintf( __( 'The file is too large. The maximum size is %s.', 'nby-it-options' ), size_format( $this->career->max_resume_bytes() ) ),
					'consent'    => __( 'Please agree to the processing of your information.', 'nby-it-options' ),
					'submitting' => __( 'Submitting…', 'nby-it-options' ),
					'summary'    => __( 'Please correct the highlighted fields.', 'nby-it-options' ),
				),
			)
		);
	}

	/**
	 * [nby_careers per_page="10" orderby="date" order="DESC" show_expired="no"]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function careers( $atts ) {
		if ( ! (int) $this->career->setting( 'enable_listing' ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'per_page'     => $this->career->setting( 'per_page' ),
				'orderby'      => $this->career->setting( 'orderby' ),
				'order'        => $this->career->setting( 'order' ),
				'show_expired' => (int) $this->career->setting( 'show_expired' ) ? 'yes' : 'no',
			),
			$atts,
			'nby_careers'
		);

		wp_enqueue_style( 'nby-careers' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- pagination only.
		$paged = isset( $_GET['careers_page'] ) ? max( 1, absint( $_GET['careers_page'] ) ) : 1;

		$query = $this->career->query(
			array(
				'per_page'     => min( 100, max( 1, absint( $atts['per_page'] ) ) ),
				'orderby'      => in_array( $atts['orderby'], array( 'date', 'title', 'deadline', 'menu_order' ), true ) ? $atts['orderby'] : 'date',
				'order'        => $atts['order'],
				'show_expired' => in_array( strtolower( (string) $atts['show_expired'] ), array( 'yes', '1', 'true' ), true ),
				'paged'        => $paged,
			)
		);

		$jobs = array();
		foreach ( $query->posts as $post ) {
			$jobs[] = $this->career->get_data( $post );
		}

		$pagination = '';
		if ( $query->max_num_pages > 1 ) {
			$pagination = paginate_links(
				array(
					'base'      => esc_url_raw( add_query_arg( 'careers_page', '%#%' ) ),
					'format'    => '',
					'current'   => $paged,
					'total'     => (int) $query->max_num_pages,
					'prev_text' => __( '&larr; Previous', 'nby-it-options' ),
					'next_text' => __( 'Next &rarr;', 'nby-it-options' ),
				)
			);
		}

		return NBY_IT_Options_Plugin::template(
			'career-list',
			array(
				'jobs'       => $jobs,
				'offset'     => ( $paged - 1 ) * (int) $query->get( 'posts_per_page' ),
				'pagination' => $pagination,
			)
		);
	}

	/**
	 * [nby_career_application]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function application( $atts ) {
		$this->enqueue_application_assets();
		return $this->form->render( (array) $atts );
	}
	/**
	 * [nby_career_single] — two-column job page: other open jobs (left) and the
	 * job details with "Apply Now" (right). Used by the plugin's single career
	 * template; can also be placed in an Elementor single template.
	 *
	 * @param array|string $atts Attributes: optional `job` (slug); defaults to the current career.
	 * @return string
	 */
	public function career_single( $atts ) {
		static $rendering = false;
		if ( $rendering ) {
			return ''; // Prevent recursion if the job content contains this shortcode.
		}

		$atts      = shortcode_atts( array( 'job' => '' ), $atts, 'nby_career_single' );
		$career_id = '' !== $atts['job'] ? NBY_IT_Options_Career::find_by_slug( $atts['job'] ) : 0;
		if ( ! $career_id && is_singular( NBY_IT_Options_Career::POST_TYPE ) ) {
			$career_id = get_queried_object_id();
		}
		$post = $career_id ? get_post( $career_id ) : null;
		if ( ! $post || NBY_IT_Options_Career::POST_TYPE !== $post->post_type ) {
			return '';
		}

		wp_enqueue_style( 'nby-career-single' );

		$rendering = true;
		$content   = apply_filters( 'the_content', get_the_content( null, false, $post ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
		$rendering = false;

		$job = $this->career->get_data( $post );

		return NBY_IT_Options_Plugin::template(
			'career-single',
			array(
				'job'         => $job,
				'content'     => $content,
				'image'       => get_the_post_thumbnail( $post, 'large', array( 'class' => 'nby-single__image' ) ),
				'other_jobs'  => $this->career->other_jobs( $post->ID ),
				'careers_url' => $this->career->careers_url(),
				'can_apply'   => NBY_IT_Options_Career::is_open_post( $post->ID ) && ! $job['is_expired'],
			)
		);
	}
}
