<?php
/**
 * Culprit attribution: map an error file path to the plugin, mu-plugin,
 * theme or core that owns it. The environment map (plugins => names) is
 * cached in an option so the shutdown path never calls get_plugins().
 */

defined( 'ABSPATH' ) || exit;

class WPCA_Attributor {

	const CACHE_KEY = 'wpcrash_env_cache';

	/**
	 * @return array {kind: plugin|mu-plugin|theme|core|unknown, name: string, detail: string}
	 */
	public static function attribute( $file ) {
		$file = wp_normalize_path( (string) $file );

		if ( defined( 'WPMU_PLUGIN_DIR' ) && 0 === strpos( $file, wp_normalize_path( WPMU_PLUGIN_DIR ) ) ) {
			return array(
				'kind'   => 'mu-plugin',
				'name'   => basename( $file ),
				'detail' => self::rel( $file, WPMU_PLUGIN_DIR ),
			);
		}

		if ( defined( 'WP_PLUGIN_DIR' ) && 0 === strpos( $file, wp_normalize_path( WP_PLUGIN_DIR ) ) ) {
			$rel = ltrim( substr( $file, strlen( wp_normalize_path( WP_PLUGIN_DIR ) ) ), '/' );
			$name = self::plugin_name_for( $rel, $file );
			return array(
				'kind'   => 'plugin',
				'name'   => $name,
				'detail' => $rel,
			);
		}

		$theme = self::theme_for( $file );
		if ( $theme ) {
			return array(
				'kind'   => 'theme',
				'name'   => $theme,
				'detail' => 'theme',
			);
		}

		if ( defined( 'ABSPATH' ) && 0 === strpos( $file, wp_normalize_path( ABSPATH ) ) ) {
			return array(
				'kind'   => 'core',
				'name'   => 'WordPress core',
				'detail' => self::rel( $file, ABSPATH ),
			);
		}

		return array(
			'kind'   => 'unknown',
			'name'   => basename( $file ),
			'detail' => self::rel( $file, defined( 'ABSPATH' ) ? ABSPATH : '' ),
		);
	}

	/** Name of the plugin owning a plugins-dir-relative path. */
	private static function plugin_name_for( $rel, $file ) {
		$map = self::env_map();
		// Longest prefix wins: subfolder plugins, vendor paths inside a plugin.
		foreach ( $map as $folder => $info ) {
			if ( 0 === strpos( $rel, $folder . '/' ) || $rel === $folder ) {
				return isset( $info['Name'] ) ? $info['Name'] . self::version_note( $info ) : basename( $file );
			}
		}
		// Unknown folder: use the first path segment.
		$seg = explode( '/', $rel );
		return reset( $seg );
	}

	private static function version_note( $info ) {
		return isset( $info['Version'] ) && '' !== $info['Version'] ? ' v' . $info['Version'] : '';
	}

	/** Theme (parent/child) the file belongs to, or '' */
	private static function theme_for( $file ) {
		if ( ! function_exists( 'wp_get_theme' ) ) {
			return '';
		}
		foreach ( array( get_stylesheet_directory(), get_template_directory() ) as $dir ) {
			$dir = wp_normalize_path( $dir );
			if ( $dir && 0 === strpos( $file, $dir . '/' ) ) {
				$theme = wp_get_theme( basename( $dir ) );
				return $theme->exists() ? $theme->get( 'Name' ) : basename( $dir );
			}
		}
		return '';
	}

	/**
	 * Cached map: relative plugin folder => array( Name, Version ).
	 * mu-plugins and vendor dirs inside a plugin are attributed via folders.
	 */
	public static function env_map() {
		$map = get_option( self::CACHE_KEY, null );
		if ( is_array( $map ) ) {
			return $map;
		}
		return self::refresh_cache();
	}

	/** Rebuild the environment cache. Safe to call on any hook. */
	public static function refresh_cache() {
		$map = array();
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		foreach ( get_plugins() as $plugin_file => $data ) {
			$folder = trim( dirname( $plugin_file ), '.' );
			$folder = '' === $folder ? basename( $plugin_file ) : $folder;
			$map[ $folder ] = array(
				'Name'    => isset( $data['Name'] ) ? $data['Name'] : $folder,
				'Version' => isset( $data['Version'] ) ? $data['Version'] : '',
			);
		}
		update_option( self::CACHE_KEY, $map, false );
		return $map;
	}

	/** Make an absolute path relative to a base (for display). */
	private static function rel( $file, $base ) {
		$base = wp_normalize_path( (string) $base );
		if ( $base && 0 === strpos( $file, $base ) ) {
			return ltrim( substr( $file, strlen( $base ) ), '/' );
		}
		return basename( $file );
	}
}
