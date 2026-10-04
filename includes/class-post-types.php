<?php
/**
 * Registers post types declared by modules.
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

class NBY_IT_Options_Post_Types {

	/**
	 * Modules providing post types.
	 *
	 * @var NBY_IT_Options_Module[]
	 */
	private $modules;

	/**
	 * @param NBY_IT_Options_Module[] $modules Active modules.
	 */
	public function __construct( array $modules ) {
		$this->modules = $modules;
	}

	public function register() {
		add_action( 'init', array( $this, 'register_post_types' ) );
	}

	/**
	 * All post type definitions, filterable.
	 *
	 * @return array<string, array>
	 */
	public function definitions() {
		$definitions = array();
		foreach ( $this->modules as $module ) {
			$definitions = array_merge( $definitions, $module->post_types() );
		}
		return apply_filters( 'nby_it_options_post_types', $definitions );
	}

	public function register_post_types() {
		foreach ( $this->definitions() as $post_type => $args ) {
			if ( ! post_type_exists( $post_type ) ) {
				register_post_type( $post_type, $args );
			}
		}
	}

	/**
	 * Build a standard labels array.
	 *
	 * @param string $singular Singular label.
	 * @param string $plural   Plural label.
	 * @return array
	 */
	public static function labels( $singular, $plural ) {
		return array(
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $plural,
			'all_items'          => $plural,
			/* translators: %s: singular post type label. */
			'add_new_item'       => sprintf( __( 'Add New %s', 'nby-it-options' ), $singular ),
			'add_new'            => __( 'Add New', 'nby-it-options' ),
			/* translators: %s: singular post type label. */
			'edit_item'          => sprintf( __( 'Edit %s', 'nby-it-options' ), $singular ),
			/* translators: %s: singular post type label. */
			'new_item'           => sprintf( __( 'New %s', 'nby-it-options' ), $singular ),
			/* translators: %s: singular post type label. */
			'view_item'          => sprintf( __( 'View %s', 'nby-it-options' ), $singular ),
			/* translators: %s: plural post type label. */
			'search_items'       => sprintf( __( 'Search %s', 'nby-it-options' ), $plural ),
			/* translators: %s: plural post type label. */
			'not_found'          => sprintf( __( 'No %s found.', 'nby-it-options' ), strtolower( $plural ) ),
			/* translators: %s: plural post type label. */
			'not_found_in_trash' => sprintf( __( 'No %s found in Trash.', 'nby-it-options' ), strtolower( $plural ) ),
		);
	}
}
