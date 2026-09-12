<?php
/**
 * Plugin Name:       CrashAlert
 * Plugin URI:        https://wordpress.org/plugins/crashalert/
 * Description:       Fatal error alerts to Telegram, email and webhook — with plain-language explanations, culprit attribution and a recovery notice. Know about a crash before your clients do.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Yodzira
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       crashalert
 */

defined( 'ABSPATH' ) || exit;

define( 'WPCA_VERSION', '0.1.0' );
define( 'WPCA_FILE', __FILE__ );
define( 'WPCA_DIR', plugin_dir_path( __FILE__ ) );

/*
 * The shutdown handler is registered at file-include time, NOT on
 * plugins_loaded: the earlier it sits in the stack, the more third-party
 * fatals it sees. Everything inside is guarded so that CrashAlert itself
 * can never be the cause of a white screen.
 */
require_once WPCA_DIR . 'includes/class-wpca-shutdown.php';
WPCA_Shutdown::register();

require_once WPCA_DIR . 'includes/class-wpca-plugin.php';

/*
 * Class autoloader: WPCA_Foo_Bar => includes/class-wpca-foo-bar.php.
 * Covers activation, cron, admin and the shutdown path alike.
 */
spl_autoload_register( function ( $class ) {
	if ( 0 !== strpos( $class, 'WPCA_' ) ) {
		return;
	}
	$file = WPCA_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
	if ( is_readable( $file ) ) {
		require_once $file;
	}
} );

register_activation_hook( __FILE__, array( 'WPCA_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPCA_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WPCA_Plugin', 'boot' ) );
