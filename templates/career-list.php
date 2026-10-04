<?php
/**
 * Career listing. Override in your theme at nby-it-options/career-list.php.
 *
 * @var array $vars {
 *     @type array[] $jobs       Career data (see NBY_IT_Options_Career::get_data()).
 *     @type int     $offset     Number of jobs on previous pages.
 *     @type string  $pagination Pagination HTML.
 * }
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="nby-careers">
	<?php if ( empty( $vars['jobs'] ) ) : ?>
		<p class="nby-careers__empty"><?php esc_html_e( 'There are no open positions at the moment. Please check back soon.', 'nby-it-options' ); ?></p>
	<?php else : ?>
		<ol class="nby-careers__list" start="<?php echo esc_attr( (string) ( $vars['offset'] + 1 ) ); ?>">
			<?php foreach ( $vars['jobs'] as $index => $job ) : ?>
				<?php $number = str_pad( (string) ( $vars['offset'] + $index + 1 ), 2, '0', STR_PAD_LEFT ); ?>
				<li class="nby-job<?php echo $job['is_expired'] ? ' is-expired' : ''; ?>">
					<div class="nby-job__num" aria-hidden="true"><?php echo esc_html( $number ); ?></div>

					<div class="nby-job__main">
						<?php if ( $job['employment_type'] ) : ?>
							<span class="nby-job__type"><?php echo esc_html( $job['employment_type'] ); ?></span>
						<?php endif; ?>
						<h3 class="nby-job__title">
							<a href="<?php echo esc_url( $job['permalink'] ); ?>"><?php echo esc_html( $job['title'] ); ?></a>
						</h3>
					</div>

					<div class="nby-job__meta">
						<?php if ( $job['location'] ) : ?>
							<span class="nby-job__location">
								<svg class="nby-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a7 7 0 0 0-7 7c0 5.25 7 13 7 13s7-7.75 7-13a7 7 0 0 0-7-7Zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5Z"/></svg>
								<?php echo esc_html( $job['location'] ); ?>
							</span>
						<?php endif; ?>
						<?php if ( $job['deadline_display'] ) : ?>
							<span class="nby-job__deadline">
								<?php
								/* translators: %s: deadline date. */
								echo esc_html( sprintf( __( 'Apply before: %s', 'nby-it-options' ), $job['deadline_display'] ) );
								?>
							</span>
						<?php endif; ?>
					</div>

					<div class="nby-job__meta">
						<?php if ( $job['experience_level'] ) : ?>
							<span class="nby-job__level"><?php echo esc_html( $job['experience_level'] ); ?></span>
						<?php endif; ?>
						<?php if ( $job['salary'] ) : ?>
							<span class="nby-job__salary"><?php echo esc_html( $job['salary'] ); ?></span>
						<?php endif; ?>
					</div>

					<div class="nby-job__action">
						<?php if ( $job['is_expired'] ) : ?>
							<span class="nby-btn nby-btn--disabled" aria-disabled="true"><?php esc_html_e( 'Applications Closed', 'nby-it-options' ); ?></span>
						<?php else : ?>
							<a class="nby-btn text-white" href="<?php echo esc_url( $job['apply_url'] ); ?>">
								<?php esc_html_e( 'Apply', 'nby-it-options' ); ?>
								<span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: job title. */ __( 'for %s', 'nby-it-options' ), $job['title'] ) ); ?></span>
								<svg class="nby-icon" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M13.2 5.3 19.9 12l-6.7 6.7-1.4-1.4 4.3-4.3H4v-2h12.1l-4.3-4.3 1.4-1.4Z"/></svg>
							</a>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>

		<?php if ( $vars['pagination'] ) : ?>
			<nav class="nby-careers__pagination" aria-label="<?php esc_attr_e( 'Careers pagination', 'nby-it-options' ); ?>">
				<?php echo wp_kses_post( $vars['pagination'] ); ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>
</div>
