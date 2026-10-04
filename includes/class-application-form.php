<?php
/**
 * Frontend application form: rendering, server-side validation and submission.
 *
 * Submissions are processed on `template_redirect` (no custom PHP endpoints).
 * On validation errors the same request re-renders the form with messages; on
 * success the visitor is redirected (POST/redirect/GET) to a one-time success URL.
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

class NBY_IT_Options_Application_Form {

	const ACTION        = 'nby_submit_application';
	const SUCCESS_PARAM = 'nby_applied';

	/**
	 * @var NBY_IT_Options_Career
	 */
	private $career;

	/**
	 * @var NBY_IT_Options_Email
	 */
	private $email;

	/**
	 * Errors for the current request: field key (or "form") => message.
	 *
	 * @var array<string, string>
	 */
	private $errors = array();

	/**
	 * Sanitized submitted values, used to refill the form after errors.
	 *
	 * @var array<string, string>
	 */
	private $old = array();

	/**
	 * Career id the current POST was for.
	 *
	 * @var int
	 */
	private $submitted_career = 0;

	/**
	 * @param NBY_IT_Options_Career $career Career module.
	 * @param NBY_IT_Options_Email  $email  Email service.
	 */
	public function __construct( NBY_IT_Options_Career $career, NBY_IT_Options_Email $email ) {
		$this->career = $career;
		$this->email  = $email;
	}

	public function register() {
		add_action( 'template_redirect', array( $this, 'redirect_legacy_url' ), 0 );
		add_action( 'template_redirect', array( $this, 'prevent_caching' ), 1 );
		add_action( 'template_redirect', array( $this, 'handle_submission' ), 5 );
	}

	/**
	 * Field definitions used by the template.
	 *
	 * @return array<string, array>
	 */
	public static function fields() {
		return array(
			'full_name'     => array(
				'label'        => __( 'Full Name', 'nby-it-options' ),
				'type'         => 'text',
				'required'     => true,
				'max'          => 100,
				'autocomplete' => 'name',
			),
			'email'         => array(
				'label'        => __( 'Email Address', 'nby-it-options' ),
				'type'         => 'email',
				'required'     => true,
				'max'          => 100,
				'autocomplete' => 'email',
			),
			'phone'         => array(
				'label'        => __( 'Phone', 'nby-it-options' ),
				'type'         => 'tel',
				'required'     => true,
				'max'          => 30,
				'autocomplete' => 'tel',
			),
			'location'      => array(
				'label'        => __( 'Current Location', 'nby-it-options' ),
				'type'         => 'text',
				'required'     => true,
				'max'          => 150,
				'autocomplete' => 'address-level2',
			),
			'linkedin_url'  => array(
				'label'        => __( 'LinkedIn Profile', 'nby-it-options' ),
				'type'         => 'url',
				'required'     => false,
				'max'          => 255,
				'placeholder'  => 'https://linkedin.com/in/…',
				'autocomplete' => 'url',
			),
			'portfolio_url' => array(
				'label'        => __( 'Portfolio', 'nby-it-options' ),
				'type'         => 'url',
				'required'     => false,
				'max'          => 255,
				'placeholder'  => 'https://',
				'autocomplete' => 'url',
			),
			'cover_letter'  => array(
				'label'    => __( 'Cover Letter', 'nby-it-options' ),
				'type'     => 'textarea',
				'required' => true,
				'max'      => 5000,
			),
		);
	}

	/**
	 * Whether the current main query is a page that renders the application form.
	 *
	 * @return bool
	 */
	private function is_application_page() {
		if ( ! is_singular() ) {
			return false;
		}
		$post_id = get_queried_object_id();
		if ( $post_id && $post_id === $this->career->application_page_id() ) {
			return true;
		}
		$post = get_post( $post_id );
		return $post && has_shortcode( $post->post_content, 'nby_career_application' );
	}

	/**
	 * Permanently redirect old ?job_id=123 links to ?job=slug so IDs never stay in public URLs.
	 */
	public function redirect_legacy_url() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect.
		if ( ! isset( $_GET['job_id'] ) || isset( $_GET['job'] ) || ! empty( $_POST ) || ! $this->is_application_page() ) {
			return;
		}
		$career_id = absint( $_GET['job_id'] );
		// phpcs:enable
		$base = remove_query_arg( 'job_id', get_permalink( get_queried_object_id() ) );
		if ( NBY_IT_Options_Career::is_open_post( $career_id ) ) {
			$base = add_query_arg( 'job', rawurlencode( NBY_IT_Options_Career::slug( $career_id ) ), $base );
		}
		wp_safe_redirect( $base, 301 );
		exit;
	}

	/**
	 * Never cache the form page: it carries a nonce and per-visitor messages.
	 */
	public function prevent_caching() {
		if ( ! $this->is_application_page() ) {
			return;
		}
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
		nocache_headers();
	}

	/**
	 * Process a submitted application.
	 */
	public function handle_submission() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $method ) {
			return;
		}

		// A request larger than post_max_size arrives with empty $_POST and $_FILES.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_POST ) && empty( $_FILES ) && ! empty( $_SERVER['CONTENT_LENGTH'] ) && isset( $_GET['job'] ) && $this->is_application_page() ) {
			$this->submitted_career = NBY_IT_Options_Career::find_by_slug( sanitize_title( wp_unslash( $_GET['job'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$this->errors['resume'] = sprintf(
				/* translators: %s: maximum size. */
				__( 'Your resume is too large. The maximum size is %s.', 'nby-it-options' ),
				size_format( $this->career->max_resume_bytes() )
			);
			return;
		}

		if ( ! isset( $_POST['nby_action'] ) || self::ACTION !== $_POST['nby_action'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		// Nonce, honeypot, timing, rate limit and pluggable CAPTCHA checks.
		$security = NBY_IT_Options_Security::verify_submission();

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified above; still sanitized to refill the form.
		// The job is identified publicly by its slug; the ID is resolved server-side.
		$career_id              = isset( $_POST['job'] ) && is_string( $_POST['job'] ) ? NBY_IT_Options_Career::find_by_slug( sanitize_title( wp_unslash( $_POST['job'] ) ) ) : 0;
		$this->submitted_career = $career_id;

		$raw = array();
		foreach ( array_keys( self::fields() ) as $key ) {
			$raw[ $key ] = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
		}
		$consent = ! empty( $_POST['privacy_consent'] );
		$file    = isset( $_FILES['resume'] ) && is_array( $_FILES['resume'] ) ? $_FILES['resume'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by NBY_IT_Options_File_Upload.
		// phpcs:enable

		$data         = $this->sanitize( $raw );
		$this->old    = $data;
		$this->old['privacy_consent'] = $consent ? '1' : '';

		if ( is_wp_error( $security ) ) {
			$this->errors['form'] = $security->get_error_message();
			return;
		}

		// Career must exist, be a published career and still be open.
		if ( ! NBY_IT_Options_Career::is_open_post( $career_id ) ) {
			$this->errors['form'] = __( 'This position is not available. Please choose another position from our Careers page.', 'nby-it-options' );
			return;
		}
		if ( NBY_IT_Options_Career::is_expired( $career_id ) ) {
			$this->errors['form'] = __( 'The application deadline for this position has passed.', 'nby-it-options' );
			return;
		}

		$this->errors = $this->validate( $data, $consent );

		$allowed   = $this->career->allowed_resume_types();
		$max_bytes = $this->career->max_resume_bytes();
		$ext       = NBY_IT_Options_File_Upload::validate( $file, $allowed, $max_bytes );
		if ( is_wp_error( $ext ) ) {
			$this->errors['resume'] = $ext->get_error_message();
		}

		if ( $this->errors ) {
			return;
		}

		// Duplicate check, only against the submitted email for this career.
		if ( NBY_IT_Options_Application::exists_for( $data['email'], $career_id ) ) {
			$this->errors['form'] = __( 'You have already submitted an application for this position. If you need to update your application, please contact our recruitment team.', 'nby-it-options' );
			return;
		}

		$stored = NBY_IT_Options_File_Upload::store( $file, $ext, $data['full_name'] );
		if ( is_wp_error( $stored ) ) {
			$this->errors['resume'] = $stored->get_error_message();
			return;
		}

		$data['career_id']    = (string) $career_id;
		$data['career_title'] = get_the_title( $career_id );
		$data['resume_file']  = $stored['file'];
		$data['resume_mime']  = $stored['mime'];

		$application_id = NBY_IT_Options_Application::create( $data );
		if ( is_wp_error( $application_id ) || ! $application_id ) {
			NBY_IT_Options_File_Upload::delete( $stored['file'] );
			$this->errors['form'] = __( 'Sorry, we could not save your application. Please try again later.', 'nby-it-options' );
			return;
		}

		NBY_IT_Options_Security::record_submission();

		$this->email->send_admin_notification( $application_id );
		$this->email->send_applicant_confirmation( $application_id );

		// Short-lived random token: the success screen reveals nothing and cannot be forged.
		$token = strtolower( wp_generate_password( 20, false ) );
		set_transient( 'nby_app_ok_' . $token, $career_id, 30 * MINUTE_IN_SECONDS );

		$page_id  = get_queried_object_id();
		$base_url = $page_id ? get_permalink( $page_id ) : $this->career->apply_url( $career_id );
		$redirect = add_query_arg(
			array(
				'job'               => rawurlencode( NBY_IT_Options_Career::slug( $career_id ) ),
				self::SUCCESS_PARAM => $token,
			),
			$base_url
		);

		wp_safe_redirect( $redirect, 303 );
		exit;
	}

	/**
	 * Sanitize raw values.
	 *
	 * @param array $raw Raw (unslashed) values.
	 * @return array
	 */
	private function sanitize( array $raw ) {
		$fields = self::fields();
		$data   = array();
		foreach ( $fields as $key => $field ) {
			$value = (string) $raw[ $key ];
			switch ( $field['type'] ) {
				case 'email':
					$email = strtolower( sanitize_email( $value ) );
					// Keep invalid input (as plain text) so validation reports "invalid", not "required".
					$value = '' === $email ? sanitize_text_field( $value ) : $email;
					break;
				case 'url':
					$value = trim( $value );
					if ( '' !== $value && ! preg_match( '#^https?://#i', $value ) ) {
						$value = 'https://' . $value;
					}
					$value = '' === $value ? '' : esc_url_raw( $value, array( 'http', 'https' ) );
					break;
				case 'textarea':
					$value = sanitize_textarea_field( $value );
					break;
				default:
					$value = sanitize_text_field( $value );
			}
			$data[ $key ] = function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $field['max'] * 2 ) : substr( $value, 0, $field['max'] * 2 );
		}
		return $data;
	}

	/**
	 * Validate sanitized values.
	 *
	 * @param array $data    Sanitized values.
	 * @param bool  $consent Privacy consent given.
	 * @return array<string, string> Errors.
	 */
	private function validate( array $data, $consent ) {
		$errors = array();
		$strlen = function_exists( 'mb_strlen' ) ? 'mb_strlen' : 'strlen';

		foreach ( self::fields() as $key => $field ) {
			$value = $data[ $key ];

			if ( $field['required'] && '' === trim( $value ) ) {
				/* translators: %s: field label. */
				$errors[ $key ] = sprintf( __( '%s is required.', 'nby-it-options' ), $field['label'] );
				continue;
			}
			if ( '' === $value ) {
				continue;
			}
			if ( $strlen( $value ) > $field['max'] ) {
				/* translators: 1: field label, 2: max characters. */
				$errors[ $key ] = sprintf( __( '%1$s must be %2$d characters or fewer.', 'nby-it-options' ), $field['label'], $field['max'] );
				continue;
			}

			switch ( $field['type'] ) {
				case 'email':
					if ( ! is_email( $value ) ) {
						$errors[ $key ] = __( 'Please enter a valid email address.', 'nby-it-options' );
					}
					break;
				case 'tel':
					$digits = preg_replace( '/\D/', '', $value );
					if ( ! preg_match( '/^[0-9+()\-.\s\/]+$/', $value ) || strlen( $digits ) < 6 || strlen( $digits ) > 20 ) {
						$errors[ $key ] = __( 'Please enter a valid phone number.', 'nby-it-options' );
					}
					break;
				case 'url':
					$host = wp_parse_url( $value, PHP_URL_HOST );
					if ( ! filter_var( $value, FILTER_VALIDATE_URL ) || ! $host || false === strpos( $host, '.' ) ) {
						/* translators: %s: field label. */
						$errors[ $key ] = sprintf( __( 'Please enter a valid URL for %s.', 'nby-it-options' ), $field['label'] );
					}
					break;
			}
		}

		if ( ! $consent ) {
			$errors['privacy_consent'] = __( 'Please agree to the processing of your information to submit your application.', 'nby-it-options' );
		}

		return apply_filters( 'nby_it_options_application_validation_errors', $errors, $data );
	}

	/**
	 * [nby_career_application] output.
	 *
	 * @param array $atts Attributes: optional `job` (slug) or `job_id` override.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'job'    => '',
				'job_id' => 0,
			),
			$atts,
			'nby_career_application'
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only display parameters.
		$slug = '' !== $atts['job'] ? $atts['job'] : ( isset( $_GET['job'] ) && is_string( $_GET['job'] ) ? wp_unslash( $_GET['job'] ) : '' );
		$slug = sanitize_title( $slug );

		$career_id = absint( $atts['job_id'] );
		if ( ! $career_id && '' !== $slug ) {
			$career_id = NBY_IT_Options_Career::find_by_slug( $slug );
		}
		if ( $this->submitted_career ) {
			$career_id = $this->submitted_career;
		}
		$requested = $career_id || '' !== $slug;

		$careers_url = $this->career->careers_url();

		// Success screen (one-time token from the redirect).
		if ( isset( $_GET[ self::SUCCESS_PARAM ] ) ) {
			$token      = sanitize_key( wp_unslash( $_GET[ self::SUCCESS_PARAM ] ) );
			$success_id = $token ? absint( get_transient( 'nby_app_ok_' . $token ) ) : 0;
			if ( $success_id ) {
				return NBY_IT_Options_Plugin::template(
					'application-success',
					array(
						'career_title' => get_the_title( $success_id ),
						'careers_url'  => $careers_url,
					)
				);
			}
		}
		// phpcs:enable

		if ( ! $requested ) {
			return NBY_IT_Options_Plugin::template(
				'application-message',
				array(
					'title'       => __( 'Select a Position', 'nby-it-options' ),
					'message'     => __( 'Please select a career position before applying.', 'nby-it-options' ),
					'careers_url' => $careers_url,
				)
			);
		}

		if ( ! NBY_IT_Options_Career::is_open_post( $career_id ) ) {
			return NBY_IT_Options_Plugin::template(
				'application-message',
				array(
					'title'       => __( 'Position Not Available', 'nby-it-options' ),
					'message'     => __( 'This position is not available. Please check our Careers page for other opportunities.', 'nby-it-options' ),
					'careers_url' => $careers_url,
				)
			);
		}

		if ( NBY_IT_Options_Career::is_expired( $career_id ) ) {
			return NBY_IT_Options_Plugin::template(
				'application-message',
				array(
					'title'       => __( 'Applications Closed', 'nby-it-options' ),
					'message'     => __( 'The application deadline for this position has passed. Please check our Careers page for other opportunities.', 'nby-it-options' ),
					'careers_url' => $careers_url,
				)
			);
		}

		$allowed   = $this->career->allowed_resume_types();
		$max_bytes = $this->career->max_resume_bytes();
		$accept    = array();
		foreach ( $allowed as $ext ) {
			$accept[] = '.' . $ext;
			$accept[] = NBY_IT_Options_File_Upload::canonical_mime( $ext );
		}

		$page_id = get_queried_object_id();
		$action  = $page_id ? add_query_arg( 'job', rawurlencode( NBY_IT_Options_Career::slug( $career_id ) ), get_permalink( $page_id ) ) : $this->career->apply_url( $career_id );

		return NBY_IT_Options_Plugin::template(
			'career-application',
			array(
				'job'          => $this->career->get_data( $career_id ),
				'fields'       => self::fields(),
				'errors'       => $this->errors,
				'old'          => $this->old,
				'action'       => $action,
				'accept'       => implode( ',', $accept ),
				'types_label'  => strtoupper( implode( ', ', $allowed ) ),
				'max_bytes'    => $max_bytes,
				'max_label'    => size_format( $max_bytes ),
				'form_action'  => self::ACTION,
				'careers_url'  => $careers_url,
			)
		);
	}
}
