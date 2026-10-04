<?php
/**
 * Informational screen (no job selected, unavailable job, applications closed).
 * Override in your theme at nby-it-options/application-message.php.
 *
 * @var array $vars { @type string $title, @type string $message, @type string $careers_url }
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="nby-apply nby-apply--message">
	<h2 class="nby-apply__title"><?php echo esc_html( $vars['title'] ); ?></h2>
	<p class="nby-apply__lead"><?php echo esc_html( $vars['message'] ); ?></p>
	<p class="nby-apply__actions">
		<a class="nby-btn" href="<?php echo esc_url( $vars['careers_url'] ); ?>"><?php esc_html_e( 'View Career Opportunities', 'nby-it-options' ); ?></a>
	</p>
</div>
