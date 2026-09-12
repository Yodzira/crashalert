<?php
/**
 * Uninstall: everything CrashAlert stored is removed — options, cron,
 * the events table and the fallback folder. History does not survive
 * uninstall by design (documented).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	// This file is executed only by the WordPress uninstaller.
	return;
}

global $wpdb;

// Options.
delete_option( 'wpcrash_settings' );
delete_option( 'wpcrash_queue' );
delete_option( 'wpcrash_rate' );
delete_option( 'wpcrash_down' );
delete_option( 'wpcrash_recovery' );
delete_option( 'wpcrash_env_cache' );
delete_option( 'wpcrash_upgrade_snapshot' );
delete_option( 'wpcrash_version' );

// Cron events.
wp_clear_scheduled_hook( 'wpca_send_alerts' );
wp_clear_scheduled_hook( 'wpca_prune' );

// Events table.
$table = $wpdb->prefix . 'wpcrash_events';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore

// Fallback folder in uploads.
$upload = wp_upload_dir();
if ( ! empty( $upload['basedir'] ) ) {
	$dir = rtrim( $upload['basedir'], '/\\' ) . '/crashalert';
	if ( is_dir( $dir ) ) {
		foreach ( array( 'last.fatal.json', '.htaccess', 'index.php' ) as $f ) {
			@unlink( $dir . '/' . $f ); // phpcs:ignore
		}
		@rmdir( $dir ); // phpcs:ignore
	}
}
