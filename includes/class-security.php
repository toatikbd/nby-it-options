<?php
/**
 * Anti-spam and request security helpers for public forms.
 *
 * Checks run in order: nonce, honeypot, minimum fill time, rate limit, then the
 * `nby_it_options_application_spam_check` filter. A CAPTCHA provider (reCAPTCHA,
 * Turnstile...) can be added later by printing its widget on the
 * `nby_it_options_application_form_before_submit` action and verifying the token
 * in that filter, without changing the application system.
 *
 * @package NBY_IT_Options
 */

defined( 'ABSPATH' ) || exit;

class NBY_IT_Options_Security {

	const NONCE_ACTION   = 'nby_career_application_submit';
	const NONCE_FIELD    = 'nby_application_nonce';
	const HONEYPOT_FIELD = 'website';
	const TIMER_FIELD    = 'nby_form_ts';

	/**
	 * Hidden fields to print inside the form.
	 */
	public static function form_fields() {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );

		$time = (string) time();
		printf(
			'<input type="hidden" name="%1$s" value="%2$s" />',
			esc_attr( self::TIMER_FIELD ),
			esc_attr( $time . '|' . wp_hash( 'nby_form_ts|' . $time ) )
		);

		// Honeypot: visually hidden, skipped by assistive tech and autofill.
		printf(
			'<div class="nby-hp" aria-hidden="true"><label for="nby-%1$s">%2$s</label><input type="text" id="nby-%1$s" name="%1$s" value="" tabindex="-1" autocomplete="off" /></div>',
			esc_attr( self::HONEYPOT_FIELD ),
			esc_html__( 'Leave this field empty', 'nby-it-options' )
		);
	}

	/**
	 * Run all anti-spam checks on the current POST request.
	 *
	 * @return true|WP_Error
	 */
	public static function verify_submission() {
		$generic = new WP_Error(
			'nby_security',
			__( 'Your submission could not be verified. Please reload the page and try again.', 'nby-it-options' )
		);

		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return $generic;
		}

		// Honeypot must be empty.
		if ( ! empty( $_POST[ self::HONEYPOT_FIELD ] ) ) {
			return $generic;
		}

		// Signed timestamp: reject tampering and inhumanly fast submissions.
		$stamp = isset( $_POST[ self::TIMER_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::TIMER_FIELD ] ) ) : '';
		$parts = explode( '|', $stamp );
		if ( 2 !== count( $parts ) || ! hash_equals( wp_hash( 'nby_form_ts|' . $parts[0] ), $parts[1] ) ) {
			return $generic;
		}
		$min_seconds = (int) apply_filters( 'nby_it_options_min_fill_seconds', 3 );
		if ( time() - (int) $parts[0] < $min_seconds ) {
			return $generic;
		}

		if ( self::is_rate_limited() ) {
			return new WP_Error(
				'nby_rate_limited',
				__( 'Too many applications have been submitted from your connection. Please try again later.', 'nby-it-options' )
			);
		}

		/**
		 * Extra verification (e.g. CAPTCHA). Return a WP_Error to reject.
		 *
		 * @param true|WP_Error $result Current result.
		 */
		$result = apply_filters( 'nby_it_options_application_spam_check', true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}

	/**
	 * Visitor IP. Only REMOTE_ADDR is trusted; proxies can be supported via the filter.
	 *
	 * @return string
	 */
	public static function client_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = (string) apply_filters( 'nby_it_options_client_ip', $ip );
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	/**
	 * Transient key for the current visitor (the IP is hashed, never stored raw).
	 *
	 * @return string
	 */
	private static function rate_key() {
		return 'nby_app_rl_' . substr( wp_hash( self::client_ip() ), 0, 20 );
	}

	/**
	 * Whether the visitor exceeded the submission limit.
	 *
	 * @return bool
	 */
	public static function is_rate_limited() {
		$limit = (int) apply_filters( 'nby_it_options_rate_limit', 5 );
		if ( $limit <= 0 ) {
			return false;
		}
		return (int) get_transient( self::rate_key() ) >= $limit;
	}

	/**
	 * Count a successful submission against the visitor's limit.
	 */
	public static function record_submission() {
		$key   = self::rate_key();
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, (int) apply_filters( 'nby_it_options_rate_window', HOUR_IN_SECONDS ) );
	}
}
