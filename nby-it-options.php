<?php
/**
 * Plugin Name:       NBY IT Options
 * Description:       Site options for NBY IT: Career management and a built-in, secure career application system.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            NBY IT
 * Text Domain:       nby-it-options
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

define( 'NBY_IT_OPTIONS_VERSION', '1.1.0' );
define( 'NBY_IT_OPTIONS_FILE', __FILE__ );
define( 'NBY_IT_OPTIONS_PATH', plugin_dir_path( __FILE__ ) );
define( 'NBY_IT_OPTIONS_URL', plugin_dir_url( __FILE__ ) );

require_once NBY_IT_OPTIONS_PATH . 'includes/class-module.php';
require_once NBY_IT_OPTIONS_PATH . 'includes/class-settings.php';
require_once NBY_IT_OPTIONS_PATH . 'includes/class-post-types.php';
require_once NBY_IT_OPTIONS_PATH . 'includes/class-security.php';
require_once NBY_IT_OPTIONS_PATH . 'includes/class-file-upload.php';
require_once NBY_IT_OPTIONS_PATH . 'includes/class-email.php';
require_once NBY_IT_OPTIONS_PATH . 'includes/class-career.php';
require_once NBY_IT_OPTIONS_PATH . 'includes/class-application.php';
require_once NBY_IT_OPTIONS_PATH . 'includes/class-application-form.php';
require_once NBY_IT_OPTIONS_PATH . 'includes/class-shortcodes.php';
require_once NBY_IT_OPTIONS_PATH . 'includes/class-plugin.php';

register_activation_hook( __FILE__, array( 'NBY_IT_Options_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'NBY_IT_Options_Plugin', 'deactivate' ) );

// Boot early on init (translations are available; post types register later on init).
add_action( 'init', array( 'NBY_IT_Options_Plugin', 'instance' ), 0 );

/**
 * Accessor for the plugin instance.
 *
 * @return NBY_IT_Options_Plugin
 */
function nby_it_options() {
	return NBY_IT_Options_Plugin::instance();
}
