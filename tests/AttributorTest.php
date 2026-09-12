<?php
/** Attribution: plugin folders (incl. subfolders), mu-plugins, themes, core, unknown. */
class AttributorTest extends PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		$GLOBALS['__wp_options'] = array();
	}

	public function test_plugin_in_root_folder() {
		update_option( WPCA_Attributor::CACHE_KEY, array(
			'super-slider' => array( 'Name' => 'Super Slider', 'Version' => '2.1' ),
		) );
		$a = WPCA_Attributor::attribute( '/wp/wp-content/plugins/super-slider/slider.php' );
		$this->assertSame( 'plugin', $a['kind'] );
		$this->assertSame( 'Super Slider v2.1', $a['name'] );
		$this->assertStringContainsString( 'slider.php', $a['detail'] );
	}

	public function test_plugin_in_subfolder_vendor_path() {
		update_option( WPCA_Attributor::CACHE_KEY, array(
			'big-plugin' => array( 'Name' => 'Big Plugin', 'Version' => '3.0' ),
		) );
		$a = WPCA_Attributor::attribute( '/wp/wp-content/plugins/big-plugin/vendor/guzzle/http.php' );
		$this->assertSame( 'plugin', $a['kind'] );
		$this->assertSame( 'Big Plugin v3.0', $a['name'] );
		$this->assertStringContainsString( 'vendor/guzzle/http.php', $a['detail'] );
	}

	public function test_mu_plugin() {
		$a = WPCA_Attributor::attribute( '/wp/wp-content/mu-plugins/must-have.php' );
		$this->assertSame( 'mu-plugin', $a['kind'] );
		$this->assertSame( 'must-have.php', $a['name'] );
	}

	public function test_core_file() {
		$a = WPCA_Attributor::attribute( '/wp/wp-includes/functions.php' );
		$this->assertSame( 'core', $a['kind'] );
		$this->assertSame( 'WordPress core', $a['name'] );
	}

	public function test_unknown_path_outside_abspath() {
		$a = WPCA_Attributor::attribute( '/var/www/somewhere/else.php' );
		$this->assertSame( 'unknown', $a['kind'] );
		$this->assertSame( 'else.php', $a['name'] );
	}

	public function test_theme_falls_back_when_theme_object_missing() {
		// WPCA_Fake_Theme::exists() is false → attribution degrades to core/unknown,
		// never to a wrong name.
		$a = WPCA_Attributor::attribute( '/wp/wp-content/themes/kid/functions.php' );
		$this->assertContains( $a['kind'], array( 'core', 'unknown' ) );
	}
}
