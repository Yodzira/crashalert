<?php
/** Signatures: dedup of identical errors, separation of different ones. */
class SignatureTest extends PHPUnit\Framework\TestCase {

	public function test_same_error_same_signature() {
		$a = WPCA_Signature::make( E_ERROR, '/wp/wp-content/plugins/x/x.php', 42, 'Call to undefined function boom()' );
		$b = WPCA_Signature::make( E_ERROR, '\\wp\\wp-content\\plugins\\x\\x.php', 42, 'Call to undefined function boom()' );
		$this->assertSame( $a, $b ); // path normalization (win/linux separators).
	}

	public function test_different_line_different_signature() {
		$a = WPCA_Signature::make( E_ERROR, '/wp/plugins/x/x.php', 42, 'Boom' );
		$b = WPCA_Signature::make( E_ERROR, '/wp/plugins/x/x.php', 43, 'Boom' );
		$this->assertNotSame( $a, $b );
	}

	public function test_normalize_masks_numbers_and_strings() {
		$this->assertSame(
			WPCA_Signature::normalize( 'Table wp_options doesn\'t exist for user 42' ),
			WPCA_Signature::normalize( 'Table wp_options doesn\'t exist for user 777' )
		);
	}

	public function test_normalize_masks_quoted_strings_and_hex() {
		$m1 = 'Class \'Guzzle\Http\Client\' not found in abcdef1234567890abcd';
		$m2 = 'Class \'Guzzle\Http\Client\' not found in ffff1111222233334444';
		$this->assertSame( WPCA_Signature::normalize( $m1 ), WPCA_Signature::normalize( $m2 ) );
	}

	public function test_multibyte_message_survives() {
		$sig = WPCA_Signature::make( E_ERROR, '/wp/x.php', 1, 'Ошибка в блендере: функция «купить» не найдена' );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}$/', $sig );
	}
}
