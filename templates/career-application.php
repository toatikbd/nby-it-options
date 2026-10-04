<?php
/**
 * Career application form. Override in your theme at nby-it-options/career-application.php.
 *
 * Keep the field names, the NBY_IT_Options_Security::form_fields() call and the
 * hidden nby_action / job inputs when overriding.
 *
 * @var array $vars See NBY_IT_Options_Application_Form::render().
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

$job    = $vars['job'];
$errors = $vars['errors'];
$old    = $vars['old'];

$field_errors = array_diff_key( $errors, array( 'form' => true ) );
$job_meta     = array_filter( array( $job['employment_type'], $job['location'], $job['experience_level'], $job['salary'] ) );
?>
<div class="nby-apply">
	<header class="nby-apply__header">
		<p class="nby-apply__eyebrow"><?php esc_html_e( 'Apply for', 'nby-it-options' ); ?></p>
		<h2 class="nby-apply__title"><?php echo esc_html( $job['title'] ); ?></h2>

		<?php if ( $job_meta ) : ?>
			<ul class="nby-apply__meta">
				<?php foreach ( $job_meta as $item ) : ?>
					<li><?php echo esc_html( $item ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php if ( $job['deadline_display'] ) : ?>
			<p class="nby-apply__deadline">
				<?php
				/* translators: %s: deadline date. */
				echo esc_html( sprintf( __( 'Application deadline: %s', 'nby-it-options' ), $job['deadline_display'] ) );
				?>
			</p>
		<?php endif; ?>
	</header>

	<div class="nby-apply__alert" role="alert" tabindex="-1" data-nby-summary <?php echo $errors ? '' : 'hidden'; ?>>
		<?php if ( ! empty( $errors['form'] ) ) : ?>
			<p><?php echo esc_html( $errors['form'] ); ?></p>
		<?php endif; ?>
		<?php if ( $field_errors ) : ?>
			<p><?php esc_html_e( 'Please correct the following:', 'nby-it-options' ); ?></p>
			<ul>
				<?php foreach ( $field_errors as $key => $message ) : ?>
					<li><a href="#nby-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $message ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>

	<form class="nby-apply__form" method="post" action="<?php echo esc_url( $vars['action'] ); ?>" enctype="multipart/form-data" novalidate data-nby-form>
		<input type="hidden" name="nby_action" value="<?php echo esc_attr( $vars['form_action'] ); ?>" />
		<input type="hidden" name="job" value="<?php echo esc_attr( $job['slug'] ); ?>" />
		<?php NBY_IT_Options_Security::form_fields(); ?>

		<div class="nby-apply__grid">
			<?php foreach ( $vars['fields'] as $key => $field ) : ?>
				<?php
				$id        = 'nby-' . $key;
				$has_error = isset( $errors[ $key ] );
				$value     = isset( $old[ $key ] ) ? $old[ $key ] : '';
				$describe  = $id . '-error';
				$wide      = 'textarea' === $field['type'];
				?>
				<div class="nby-field<?php echo $wide ? ' nby-field--wide' : ''; ?><?php echo $has_error ? ' has-error' : ''; ?>">
					<label for="<?php echo esc_attr( $id ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
						<?php if ( $field['required'] ) : ?>
							<span class="nby-required" aria-hidden="true">*</span>
						<?php endif; ?>
					</label>

					<?php if ( 'textarea' === $field['type'] ) : ?>
						<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>" rows="7" maxlength="<?php echo esc_attr( (string) $field['max'] ); ?>"
							<?php echo $field['required'] ? 'required aria-required="true"' : ''; ?>
							aria-describedby="<?php echo esc_attr( $describe ); ?>"
							<?php echo $has_error ? 'aria-invalid="true"' : ''; ?>><?php echo esc_textarea( $value ); ?></textarea>
					<?php else : ?>
						<input type="<?php echo esc_attr( $field['type'] ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $key ); ?>"
							value="<?php echo esc_attr( $value ); ?>" maxlength="<?php echo esc_attr( (string) $field['max'] ); ?>"
							<?php echo ! empty( $field['autocomplete'] ) ? 'autocomplete="' . esc_attr( $field['autocomplete'] ) . '"' : ''; ?>
							<?php echo ! empty( $field['placeholder'] ) ? 'placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?>
							<?php echo $field['required'] ? 'required aria-required="true"' : ''; ?>
							aria-describedby="<?php echo esc_attr( $describe ); ?>"
							<?php echo $has_error ? 'aria-invalid="true"' : ''; ?> />
					<?php endif; ?>

					<p class="nby-field__error" id="<?php echo esc_attr( $describe ); ?>" data-nby-error><?php echo $has_error ? esc_html( $errors[ $key ] ) : ''; ?></p>
				</div>
			<?php endforeach; ?>

			<?php $resume_error = isset( $errors['resume'] ); ?>
			<div class="nby-field nby-field--wide nby-field--file<?php echo $resume_error ? ' has-error' : ''; ?>">
				<label for="nby-resume">
					<?php esc_html_e( 'Resume / CV', 'nby-it-options' ); ?>
					<span class="nby-required" aria-hidden="true">*</span>
				</label>
				<div class="nby-file">
					<input type="file" id="nby-resume" name="resume" accept="<?php echo esc_attr( $vars['accept'] ); ?>" required aria-required="true"
						aria-describedby="nby-resume-help nby-resume-error" <?php echo $resume_error ? 'aria-invalid="true"' : ''; ?>
						data-max-bytes="<?php echo esc_attr( (string) $vars['max_bytes'] ); ?>" />
				</div>
				<p class="nby-field__help" id="nby-resume-help">
					<?php
					/* translators: 1: allowed types, 2: max size. */
					echo esc_html( sprintf( __( '%1$s — Max %2$s', 'nby-it-options' ), $vars['types_label'], $vars['max_label'] ) );
					?>
				</p>
				<p class="nby-field__error" id="nby-resume-error" data-nby-error><?php echo $resume_error ? esc_html( $errors['resume'] ) : ''; ?></p>
			</div>

			<?php $consent_error = isset( $errors['privacy_consent'] ); ?>
			<div class="nby-field nby-field--wide nby-field--check<?php echo $consent_error ? ' has-error' : ''; ?>">
				<label for="nby-privacy_consent" class="nby-check">
					<input type="checkbox" id="nby-privacy_consent" name="privacy_consent" value="1" required aria-required="true"
						aria-describedby="nby-privacy_consent-error" <?php checked( ! empty( $old['privacy_consent'] ) ); ?>
						<?php echo $consent_error ? 'aria-invalid="true"' : ''; ?> />
					<span><?php esc_html_e( 'I agree to the processing of my information for recruitment purposes.', 'nby-it-options' ); ?> <span class="nby-required" aria-hidden="true">*</span></span>
				</label>
				<p class="nby-field__error" id="nby-privacy_consent-error" data-nby-error><?php echo $consent_error ? esc_html( $errors['privacy_consent'] ) : ''; ?></p>
			</div>
		</div>

		<?php do_action( 'nby_it_options_application_form_before_submit', $job['id'] ); ?>

		<div class="nby-apply__actions">
			<button type="submit" class="nby-btn nby-btn--lg" data-nby-submit><?php esc_html_e( 'Submit Application', 'nby-it-options' ); ?></button>
			<p class="nby-apply__note"><?php esc_html_e( 'Fields marked * are required.', 'nby-it-options' ); ?></p>
		</div>
	</form>
</div>
