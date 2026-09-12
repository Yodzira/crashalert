<?php
/** Settings sanitization: tokens, chats, clamps, webhook scheme. */
class SettingsTest extends PHPUnit\Framework\TestCase {

	public function test_defaults_when_nothing_stored() {
		$s = WPCA_Settings::all();
		$this->assertSame( 1, $s['enabled'] );
		$this->assertSame( 600, $s['rate_window'] );
		$this->assertSame( 60, $s['retention_days'] );
	}

	public function test_valid_telegram_token_accepted_and_stored() {
		update_option( 'wpcrash_settings', WPCA_Settings::defaults() );
		$clean = WPCA_Settings::sanitize( array(
			'telegram_token' => '123456789:AAEBOvT8nQ2mKpXyZzTokenTokenToken12',
			'telegram_chat'  => '@mychannel',
			'enabled'        => 1,
		) );
		$this->assertSame( '123456789:AAEBOvT8nQ2mKpXyZzTokenTokenToken12', $clean['telegram_token'] );
		$this->assertSame( '@mychannel', $clean['telegram_chat'] );
	}

	public function test_garbage_token_dropped() {
		update_option( 'wpcrash_settings', WPCA_Settings::defaults() );
		$clean = WPCA_Settings::sanitize( array( 'telegram_token' => 'drop table users; --' ) );
		$this->assertSame( '', $clean['telegram_token'] );
	}

	public function test_masked_token_keeps_stored_value() {
		update_option( 'wpcrash_settings', array_merge( WPCA_Settings::defaults(), array(
			'telegram_token' => '123456789:AAEBOvT8nQ2mKpXyZzTokenTokenToken12',
		) ) );
		$clean = WPCA_Settings::sanitize( array( 'telegram_token' => '••••n12' ) );
		$this->assertSame( '123456789:AAEBOvT8nQ2mKpXyZzTokenTokenToken12', $clean['telegram_token'] );
	}

	public function test_rate_and_retention_clamped() {
		update_option( 'wpcrash_settings', WPCA_Settings::defaults() );
		$clean = WPCA_Settings::sanitize( array( 'rate_window' => 5, 'retention_days' => 9999 ) );
		$this->assertSame( 60, $clean['rate_window'] );
		$this->assertSame( 365, $clean['retention_days'] );
	}

	public function test_webhook_file_scheme_rejected() {
		update_option( 'wpcrash_settings', WPCA_Settings::defaults() );
		$clean = WPCA_Settings::sanitize( array( 'webhook_url' => 'file:///etc/passwd' ) );
		$this->assertSame( '', $clean['webhook_url'] );
	}
}
