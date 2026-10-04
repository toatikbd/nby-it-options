<?php
/**
 * Base class for content modules (Career, and future Portfolio, Team, Services...).
 *
 * A module declares its post types and hooks. The plugin core registers every
 * module returned by the `nby_it_options_modules` filter, so new modules can be
 * added without touching the core.
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

abstract class NBY_IT_Options_Module {

	/**
	 * Unique module id, e.g. "career".
	 *
	 * @return string
	 */
	abstract public function id();

	/**
	 * Attach WordPress hooks. Called once when the plugin boots (init, priority 0).
	 */
	abstract public function register();

	/**
	 * Post type definitions owned by this module.
	 *
	 * @return array<string, array> Map of post type key => register_post_type() args.
	 */
	public function post_types() {
		return array();
	}

	/**
	 * Runs on plugin activation (roles, capabilities, directories...).
	 */
	public function activate() {}
}
