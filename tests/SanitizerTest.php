<?php
/** Sanitizer: paths, messages, stacks, URLs. */
class SanitizerTest extends PHPUnit\Framework\TestCase {

	public function test_path_becomes_relative_to_abspath() {
		$this->assertSame(
			'wp-content/plugins/x/x.php',
			WPCA_Sanitizer::path( ABSPATH . 'wp-content/plugins/x/x.php' )
		);
	}

	public function test_message_strips_abspath_and_control_chars() {
		$msg = "Error in " . ABSPATH . "wp-includes/file.php\x00 on line 5";
		$clean = WPCA_Sanitizer::message( $msg );
		$this->assertStringNotContainsString( ABSPATH, $clean );
		$this->assertStringNotContainsString( "\x00", $clean );
	}

	public function test_stack_is_capped_and_has_no_args() {
		$stack = self::deep_call( 30 );
		$frames = json_decode( $stack, true );
		$this->assertIsArray( $frames );
		$this->assertLessThanOrEqual( 15, count( $frames ) );
		foreach ( $frames as $f ) {
			$this->assertArrayNotHasKey( 'args', $f );
		}
	}

	public function test_stack_skips_own_frames() {
		$stack = self::deep_call( 5 );
		$this->assertStringNotContainsString( 'WPCA_', $stack );
	}

	public function test_url_allow_list() {
		$this->assertSame( 'https://example.com/hook', WPCA_Sanitizer::url( 'https://example.com/hook' ) );
		$this->assertSame( '', WPCA_Sanitizer::url( 'file:///etc/passwd' ) );
		$this->assertSame( '', WPCA_Sanitizer::url( 'ftp://example.com' ) );
	}

	private static function deep_call( $depth, $acc = array() ) {
		if ( $depth === 0 ) {
			return WPCA_Sanitizer::stack();
		}
		$acc[] = 'payload-secret-' . $depth; // Would leak into args if args were captured.
		return self::deep_call( $depth - 1, $acc );
	}
}
