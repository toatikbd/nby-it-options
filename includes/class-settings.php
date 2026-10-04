<?php
/**
 * Modular settings page built on the Settings API.
 *
 * Each tab stores its values in its own option (`nby_it_options_{tab}`). Modules
 * add tabs with NBY_IT_Options_Settings::add_tab(); fields are declared as arrays
 * and sanitized generically according to their type.
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

class NBY_IT_Options_Settings {

	const PAGE_SLUG = 'nby-it-options';

	/**
	 * Registered tabs.
	 *
	 * @var array<string, array>
	 */
	private $tabs = array();

	public function register() {
		$this->add_tab(
			'general',
			__( 'General', 'nby-it-options' ),
			array(
				'general' => array(
					'title'  => __( 'General Settings', 'nby-it-options' ),
					'fields' => array(
						'company_name'  => array(
							'label'       => __( 'Company Name', 'nby-it-options' ),
							'type'        => 'text',
							'default'     => 'NBY IT',
							'description' => __( 'Used as the signature in emails sent to applicants.', 'nby-it-options' ),
						),
						'primary_color' => array(
							'label'       => __( 'Primary Color', 'nby-it-options' ),
							'type'        => 'color',
							'default'     => '#1f4d3a',
							'description' => __( 'Accent color used by the careers listing and application form.', 'nby-it-options' ),
						),
					),
				),
			),
			5
		);

		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add a settings tab.
	 *
	 * @param string $id       Tab id (also the option suffix).
	 * @param string $label    Tab label.
	 * @param array  $sections Map of section id => array( 'title' => string, 'fields' => array ).
	 * @param int    $priority Sort order.
	 */
	public function add_tab( $id, $label, array $sections, $priority = 10 ) {
		$this->tabs[ $id ] = array(
			'label'    => $label,
			'sections' => $sections,
			'priority' => $priority,
		);
	}

	/**
	 * Tabs sorted by priority.
	 *
	 * @return array<string, array>
	 */
	public function tabs() {
		$tabs = apply_filters( 'nby_it_options_settings_tabs', $this->tabs );
		uasort(
			$tabs,
			static function ( $a, $b ) {
				return $a['priority'] - $b['priority'];
			}
		);
		return $tabs;
	}

	/**
	 * Option name for a tab.
	 *
	 * @param string $tab Tab id.
	 * @return string
	 */
	public static function option_name( $tab ) {
		return 'nby_it_options_' . $tab;
	}

	/**
	 * Defaults for a tab.
	 *
	 * @param string $tab Tab id.
	 * @return array
	 */
	public function defaults( $tab ) {
		$defaults = array();
		$tabs     = $this->tabs();
		if ( empty( $tabs[ $tab ] ) ) {
			return $defaults;
		}
		foreach ( $tabs[ $tab ]['sections'] as $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				$defaults[ $key ] = isset( $field['default'] ) ? $field['default'] : '';
			}
		}
		return $defaults;
	}

	/**
	 * Read a setting value (falls back to the field default).
	 *
	 * @param string $tab Tab id.
	 * @param string $key Field key.
	 * @return mixed
	 */
	public function get( $tab, $key ) {
		$values = get_option( self::option_name( $tab ), array() );
		if ( is_array( $values ) && array_key_exists( $key, $values ) ) {
			return $values[ $key ];
		}
		$defaults = $this->defaults( $tab );
		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : null;
	}

	public function register_settings() {
		foreach ( $this->tabs() as $tab_id => $tab ) {
			$option = self::option_name( $tab_id );
			$page   = self::PAGE_SLUG . '-' . $tab_id;

			register_setting(
				$page,
				$option,
				array(
					'type'              => 'array',
					'sanitize_callback' => function ( $input ) use ( $tab_id ) {
						return $this->sanitize_tab( $tab_id, $input );
					},
					'default'           => $this->defaults( $tab_id ),
					'show_in_rest'      => false,
				)
			);

			foreach ( $tab['sections'] as $section_id => $section ) {
				add_settings_section(
					$tab_id . '_' . $section_id,
					$section['title'],
					static function () use ( $section ) {
						if ( ! empty( $section['description'] ) ) {
							echo '<p>' . esc_html( $section['description'] ) . '</p>';
						}
					},
					$page
				);

				foreach ( $section['fields'] as $key => $field ) {
					$field_id = $option . '_' . $key;
					add_settings_field(
						$field_id,
						$field['label'],
						array( $this, 'render_field' ),
						$page,
						$tab_id . '_' . $section_id,
						array(
							'tab'       => $tab_id,
							'key'       => $key,
							'field'     => $field,
							'label_for' => in_array( $field['type'], array( 'multicheck' ), true ) ? '' : $field_id,
						)
					);
				}
			}
		}
	}

	/**
	 * Sanitize the values of one tab.
	 *
	 * @param string $tab_id Tab id.
	 * @param mixed  $input  Raw input.
	 * @return array
	 */
	public function sanitize_tab( $tab_id, $input ) {
		$input  = is_array( $input ) ? $input : array();
		$tabs   = $this->tabs();
		$output = array();

		if ( empty( $tabs[ $tab_id ] ) ) {
			return $output;
		}

		foreach ( $tabs[ $tab_id ]['sections'] as $section ) {
			foreach ( $section['fields'] as $key => $field ) {
				$raw     = isset( $input[ $key ] ) ? wp_unslash( $input[ $key ] ) : null;
				$default = isset( $field['default'] ) ? $field['default'] : '';

				switch ( $field['type'] ) {
					case 'checkbox':
						// The form always posts a hidden "0" before each checkbox. If the key is
						// missing entirely (form rendered before this field existed), keep the
						// current value instead of silently switching the option off.
						if ( ! array_key_exists( $key, $input ) ) {
							$output[ $key ] = (int) $this->get( $tab_id, $key ) ? 1 : 0;
							break;
						}
						$output[ $key ] = empty( $raw ) ? 0 : 1;
						break;

					case 'number':
						$value = is_numeric( $raw ) ? (int) $raw : (int) $default;
						if ( isset( $field['min'] ) ) {
							$value = max( (int) $field['min'], $value );
						}
						if ( isset( $field['max'] ) ) {
							$value = min( (int) $field['max'], $value );
						}
						$output[ $key ] = $value;
						break;

					case 'email':
						$email = sanitize_email( (string) $raw );
						if ( '' !== (string) $raw && ! is_email( $email ) && function_exists( 'add_settings_error' ) ) {
							add_settings_error(
								self::option_name( $tab_id ),
								$key,
								/* translators: %s: field label. */
								sprintf( __( '%s is not a valid email address and was not saved.', 'nby-it-options' ), $field['label'] )
							);
							$email = (string) $this->get( $tab_id, $key );
						}
						$output[ $key ] = $email;
						break;

					case 'select':
						$output[ $key ] = array_key_exists( (string) $raw, $field['options'] ) ? (string) $raw : $default;
						break;

					case 'multicheck':
						$raw            = is_array( $raw ) ? array_map( 'sanitize_key', $raw ) : array();
						$value          = array_values( array_intersect( array_keys( $field['options'] ), $raw ) );
						$output[ $key ] = $value ? $value : (array) $default;
						break;

					case 'page':
						$page_id        = absint( $raw );
						$output[ $key ] = ( $page_id && 'page' === get_post_type( $page_id ) ) ? $page_id : 0;
						break;

					case 'color':
						$color          = sanitize_hex_color( (string) $raw );
						$output[ $key ] = $color ? $color : $default;
						break;

					default:
						$output[ $key ] = sanitize_text_field( (string) $raw );
				}
			}
		}

		return $output;
	}

	/**
	 * Render a single field.
	 *
	 * @param array $args Field arguments.
	 */
	public function render_field( $args ) {
		$tab   = $args['tab'];
		$key   = $args['key'];
		$field = $args['field'];
		$name  = self::option_name( $tab ) . '[' . $key . ']';
		$id    = self::option_name( $tab ) . '_' . $key;
		$value = $this->get( $tab, $key );

		switch ( $field['type'] ) {
			case 'checkbox':
				printf(
					'<input type="hidden" name="%2$s" value="0" /><label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $name ),
					checked( (int) $value, 1, false ),
					esc_html( isset( $field['checkbox_label'] ) ? $field['checkbox_label'] : __( 'Yes', 'nby-it-options' ) )
				);
				break;

			case 'number':
				printf(
					'<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s" step="1" />%6$s',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( isset( $field['min'] ) ? (string) $field['min'] : '' ),
					esc_attr( isset( $field['max'] ) ? (string) $field['max'] : '' ),
					isset( $field['suffix'] ) ? ' ' . esc_html( $field['suffix'] ) : ''
				);
				break;

			case 'select':
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
				foreach ( $field['options'] as $option_value => $option_label ) {
					printf(
						'<option value="%1$s" %2$s>%3$s</option>',
						esc_attr( $option_value ),
						selected( (string) $value, (string) $option_value, false ),
						esc_html( $option_label )
					);
				}
				echo '</select>';
				break;

			case 'multicheck':
				$value = (array) $value;
				echo '<fieldset>';
				foreach ( $field['options'] as $option_value => $option_label ) {
					printf(
						'<label style="margin-right:1em"><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label>',
						esc_attr( $name ),
						esc_attr( $option_value ),
						checked( in_array( $option_value, $value, true ), true, false ),
						esc_html( $option_label )
					);
				}
				echo '</fieldset>';
				break;

			case 'page':
				wp_dropdown_pages(
					array(
						'name'              => esc_attr( $name ),
						'id'                => esc_attr( $id ),
						'selected'          => absint( $value ),
						'show_option_none'  => esc_html__( '— Select Page —', 'nby-it-options' ),
						'option_none_value' => '0',
					)
				);
				break;

			case 'color':
				printf(
					'<input type="color" id="%1$s" name="%2$s" value="%3$s" />',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value )
				);
				break;

			default:
				printf(
					'<input type="%1$s" class="regular-text" id="%2$s" name="%3$s" value="%4$s" placeholder="%5$s" />',
					esc_attr( 'email' === $field['type'] ? 'email' : 'text' ),
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' )
				);
		}

		if ( ! empty( $field['description'] ) ) {
			echo '<p class="description">' . wp_kses_post( $field['description'] ) . '</p>';
		}
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'nby-it-options' ), 403 );
		}

		$tabs    = $this->tabs();
		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $current ] ) ) {
			$current = (string) key( $tabs );
		}
		$page = self::PAGE_SLUG . '-' . $current;
		?>
		<div class="wrap nby-admin">
			<h1><?php esc_html_e( 'NBY IT Options', 'nby-it-options' ); ?></h1>
			<?php settings_errors(); ?>

			<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Settings sections', 'nby-it-options' ); ?>">
				<?php foreach ( $tabs as $tab_id => $tab ) : ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => self::PAGE_SLUG, 'tab' => $tab_id ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $tab_id === $current ? 'nav-tab-active' : ''; ?>"
						<?php echo $tab_id === $current ? 'aria-current="page"' : ''; ?>>
						<?php echo esc_html( $tab['label'] ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php do_action( 'nby_it_options_settings_before_' . $current ); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( $page );
				do_settings_sections( $page );
				submit_button();
				?>
			</form>

			<?php do_action( 'nby_it_options_settings_after_' . $current ); ?>
		</div>
		<?php
	}
}
