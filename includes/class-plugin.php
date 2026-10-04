<?php
/**
 * Plugin core: wires services and modules together.
 *
 * To add a module (e.g. Portfolio), create a class extending
 * NBY_IT_Options_Module and add it via the `nby_it_options_modules` filter or
 * in build_modules(). Its post types, settings tab and hooks are picked up
 * automatically.
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

final class NBY_IT_Options_Plugin {

	const VERSION_OPTION = 'nby_it_options_version';

	/**
	 * @var NBY_IT_Options_Plugin|null
	 */
	private static $instance = null;

	/** @var NBY_IT_Options_Settings */
	public $settings;

	/** @var NBY_IT_Options_Post_Types */
	public $post_types;

	/** @var NBY_IT_Options_Career */
	public $career;

	/** @var NBY_IT_Options_Application */
	public $application;

	/** @var NBY_IT_Options_Application_Form */
	public $form;

	/** @var NBY_IT_Options_Email */
	public $email;

	/** @var NBY_IT_Options_Shortcodes */
	public $shortcodes;

	/** @var NBY_IT_Options_Module[] */
	private $modules = array();

	/**
	 * @return NBY_IT_Options_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	private function __construct() {}

	private function boot() {
		load_plugin_textdomain( 'nby-it-options', false, dirname( plugin_basename( NBY_IT_OPTIONS_FILE ) ) . '/languages' );

		$this->settings    = new NBY_IT_Options_Settings();
		$this->career      = new NBY_IT_Options_Career( $this->settings );
		$this->application = new NBY_IT_Options_Application( $this->career );
		$this->email       = new NBY_IT_Options_Email( $this->settings );
		$this->form        = new NBY_IT_Options_Application_Form( $this->career, $this->email );
		$this->shortcodes  = new NBY_IT_Options_Shortcodes( $this->career, $this->form, $this->settings );

		$this->modules = array_filter(
			(array) apply_filters(
				'nby_it_options_modules',
				array(
					$this->career->id()      => $this->career,
					$this->application->id() => $this->application,
				),
				$this
			),
			static function ( $module ) {
				return $module instanceof NBY_IT_Options_Module;
			}
		);

		$this->post_types = new NBY_IT_Options_Post_Types( $this->modules );

		$this->settings->register();
		$this->post_types->register();
		foreach ( $this->modules as $module ) {
			$module->register();
		}
		$this->form->register();
		$this->shortcodes->register();

		add_action( 'admin_menu', array( $this, 'admin_menu' ), 9 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_assets' ) );
		add_action( 'admin_notices', array( $this, 'acf_notice' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * @return NBY_IT_Options_Module[]
	 */
	public function modules() {
		return $this->modules;
	}

	/* ---------------------------------------------------------------------
	 * Lifecycle
	 * ------------------------------------------------------------------- */

	public static function activate() {
		$plugin = self::instance();
		$plugin->post_types->register_post_types();
		foreach ( $plugin->modules as $module ) {
			$module->activate();
		}
		flush_rewrite_rules();
		update_option( self::VERSION_OPTION, NBY_IT_OPTIONS_VERSION );
	}

	public static function deactivate() {
		$plugin = self::instance();
		foreach ( array_keys( $plugin->post_types->definitions() ) as $post_type ) {
			unregister_post_type( $post_type );
		}
		flush_rewrite_rules();
	}

	/**
	 * Re-run activation tasks after an update (files replaced without reactivation).
	 */
	public function maybe_upgrade() {
		if ( get_option( self::VERSION_OPTION ) === NBY_IT_OPTIONS_VERSION ) {
			return;
		}
		foreach ( $this->modules as $module ) {
			$module->activate();
		}
		flush_rewrite_rules();
		update_option( self::VERSION_OPTION, NBY_IT_OPTIONS_VERSION );
	}

	/* ---------------------------------------------------------------------
	 * Admin
	 * ------------------------------------------------------------------- */

	public function admin_menu() {
		// WordPress checks the top-level capability for post type screens nested
		// under it, so use one the current user actually has if they may see any
		// item (the Settings item itself always requires manage_options).
		$capability = 'manage_options';
		foreach ( array( 'manage_options', 'edit_career_applications', 'edit_pages' ) as $cap ) {
			if ( current_user_can( $cap ) ) {
				$capability = $cap;
				break;
			}
		}

		add_menu_page(
			__( 'NBY IT Options', 'nby-it-options' ),
			__( 'NBY IT Options', 'nby-it-options' ),
			$capability,
			NBY_IT_Options_Settings::PAGE_SLUG,
			array( $this->settings, 'render_page' ),
			'dashicons-admin-generic',
			58
		);
		add_submenu_page(
			NBY_IT_Options_Settings::PAGE_SLUG,
			__( 'NBY IT Options', 'nby-it-options' ),
			__( 'Overview & Settings', 'nby-it-options' ),
			'manage_options',
			NBY_IT_Options_Settings::PAGE_SLUG,
			array( $this->settings, 'render_page' )
		);
	}

	/**
	 * Load admin assets only on this plugin's screens.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function admin_assets( $hook ) {
		$screen  = get_current_screen();
		$ours    = $screen && in_array( $screen->post_type, array( NBY_IT_Options_Career::POST_TYPE, NBY_IT_Options_Application::POST_TYPE ), true );
		$ours    = $ours || false !== strpos( (string) $hook, NBY_IT_Options_Settings::PAGE_SLUG );
		if ( ! $ours ) {
			return;
		}
		wp_enqueue_style( 'nby-it-options-admin', NBY_IT_OPTIONS_URL . 'admin/css/admin.css', array(), NBY_IT_OPTIONS_VERSION );
		if ( $screen && NBY_IT_Options_Application::POST_TYPE === $screen->post_type ) {
			wp_enqueue_script( 'nby-it-options-admin', NBY_IT_OPTIONS_URL . 'admin/js/admin.js', array(), NBY_IT_OPTIONS_VERSION, true );
		}
	}

	public function acf_notice() {
		if ( class_exists( 'ACF' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'NBY IT Options requires Advanced Custom Fields (ACF).', 'nby-it-options' ) . '</strong> '
			. esc_html__( 'Career details (type, location, deadline, level, salary) cannot be edited until ACF is active. Existing data and applications keep working.', 'nby-it-options' )
			. '</p></div>';
	}

	/* ---------------------------------------------------------------------
	 * Templates
	 * ------------------------------------------------------------------- */

	/**
	 * Render a template. Themes can override it at
	 * `your-theme/nby-it-options/{name}.php`.
	 *
	 * @param string $name Template name without extension.
	 * @param array  $vars Variables available as $vars inside the template.
	 * @return string
	 */
	public static function template( $name, array $vars = array() ) {
		$name     = sanitize_file_name( $name );
		$template = locate_template( 'nby-it-options/' . $name . '.php' );
		if ( ! $template ) {
			$template = NBY_IT_OPTIONS_PATH . 'templates/' . $name . '.php';
		}
		$template = apply_filters( 'nby_it_options_template', $template, $name, $vars );
		if ( ! is_readable( $template ) ) {
			return '';
		}
		ob_start();
		include $template;
		return (string) ob_get_clean();
	}
}
