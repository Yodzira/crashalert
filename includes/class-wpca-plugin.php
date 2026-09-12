<?php
/**
 * Plugin orchestration: activation, cron, admin, upgrade hooks.
 * Everything heavy lives behind is_admin()/cron checks so the front end
 * never pays for admin code.
 */

defined( 'ABSPATH' ) || exit;

class WPCA_Plugin {

	public static function boot() {
		// Editor-side capture of deprecations is Pro territory; free stays passive.

		add_action( 'init', array( __CLASS__, 'load_textdomain' ) );

		if ( is_admin() ) {
			require_once WPCA_DIR . 'includes/class-wpca-admin.php';
			WPCA_Admin::init();
		}

		// Snapshot of plugin versions right after updates (correlation).
		add_action( 'upgrader_process_complete', array( 'WPCA_Correlator', 'capture_snapshot' ), 10, 2 );

		// Alert queue worker.
		add_action( 'wpca_send_alerts', array( 'WPCA_Alerter', 'process_queue' ) );

		// Retention pruning, twice a day.
		add_action( 'wpca_prune', array( 'WPCA_Repository', 'prune' ) );

		if ( ! wp_next_scheduled( 'wpca_prune' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'wpca_prune' );
		}

		// Attribute refreshes on plugin changes (cheap, admin only).
		if ( is_admin() ) {
			add_action( 'activated_plugin', array( 'WPCA_Attributor', 'refresh_cache' ) );
			add_action( 'deactivated_plugin', array( 'WPCA_Attributor', 'refresh_cache' ) );
		}
	}

	public static function load_textdomain() {
		load_plugin_textdomain( 'crashalert', false, dirname( plugin_basename( WPCA_FILE ) ) . '/languages' );
	}

	public static function activate() {
		WPCA_Repository::activate();
		WPCA_Attributor::refresh_cache();
		WPCA_Correlator::capture_snapshot( null, array() );
		if ( ! wp_next_scheduled( 'wpca_prune' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'wpca_prune' );
		}
	}

	public static function deactivate() {
		// Keep the data (history survives deactivation), stop the workers.
		$timestamp = wp_next_scheduled( 'wpca_send_alerts' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpca_send_alerts' );
		}
	}
}
