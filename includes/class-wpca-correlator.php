<?php
/**
 * Correlator: snapshots of active plugin versions taken right after an
 * upgrade run. Event cards use them to answer "what changed in the 24 h
 * before the crash?" — the question every admin asks first.
 */

defined( 'ABSPATH' ) || exit;

class WPCA_Correlator {

	const KEY = 'wpcrash_upgrade_snapshot';

	/**
	 * Store a version snapshot after upgrades.
	 * Hooked to upgrader_process_complete (and called on activation).
	 */
	public static function capture_snapshot( $upgrader = null, $hook_extra = array() ) {
		$snapshot = array(
			'time'    => time(),
			'plugins' => self::current_plugins(),
		);
		update_option( self::KEY, $snapshot, false );
	}

	/**
	 * Describe version changes within the last N seconds (default 24 h).
	 *
	 * @return array List of strings like "Plugin X: 1.2 → 1.3".
	 */
	public static function changes_last_24h( $seconds = DAY_IN_SECONDS ) {
		$snapshot = get_option( self::KEY, array() );
		if ( ! is_array( $snapshot ) || empty( $snapshot['time'] ) ) {
			return array();
		}
		if ( ( time() - (int) $snapshot['time'] ) > $seconds ) {
			return array();
		}

		$before = isset( $snapshot['plugins'] ) && is_array( $snapshot['plugins'] ) ? $snapshot['plugins'] : array();
		$now    = self::current_plugins();
		$diff   = array();

		foreach ( $now as $file => $info ) {
			$ver   = $info['Version'];
			$name  = $info['Name'];
			if ( isset( $before[ $file ] ) ) {
				if ( $before[ $file ]['Version'] !== $ver ) {
					$diff[] = sprintf( '%s: %s → %s', $name, $before[ $file ]['Version'], $ver );
				}
			} else {
				$diff[] = sprintf( '%s: newly installed (%s)', $name, $ver );
			}
		}
		foreach ( $before as $file => $info ) {
			if ( ! isset( $now[ $file ] ) ) {
				$diff[] = sprintf( '%s: removed', $info['Name'] );
			}
		}

		return $diff;
	}

	private static function current_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return get_plugins();
	}
}
