<?php
/** Rate limiter: one alert per signature per window, injectable clock. */
class RateLimiterTest extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['__wp_options'] = array();
		WPCA_RateLimiter::$now   = 1_000_000;
	}

	protected function tearDown(): void {
		WPCA_RateLimiter::$now = null;
	}

	public function test_first_alert_allowed() {
		$this->assertTrue( WPCA_RateLimiter::can_alert( 'sig-a' ) );
	}

	public function test_second_alert_within_window_blocked() {
		WPCA_RateLimiter::can_alert( 'sig-a' );
		WPCA_RateLimiter::hit( 'sig-a' );
		WPCA_RateLimiter::$now += 300; // +5 min, window is 10 min.
		$this->assertFalse( WPCA_RateLimiter::can_alert( 'sig-a' ) );
	}

	public function test_alert_allowed_after_window() {
		WPCA_RateLimiter::can_alert( 'sig-a' );
		WPCA_RateLimiter::hit( 'sig-a' );
		WPCA_RateLimiter::$now += 601; // +10 min 1 s.
		$this->assertTrue( WPCA_RateLimiter::can_alert( 'sig-a' ) );
	}

	public function test_signatures_independent() {
		WPCA_RateLimiter::hit( 'sig-a' );
		$this->assertTrue( WPCA_RateLimiter::can_alert( 'sig-b' ) );
	}

	public function test_housekeeping_keeps_option_tiny() {
		for ( $i = 0; $i < 60; $i++ ) {
			WPCA_RateLimiter::hit( 'sig-' . $i );
		}
		$stored = get_option( 'wpcrash_rate' );
		$this->assertLessThanOrEqual( 50, count( $stored ) );
	}
}
