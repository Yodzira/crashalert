<?php
/**
 * Storage for captured crashes. One table, dedup by signature (24 h),
 * retention pruning. All queries prepared; the table is created with
 * dbDelta and fully dropped on uninstall.
 */

defined( 'ABSPATH' ) || exit;

class WPCA_Repository {

	const TABLE_SUFFIX = 'wpcrash_events';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_SUFFIX;
	}

	public static function activate() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			signature CHAR(32) NOT NULL DEFAULT '',
			created_at BIGINT UNSIGNED NOT NULL DEFAULT 0,
			last_seen BIGINT UNSIGNED NOT NULL DEFAULT 0,
			occurrences BIGINT UNSIGNED NOT NULL DEFAULT 1,
			type SMALLINT UNSIGNED NOT NULL DEFAULT 0,
			message TEXT NULL,
			file VARCHAR(255) NOT NULL DEFAULT '',
			line BIGINT UNSIGNED NOT NULL DEFAULT 0,
			culprit_kind VARCHAR(20) NOT NULL DEFAULT '',
			culprit_name VARCHAR(191) NOT NULL DEFAULT '',
			culprit_detail VARCHAR(255) NOT NULL DEFAULT '',
			url VARCHAR(255) NOT NULL DEFAULT '',
			stack LONGTEXT NULL,
			context LONGTEXT NULL,
			PRIMARY KEY  (id),
			KEY signature (signature),
			KEY created_at (created_at),
			KEY culprit (culprit_kind, culprit_name(60))
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'wpcrash_version', WPCA_VERSION, false );
	}

	public static function table_exists() {
		global $wpdb;
		return self::table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', self::table() ) );
	}

	/**
	 * Store an event. Returns the row id, or 0 if the table is missing.
	 * Dedup: a signature seen in the last 24 h bumps the counter instead
	 * of creating a new row.
	 *
	 * @return int
	 */
	public static function add( array $event ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}

		$sig   = isset( $event['signature'] ) ? (string) $event['signature'] : '';
		$now   = isset( $event['time'] ) ? (int) $event['time'] : time();
		$day   = $now - DAY_IN_SECONDS;

		$existing = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM " . self::table() . " WHERE signature = %s AND last_seen >= %d ORDER BY id DESC LIMIT 1", // phpcs:ignore
				$sig,
				$day
			)
		);

		if ( $existing ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE " . self::table() . " SET occurrences = occurrences + 1, last_seen = %d WHERE id = %d", // phpcs:ignore
					$now,
					$existing
				)
			);
			return $existing;
		}

		$wpdb->insert(
			self::table(),
			array(
				'signature'      => $sig,
				'created_at'     => $now,
				'last_seen'      => $now,
				'occurrences'    => 1,
				'type'           => isset( $event['type'] ) ? (int) $event['type'] : 0,
				'message'        => isset( $event['message'] ) ? $event['message'] : '',
				'file'           => substr( isset( $event['file'] ) ? $event['file'] : '', 0, 255 ),
				'line'           => isset( $event['line'] ) ? (int) $event['line'] : 0,
				'culprit_kind'   => substr( isset( $event['culprit_kind'] ) ? $event['culprit_kind'] : '', 0, 20 ),
				'culprit_name'   => substr( isset( $event['culprit_name'] ) ? $event['culprit_name'] : '', 0, 191 ),
				'culprit_detail' => substr( isset( $event['culprit_detail'] ) ? $event['culprit_detail'] : '', 0, 255 ),
				'url'            => substr( isset( $event['url'] ) ? $event['url'] : '', 0, 255 ),
				'stack'          => isset( $event['stack'] ) ? $event['stack'] : '',
				'context'        => isset( $event['context'] ) ? $event['context'] : '',
			),
			array( '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/** How many times this signature has occurred in the stored window. */
	public static function occurrences_of( $signature ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 1;
		}
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT occurrences FROM " . self::table() . " WHERE signature = %s ORDER BY id DESC LIMIT 1", // phpcs:ignore
				$signature
			)
		);
	}

	/** Timeline rows for the admin page. */
	public static function get_events( $limit = 20, $offset = 0, $culprit_kind = '' ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return array();
		}
		$table = self::table();
		if ( $culprit_kind ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE culprit_kind = %s ORDER BY last_seen DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore
					$culprit_kind,
					$limit,
					$offset
				),
				ARRAY_A
			);
		}
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY last_seen DESC, id DESC LIMIT %d OFFSET %d", // phpcs:ignore
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	public static function count( $culprit_kind = '' ) {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return 0;
		}
		$table = self::table();
		if ( $culprit_kind ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE culprit_kind = %s", $culprit_kind ) );
		}
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	/** Delete events older than N days. */
	public static function prune( $days = 60 ) {
		global $wpdb;
		$settings = WPCA_Settings::all();
		$days     = $days > 0 ? $days : (int) ( isset( $settings['retention_days'] ) ? $settings['retention_days'] : 60 );
		if ( ! self::table_exists() ) {
			return;
		}
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM " . self::table() . " WHERE last_seen < %d", // phpcs:ignore
				time() - (int) $days * DAY_IN_SECONDS
			)
		);
	}

	/** Wipe everything (admin button, uninstall). */
	public static function erase_all() {
		global $wpdb;
		if ( ! self::table_exists() ) {
			return;
		}
		$wpdb->query( "TRUNCATE TABLE " . self::table() ); // phpcs:ignore
	}

	/** Drop the table completely (uninstall only). */
	public static function drop_table() {
		global $wpdb;
		$wpdb->query( "DROP TABLE IF EXISTS " . self::table() ); // phpcs:ignore
	}
}
