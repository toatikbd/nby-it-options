<?php
/**
 * Two-column job page: other open jobs (left), job details + Apply Now (right).
 * Override in your theme at nby-it-options/career-single.php.
 *
 * @var array $vars {
 *     @type array   $job         Current job data (see NBY_IT_Options_Career::get_data()).
 *     @type string  $content     Rendered job description (HTML, already filtered).
 *     @type string  $image       Featured image HTML or ''.
 *     @type array[] $other_jobs  Other open jobs.
 *     @type string  $careers_url Careers page URL.
 *     @type bool    $can_apply   Whether applications are open.
 * }
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

$job  = $vars['job'];
$meta = array_filter(
	array(
		__( 'Location', 'nby-it-options' )             => $job['location'],
		__( 'Experience', 'nby-it-options' )           => $job['experience_level'],
		__( 'Salary', 'nby-it-options' )               => $job['salary'],
		__( 'Application Deadline', 'nby-it-options' ) => $job['deadline_display'],
	)
);

$apply_button = static function () use ( $job, $vars ) {
	if ( $vars['can_apply'] ) {
		printf(
			'<a class="nby-btn nby-btn--lg text-white" href="%1$s">%2$s<span class="screen-reader-text"> %3$s</span><svg class="nby-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.2 5.3 19.9 12l-6.7 6.7-1.4-1.4 4.3-4.3H4v-2h12.1l-4.3-4.3 1.4-1.4Z"/></svg></a>',
			esc_url( $job['apply_url'] ),
			esc_html__( 'Apply Now', 'nby-it-options' ),
			/* translators: %s: job title. */
			esc_html( sprintf( __( 'for %s', 'nby-it-options' ), $job['title'] ) )
		);
	} else {
		printf( '<span class="nby-btn nby-btn--lg nby-btn--disabled" aria-disabled="true">%s</span>', esc_html__( 'Applications Closed', 'nby-it-options' ) );
	}
};
?>
<div class="nby-careers nby-single pt-120">
	<aside class="nby-single__sidebar" aria-labelledby="nby-other-jobs-title">
		<div class="nby-single__sidebar-inner">
			<h3 class="nby-single__sidebar-title" id="nby-other-jobs-title"><?php esc_html_e( 'Other Open Positions', 'nby-it-options' ); ?></h3>

			<?php if ( $vars['other_jobs'] ) : ?>
				<ul class="nby-other-jobs">
					<?php foreach ( $vars['other_jobs'] as $other ) : ?>
						<li>
							<a class="nby-other-job" href="<?php echo esc_url( $other['permalink'] ); ?>">
								<?php if ( $other['employment_type'] ) : ?>
									<span class="nby-other-job__type"><?php echo esc_html( $other['employment_type'] ); ?></span>
								<?php endif; ?>
								<span class="nby-other-job__title"><?php echo esc_html( $other['title'] ); ?></span>
								<?php
								$other_meta = array_filter(
									array(
										$other['location'],
										$other['deadline_display'] ? sprintf( /* translators: %s: date. */ __( 'Apply before %s', 'nby-it-options' ), $other['deadline_display'] ) : '',
									)
								);
								?>
								<?php if ( $other_meta ) : ?>
									<span class="nby-other-job__meta"><?php echo esc_html( implode( ' · ', $other_meta ) ); ?></span>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="nby-single__empty"><?php esc_html_e( 'There are no other open positions right now.', 'nby-it-options' ); ?></p>
			<?php endif; ?>

			<a class="nby-single__all" href="<?php echo esc_url( $vars['careers_url'] ); ?>"><?php esc_html_e( 'View all careers', 'nby-it-options' ); ?> &rarr;</a>
		</div>
	</aside>

	<article class="nby-single__main">
		<header class="nby-single__header">
			<?php if ( $job['employment_type'] ) : ?>
				<span class="nby-job__type"><?php echo esc_html( $job['employment_type'] ); ?></span>
			<?php endif; ?>
			<h1 class="nby-single__title"><?php echo esc_html( $job['title'] ); ?></h1>

			<?php if ( $meta ) : ?>
				<dl class="nby-single__meta">
					<?php foreach ( $meta as $label => $value ) : ?>
						<div>
							<dt><?php echo esc_html( $label ); ?></dt>
							<dd><?php echo esc_html( $value ); ?></dd>
						</div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>

			<div class="nby-single__cta"><?php $apply_button(); ?></div>
			<?php if ( $job['is_expired'] ) : ?>
				<p class="nby-single__closed"><?php esc_html_e( 'The application deadline for this position has passed.', 'nby-it-options' ); ?></p>
			<?php endif; ?>
		</header>

		<?php if ( $vars['image'] ) : ?>
			<div class="nby-single__media"><?php echo $vars['image']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-generated image markup. ?></div>
		<?php endif; ?>

		<?php if ( '' !== trim( wp_strip_all_tags( $vars['content'] ) ) || false !== strpos( $vars['content'], '<img' ) ) : ?>
			<div class="nby-single__content entry-content">
				<?php echo $vars['content']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- post content already filtered by the_content. ?>
			</div>
		<?php endif; ?>

		<?php if ( $vars['can_apply'] ) : ?>
			<footer class="nby-single__footer">
				<p><?php esc_html_e( 'Interested in this role?', 'nby-it-options' ); ?></p>
				<?php $apply_button(); ?>
			</footer>
		<?php endif; ?>
	</article>
</div>
