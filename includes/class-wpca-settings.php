<?php
/**
 * Settings: a single option array with defaults and sanitization.
 * Secrets (Telegram token) are stored as-is in the DB — never logged,
 * never exported, never shown in full in the admin.
 */

defined( 'ABSPATH' ) || exit;

class WPCA_Settings {

	const KEY = 'wpcrash_settings';

	public static function defaults() {
		return array(
			'enabled'         => 1,      // master switch
			'telegram_token'  => '',
			'telegram_chat'   => '',
			'email_enabled'   => 1,
			'email_address'   => '',     // '' = admin_email
			'webhook_url'     => '',
			'rate_window'     => 600,    // seconds per signature
			'retention_days'  => 60,
		);
	}

	public static function all() {
		$settings = get_option( self::KEY, array() );
		return wp_parse_args( is_array( $settings ) ? $settings : array(), self::defaults() );
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sanitize a settings array (from the admin form).
	 *
	 * @return array Clean settings.
	 */
	public static function sanitize( $input ) {
		$clean                = self::defaults();
		$input                = is_array( $input ) ? $input : array();

		$clean['enabled']        = empty( $input['enabled'] ) ? 0 : 1;
		$clean['telegram_token'] = self::sanitize_token( isset( $input['telegram_token'] ) ? $input['telegram_token'] : '' );
		$clean['telegram_chat']  = self::sanitize_chat( isset( $input['telegram_chat'] ) ? $input['telegram_chat'] : '' );
		$clean['email_enabled']  = empty( $input['email_enabled'] ) ? 0 : 1;
		$clean['email_address']  = isset( $input['email_address'] ) ? sanitize_email( $input['email_address'] ) : '';
		$clean['webhook_url']    = WPCA_Sanitizer::url( isset( $input['webhook_url'] ) ? $input['webhook_url'] : '' );

		$rate = isset( $input['rate_window'] ) ? (int) $input['rate_window'] : 600;
		$clean['rate_window'] = min( 3600, max( 60, $rate ) );

		$ret = isset( $input['retention_days'] ) ? (int) $input['retention_days'] : 60;
		$clean['retention_days'] = min( 365, max( 7, $ret ) );

		return $clean;
	}

	/**
	 * Bot tokens look like 1234567890:AA...-33 chars.
	 * Keep the format strict; drop anything else.
	 */
	private static function sanitize_token( $token ) {
		$token = trim( (string) $token );
		// Masked value from the admin form ("...abc") keeps the stored one.
		if ( '' === $token || 0 === strpos( $token, '••••' ) ) {
			return self::get( 'telegram_token' );
		}
		return preg_match( '/^\d+:[A-Za-z0-9_\-]{30,}$/', $token ) ? $token : '';
	}

	private static function sanitize_chat( $chat ) {
		$chat = trim( (string) $chat );
		// Numeric chat id or @channelname.
		if ( preg_match( '/^@[\w\-]{3,}$/', $chat ) || preg_match( '/^\-?\d+$/', $chat ) ) {
			return $chat;
		}
		return self::get( 'telegram_chat' );
	}
}
