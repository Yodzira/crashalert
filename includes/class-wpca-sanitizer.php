<?php
/**
 * Sanitizer: paths and stacks must never leak server details or user data.
 * Absolute paths become relative to the WP root; stack arguments are
 * stripped; the stack is capped at 15 frames.
 */

defined( 'ABSPATH' ) || exit;

class WPCA_Sanitizer {

	const MAX_FRAMES = 15;

	/** Make an absolute path relative to ABSPATH when possible. */
	public static function path( $path ) {
		$path = wp_normalize_path( (string) $path );
		if ( defined( 'ABSPATH' ) ) {
			$root = wp_normalize_path( ABSPATH );
			if ( $root && 0 === strpos( $path, $root ) ) {
				return ltrim( substr( $path, strlen( $root ) ), '/' );
			}
		}
		return $path;
	}

	/**
	 * Error message cleanup: absolute paths → relative, control chars removed.
	 */
	public static function message( $message ) {
		$message = (string) $message;
		if ( defined( 'ABSPATH' ) ) {
			$message = str_replace( wp_normalize_path( ABSPATH ), '', $message );
			$message = str_replace( str_replace( '/', '\\', wp_normalize_path( ABSPATH ) ), '', $message );
		}
		return trim( preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $message ) );
	}

	/**
	 * Capture the current backtrace, sanitized:
	 * - relative paths,
	 * - call arguments removed (they may contain user data),
	 * - max 15 frames.
	 *
	 * @return string JSON-encoded stack or '' on failure.
	 */
	public static function stack() {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, self::MAX_FRAMES + 6 ); // phpcs:ignore
		$out   = array();

		foreach ( $trace as $frame ) {
			if ( count( $out ) >= self::MAX_FRAMES ) {
				break;
			}
			// Skip our own frames so the stack starts at the failing code.
			if ( isset( $frame['class'] ) && 0 === strpos( $frame['class'], 'WPCA_' ) ) {
				continue;
			}
			$entry = array(
				'file' => isset( $frame['file'] ) ? self::path( $frame['file'] ) : '',
				'line' => isset( $frame['line'] ) ? (int) $frame['line'] : 0,
			);
			if ( isset( $frame['class'], $frame['type'] ) ) {
				$entry['call'] = $frame['class'] . $frame['type'] . ( isset( $frame['function'] ) ? $frame['function'] : '' );
			} elseif ( isset( $frame['function'] ) ) {
				$entry['call'] = $frame['function'];
			}
			$out[] = $entry;
		}

		$json = wp_json_encode( $out );
		return $json ? $json : '';
	}

	/**
	 * Sanitize an arbitrary URL for display (scheme allow-list).
	 * Used by webhook validation and by alert formatting.
	 */
	public static function url( $url ) {
		return esc_url_raw( (string) $url, array( 'http', 'https' ) );
	}
}
