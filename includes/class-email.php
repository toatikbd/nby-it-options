<?php
/**
 * Application emails (plain text).
 *
 * The resume is never attached; the admin email links to the capability-protected
 * application screen and download action instead.
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

class NBY_IT_Options_Email {

	/**
	 * @var NBY_IT_Options_Settings
	 */
	private $settings;

	/**
	 * @param NBY_IT_Options_Settings $settings Settings.
	 */
	public function __construct( NBY_IT_Options_Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Recruitment address (setting, falling back to the site admin email).
	 *
	 * @return string
	 */
	public function recruitment_email() {
		$email = sanitize_email( (string) $this->settings->get( 'careers', 'recruitment_email' ) );
		return is_email( $email ) ? $email : get_option( 'admin_email' );
	}

	/**
	 * Remove line breaks from header-bound strings.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function single_line( $value ) {
		return trim( preg_replace( '/[\r\n\t]+/', ' ', wp_strip_all_tags( (string) $value ) ) );
	}

	/**
	 * Notify the recruitment team about a new application.
	 *
	 * @param int $application_id Application id.
	 * @return bool
	 */
	public function send_admin_notification( $application_id ) {
		$get      = static function ( $key ) use ( $application_id ) {
			return NBY_IT_Options_Application::get( $application_id, $key );
		};
		$position = self::single_line( $get( 'career_title' ) );
		$none     = __( 'Not provided', 'nby-it-options' );

		/* translators: %s: position title. */
		$subject = sprintf( __( 'New Career Application — %s', 'nby-it-options' ), $position );

		$lines = array(
			__( 'A new career application has been submitted.', 'nby-it-options' ),
			'',
			__( 'Position:', 'nby-it-options' ),
			$position,
			'',
			__( 'Applicant:', 'nby-it-options' ),
			$get( 'full_name' ),
			'',
			__( 'Email:', 'nby-it-options' ),
			$get( 'email' ),
			'',
			__( 'Phone:', 'nby-it-options' ),
			$get( 'phone' ),
			'',
			__( 'Location:', 'nby-it-options' ),
			$get( 'location' ),
			'',
			__( 'LinkedIn:', 'nby-it-options' ),
			$get( 'linkedin_url' ) ? $get( 'linkedin_url' ) : $none,
			'',
			__( 'Portfolio:', 'nby-it-options' ),
			$get( 'portfolio_url' ) ? $get( 'portfolio_url' ) : $none,
			'',
			__( 'Application Date:', 'nby-it-options' ),
			get_the_date( 'F j, Y', $application_id ),
			'',
			__( 'Status:', 'nby-it-options' ),
			NBY_IT_Options_Application::status_label( NBY_IT_Options_Application::status( $application_id ) ),
			'',
			__( 'View Application:', 'nby-it-options' ),
			NBY_IT_Options_Application::admin_url( $application_id ),
			'',
			__( 'Download Resume (login required):', 'nby-it-options' ),
			NBY_IT_Options_Application::download_url( $application_id ),
		);

		$headers   = array( 'Content-Type: text/plain; charset=UTF-8' );
		$applicant = sanitize_email( $get( 'email' ) );
		if ( is_email( $applicant ) ) {
			$headers[] = 'Reply-To: ' . self::single_line( str_replace( array( '<', '>', '"', ',' ), '', $get( 'full_name' ) ) ) . ' <' . $applicant . '>';
		}

		$mail = apply_filters(
			'nby_it_options_admin_email',
			array(
				'to'      => $this->recruitment_email(),
				'subject' => self::single_line( $subject ),
				'message' => implode( "\n", $lines ),
				'headers' => $headers,
			),
			$application_id
		);

		return wp_mail( $mail['to'], $mail['subject'], $mail['message'], $mail['headers'] );
	}

	/**
	 * Confirmation email to the applicant (if enabled). Contains no internal data.
	 *
	 * @param int $application_id Application id.
	 * @return bool
	 */
	public function send_applicant_confirmation( $application_id ) {
		if ( ! (int) $this->settings->get( 'careers', 'send_confirmation' ) ) {
			return false;
		}

		$to = sanitize_email( NBY_IT_Options_Application::get( $application_id, 'email' ) );
		if ( ! is_email( $to ) ) {
			return false;
		}

		$name     = self::single_line( NBY_IT_Options_Application::get( $application_id, 'full_name' ) );
		$position = self::single_line( NBY_IT_Options_Application::get( $application_id, 'career_title' ) );
		$company  = self::single_line( (string) $this->settings->get( 'general', 'company_name' ) );
		$company  = '' !== $company ? $company : self::single_line( get_bloginfo( 'name' ) );

		/* translators: %s: position title. */
		$subject = sprintf( __( 'Application Received — %s', 'nby-it-options' ), $position );

		$lines = array(
			/* translators: %s: applicant name. */
			sprintf( __( 'Hello %s,', 'nby-it-options' ), $name ),
			'',
			/* translators: %s: position title. */
			sprintf( __( 'Thank you for applying for the %s position.', 'nby-it-options' ), $position ),
			'',
			__( 'We have successfully received your application.', 'nby-it-options' ),
			'',
			__( 'Our recruitment team will review your application and contact you if your profile is shortlisted.', 'nby-it-options' ),
			'',
			__( 'Regards,', 'nby-it-options' ),
			$company,
		);

		$headers   = array( 'Content-Type: text/plain; charset=UTF-8' );
		$reply_to  = $this->recruitment_email();
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$mail = apply_filters(
			'nby_it_options_applicant_email',
			array(
				'to'      => $to,
				'subject' => self::single_line( $subject ),
				'message' => implode( "\n", $lines ),
				'headers' => $headers,
			),
			$application_id
		);

		return wp_mail( $mail['to'], $mail['subject'], $mail['message'], $mail['headers'] );
	}
}
