<?php
/**
 * Shutdown catcher: the earliest and most guarded layer.
 *
 * Registered at plugin file include time. On every request it checks the
 * last PHP error; if it is a fatal, the request is attributed, stored and
 * an alert is queued. On healthy requests it does nothing except maintain
 * the "site is alive" streak used for recovery notices.
 *
 * Design rule (gate G0): this class must never raise a fatal itself.
 * Every step is wrapped so the worst case is a silently missed event,
 * never a broken site.
 */

defined( 'ABSPATH' ) || exit;

class WPCA_Shutdown {

	/** @var bool Guards against double registration (mu-loader + normal). */
	private static $registered = false;

	public static function register() {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		register_shutdown_function( array( __CLASS__, 'handle' ) );
	}

	/**
	 * Shutdown entry point. Runs on every request.
	 * Keep the hot path as cheap as possible.
	 */
	public static function handle() {
		try {
			$e = function_exists( 'error_get_last' ) ? error_get_last() : null;

			if ( ! self::is_fatal( $e ) ) {
				self::tick_alive();
				return;
			}

			self::process_fatal( $e );
		} catch ( \Throwable $t ) { // phpcs:ignore
			// Our own failure must stay invisible to the visitor, but must be
			// visible to developers: log it when WP debug logging is on.
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG && function_exists( 'error_log' ) ) {
				error_log( 'CrashAlert shutdown: ' . $t->getMessage() ); // phpcs:ignore
			}
		}
	}

	/**
	 * Fatal types worth alerting about. Warnings/notices never trigger.
	 */
	public static function is_fatal( $e ) {
		if ( ! is_array( $e ) ) {
			return false;
		}
		$types = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR );
		return in_array( (int) $e['type'], $types, true );
	}

	/**
	 * Healthy request: maintain the alive-streak used by recovery notices.
	 * Writes to the DB only while the site is in "down" state (rare).
	 */
	private static function tick_alive() {
		if ( ! function_exists( 'get_option' ) || ! function_exists( 'update_option' ) ) {
			return;
		}
		$down = get_option( 'wpcrash_down' );
		if ( ! is_array( $down ) || empty( $down['since'] ) ) {
			return; // Site is fine, nothing to do — zero writes.
		}

		$down['ok_streak'] = ( isset( $down['ok_streak'] ) ? (int) $down['ok_streak'] : 0 ) + 1;

		if ( $down['ok_streak'] >= 2 ) {
			// Two clean requests in a row: the site has recovered.
			$down['recovered_at'] = time();
			update_option( 'wpcrash_down', array(), true );
			update_option( 'wpcrash_recovery', $down, true );
			do_action( 'wpca_site_recovered', $down );
		} else {
			update_option( 'wpcrash_down', $down, true );
		}
	}

	/**
	 * Process a fatal error: attribute, sanitize, store, queue alert.
	 */
	private static function process_fatal( $e ) {
		$e['file']  = isset( $e['file'] ) ? (string) $e['file'] : '';
		$e['line']  = isset( $e['line'] ) ? (int) $e['line'] : 0;
		$e['message'] = isset( $e['message'] ) ? (string) $e['message'] : '';

		// Attribute the culprit even if the heavy parts are unavailable.
		$culprit = array( 'kind' => 'unknown', 'name' => '', 'detail' => '' );
		if ( class_exists( 'WPCA_Attributor' ) ) {
			try {
				$culprit = WPCA_Attributor::attribute( $e['file'] );
			} catch ( \Throwable $t ) { // phpcs:ignore
			}
		}

		$sig = class_exists( 'WPCA_Signature' )
			? WPCA_Signature::make( $e['type'], $e['file'], $e['line'], $e['message'] )
			: md5( $e['type'] . '|' . $e['file'] . '|' . $e['line'] );

		$event = array(
			'signature'   => $sig,
			'type'        => (int) $e['type'],
			'message'     => class_exists( 'WPCA_Sanitizer' ) ? WPCA_Sanitizer::message( $e['message'] ) : $e['message'],
			'file'        => class_exists( 'WPCA_Sanitizer' ) ? WPCA_Sanitizer::path( $e['file'] ) : $e['file'],
			'line'        => $e['line'],
			'culprit_kind' => $culprit['kind'],
			'culprit_name' => $culprit['name'],
			'culprit_detail' => $culprit['detail'],
			'url'         => isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '',
			'stack'       => class_exists( 'WPCA_Sanitizer' ) ? WPCA_Sanitizer::stack() : '',
			'context'     => class_exists( 'WPCA_Correlator' ) ? wp_json_encode( WPCA_Correlator::changes_last_24h() ) : '',
			'time'        => time(),
			'occurrences' => 1,
		);

		// Mark the site as down and remember the event signature.
		if ( function_exists( 'update_option' ) ) {
			update_option(
				'wpcrash_down',
				array(
					'since'     => $event['time'],
					'signature' => $sig,
					'culprit'   => $culprit['name'],
					'ok_streak' => 0,
				),
				true
			);
		}

		$stored_id = 0;
		$occurrences = 1;
		if ( class_exists( 'WPCA_Repository' ) ) {
			try {
				$stored_id   = WPCA_Repository::add( $event );
				$occurrences = WPCA_Repository::occurrences_of( $sig );
			} catch ( \Throwable $t ) { // phpcs:ignore
				$stored_id = 0;
			}
		}

		if ( ! $stored_id ) {
			self::fallback_file( $event );
		}

		// Queue the alert (rate-limited per signature).
		if ( class_exists( 'WPCA_RateLimiter' ) && class_exists( 'WPCA_Alerter' ) ) {
			if ( WPCA_RateLimiter::can_alert( $sig ) ) {
				WPCA_RateLimiter::hit( $sig );
				$event['occurrences'] = $occurrences;
				WPCA_Alerter::queue( $event );
			}
		}
	}

	/**
	 * DB-unavailable fallback: write the last fatal into an upload folder
	 * protected by .htaccess so nothing is lost while the DB is down.
	 */
	private static function fallback_file( $event ) {
		if ( ! defined( 'WP_CONTENT_DIR' ) || ! function_exists( 'wp_upload_dir' ) || ! function_exists( 'wp_mkdir_p' ) ) {
			return;
		}
		try {
			$up = wp_upload_dir();
			if ( empty( $up['basedir'] ) ) {
				return;
			}
			$dir = rtrim( $up['basedir'], '/\\' ) . '/crashalert';
			if ( ! is_dir( $dir ) ) {
				wp_mkdir_p( $dir );
				@file_put_contents( $dir . '/.htaccess', "Deny from all\n" ); // phpcs:ignore
				@file_put_contents( $dir . '/index.php', "<?php // Silence is golden.\n" ); // phpcs:ignore
			}
			$event['stored_via'] = 'file-fallback';
			@file_put_contents( $dir . '/last.fatal.json', wp_json_encode( $event ) ); // phpcs:ignore
		} catch ( \Throwable $t ) { // phpcs:ignore
		}
	}
}
