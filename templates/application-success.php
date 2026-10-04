<?php
/**
 * Application success screen. Override in your theme at nby-it-options/application-success.php.
 *
 * @var array $vars { @type string $career_title, @type string $careers_url }
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="nby-apply nby-apply--message nby-apply--success" role="status">
	<div class="nby-apply__icon" aria-hidden="true">
		<svg viewBox="0 0 24 24" width="32" height="32" focusable="false"><path fill="currentColor" d="M9.55 17.6 4.4 12.45l1.4-1.4 3.75 3.75 8.65-8.65 1.4 1.4L9.55 17.6Z"/></svg>
	</div>
	<h2 class="nby-apply__title"><?php esc_html_e( 'Application Submitted Successfully', 'nby-it-options' ); ?></h2>
	<p class="nby-apply__lead">
		<?php
		/* translators: %s: position title. */
		echo esc_html( sprintf( __( 'Thank you for applying for %s.', 'nby-it-options' ), $vars['career_title'] ) );
		?>
	</p>
	<p><?php esc_html_e( 'Your application has been received successfully.', 'nby-it-options' ); ?></p>
	<p><?php esc_html_e( 'Our recruitment team will review your application and contact you if you are shortlisted.', 'nby-it-options' ); ?></p>
	<p class="nby-apply__actions">
		<a class="nby-btn" href="<?php echo esc_url( $vars['careers_url'] ); ?>"><?php esc_html_e( 'View Career Opportunities', 'nby-it-options' ); ?></a>
	</p>
</div>
