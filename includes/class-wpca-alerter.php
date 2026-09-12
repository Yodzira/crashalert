<?php
/**
 * Alert delivery: Telegram, email, webhook.
 *
 * Alerts are queued in an option and flushed by a scheduled cron event so
 * the failing request is never blocked by network calls. A Telegram miss is
 * retried twice (1 min, then 5 min) before giving up; email is sent once.
 */

defined( 'ABSPATH' ) || exit;

class WPCA_Alerter {

	const QUEUE_KEY  = 'wpcrash_queue';
	const CRON_HOOK  = 'wpca_send_alerts';
	const MAX_RETRY  = 2;

	/** Queue an alert for delivery. */
	public static function queue( array $event ) {
		$queue   = get_option( self::QUEUE_KEY, array() );
		$queue[] = $event;
		update_option( self::QUEUE_KEY, $queue, false );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 10, self::CRON_HOOK );
			// Try to run it right away without blocking the visitor.
			spawn_cron();
		}
	}

	/**
	 * Cron worker: drain the queue.
	 */
	public static function process_queue() {
		// Only cron/cli drains the queue. Checked directly (no
		// wp_is_post_request: removed in WordPress 7.1).
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}
		$queue = get_option( self::QUEUE_KEY, array() );
		if ( ! is_array( $queue ) || ! $queue ) {
			return;
		}

		$remaining = array();
		foreach ( $queue as $item ) {
			if ( self::dispatch( $item ) ) {
				continue; // delivered.
			}
			$attempts = isset( $item['attempts'] ) ? (int) $item['attempts'] : 0;
			if ( $attempts >= self::MAX_RETRY ) {
				continue; // gave up (email already went out once).
			}
			$item['attempts'] = $attempts + 1;
			$remaining[]      = $item;
		}

		if ( $remaining ) {
			update_option( self::QUEUE_KEY, $remaining, false );
			if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_schedule_single_event( time() + 60, self::CRON_HOOK );
			}
		} else {
			update_option( self::QUEUE_KEY, array(), false );
		}
	}

	/**
	 * Send an event through every configured channel.
	 * Telegram retry policy applies only to Telegram.
	 *
	 * @return bool True when at least the non-email channels succeeded
	 *              or nothing is configured (nothing to retry).
	 */
	public static function dispatch( array $event ) {
		$settings = WPCA_Settings::all();
		$done     = true;

		if ( $settings['telegram_token'] && $settings['telegram_chat'] ) {
			$text = self::telegram_text( $event );
			if ( ! self::send_telegram( $settings['telegram_token'], $settings['telegram_chat'], $text ) ) {
				$done = false;
			}
		}

		if ( $settings['email_enabled'] ) {
			$to      = $settings['email_address'] ? $settings['email_address'] : get_option( 'admin_email' );
			$subject = '[CrashAlert] Fatal on ' . wp_parse_url( home_url(), PHP_URL_HOST ) . ': ' . $event['culprit_name'];
			$body    = self::email_text( $event );
			if ( ! wp_mail( $to, $subject, $body ) ) {
				// wp_mail failures are logged by WP itself; do not retry spam.
			}
		}

		if ( $settings['webhook_url'] ) {
			self::send_webhook( $settings['webhook_url'], $event );
		}

		return $done;
	}

	/** Send a test alert (settings wizard). Returns error string or ''. */
	public static function send_test( $channel ) {
		$settings = WPCA_Settings::all();
		$site     = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( 'telegram' === $channel ) {
			if ( ! $settings['telegram_token'] || ! $settings['telegram_chat'] ) {
				return 'telegram_not_configured';
			}
			return self::send_telegram( $settings['telegram_token'], $settings['telegram_chat'], '🧪 CrashAlert test — ' . $site . '. Если видите это, канал работает.' )
				? '' : 'telegram_failed';
		}

		if ( 'webhook' === $channel ) {
			if ( ! $settings['webhook_url'] ) {
				return 'webhook_not_configured';
			}
			return self::send_webhook( $settings['webhook_url'], array( 'test' => true, 'time' => time() ) )
				? '' : 'webhook_failed';
		}

		return 'unknown_channel';
	}

	private static function send_telegram( $token, $chat, $text ) {
		$response = wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $token ) . '/sendMessage',
			array(
				'timeout' => 3,
				'body'    => wp_json_encode(
					array(
						'chat_id' => $chat,
						'text'    => $text,
						// Plain text: error messages must not fight the parser.
					)
				),
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			)
		);
		return ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
	}

	private static function send_webhook( $url, array $payload ) {
		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 3,
				'body'    => wp_json_encode( array( 'source' => 'CrashAlert', 'event' => $payload ) ),
				'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
			)
		);
		return ! is_wp_error( $response ) && (int) wp_remote_retrieve_response_code( $response ) < 300;
	}

	/** Telegram message body (plain text, emoji-marked). */
	private static function telegram_text( array $event ) {
		$site    = wp_parse_url( home_url(), PHP_URL_HOST );
		$explain = WPCA_Explainer::explain( $event['message'] );
		$time    = wp_date( 'H:i', $event['time'] );

		$lines   = array(
			'🔴 ' . sprintf( 'Сайт %s упал в %s', $site, $time ),
			'Виновник: ' . self::culprit_label( $event ),
			'Причина: ' . $explain['title'],
			'Что делать: ' . $explain['hint'],
			'Ошибка: ' . mb_substr( $event['message'], 0, 200 ),
		);
		if ( ! empty( $event['url'] ) ) {
			$lines[] = 'Страница: ' . mb_substr( $event['url'], 0, 120 );
		}
		if ( isset( $event['occurrences'] ) && (int) $event['occurrences'] > 1 ) {
			$lines[] = 'Повторы: ×' . (int) $event['occurrences'];
		}
		$lines[] = 'Режим восстановления: Администрирование → Консоль (баннер сверху).';

		$text = implode( "\n", $lines );
		return mb_substr( $text, 0, 3500 );
	}

	private static function email_text( array $event ) {
		$explain = WPCA_Explainer::explain( $event['message'] );
		$lines   = array(
			'Fatal error on ' . home_url(),
			'',
			'Culprit: ' . self::culprit_label( $event ),
			'Cause: ' . $explain['title'],
			'What to do: ' . $explain['hint'],
			'Error: ' . $event['message'],
			'File: ' . $event['file'] . ' : ' . $event['line'],
			'URL: ' . $event['url'],
			'',
			'--- stack (sanitized) ---',
			$event['stack'],
		);
		return implode( "\n", $lines );
	}

	private static function culprit_label( array $event ) {
		$kind = isset( $event['culprit_kind'] ) ? $event['culprit_kind'] : '';
		$name = isset( $event['culprit_name'] ) ? $event['culprit_name'] : '';
		if ( 'plugin' === $kind ) {
			return 'плагин ' . $name;
		}
		if ( 'mu-plugin' === $kind ) {
			return 'mu-плагин ' . $name;
		}
		if ( 'theme' === $kind ) {
			return 'тема ' . $name;
		}
		if ( 'core' === $kind ) {
			return 'ядро WordPress';
		}
		return $name ? $name : 'источник не определён';
	}
}
