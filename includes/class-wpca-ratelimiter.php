<?php
/**
 * Rate limiter: max one alert per error signature per window (default 10
 * minutes). Repeated hits only bump the occurrences counter, so a flood of
 * identical errors produces exactly one alert with "x100" on it.
 *
 * Time is injectable for tests (self::$now).
 */

defined( 'ABSPATH' ) || exit;

class WPCA_RateLimiter {

	const KEY = 'wpcrash_rate';

	/** @var int|null Injectable clock (unit tests). */
	public static $now = null;

	public static function now() {
		return self::$now ? self::$now + 0 : time();
	}

	private static function load() {
		$data = get_option( self::KEY, array() );
		return is_array( $data ) ? $data : array();
	}

	private static function save( $data ) {
		update_option( self::KEY, $data, false );
	}

	/**
	 * May an alert for this signature be sent right now?
	 */
	public static function can_alert( $signature, $window = 600 ) {
		$data      = self::load();
		$last      = isset( $data[ $signature ] ) ? (int) $data[ $signature ] : 0;
		$within    = ( self::now() - $last ) < $window;

		// Housekeeping: hard-cap the option, keeping only recent signatures.
		if ( count( $data ) > 50 ) {
			asort( $data );
			$data = array_slice( $data, -50, null, true );
			self::save( $data );
		}

		return ! $within;
	}

	/**
	 * Register that an alert for this signature has just been sent.
	 */
	public static function hit( $signature ) {
		$data               = self::load();
		$data[ $signature ] = self::now();
		if ( count( $data ) > 50 ) {
			asort( $data );
			$data = array_slice( $data, -50, null, true );
		}
		self::save( $data );
	}

	/**
	 * Reset everything (tests, settings reset).
	 */
	public static function reset_all() {
		delete_option( self::KEY );
	}
}
