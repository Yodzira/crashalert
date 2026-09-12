<?php
/**
 * Minimal bootstrap for standalone (non-WP) PHPUnit runs.
 * Tiny shims for the WP functions the classes call. Not a WP replacement:
 * only what unit tests need, emulating documented WP behavior.
 */
if (!defined('ABSPATH')) { define('ABSPATH', '/wp/'); }
if (!defined('WP_PLUGIN_DIR')) { define('WP_PLUGIN_DIR', '/wp/wp-content/plugins'); }
if (!defined('WPMU_PLUGIN_DIR')) { define('WPMU_PLUGIN_DIR', '/wp/wp-content/mu-plugins'); }
if (!defined('WP_CONTENT_DIR')) { define('WP_CONTENT_DIR', '/wp/wp-content'); }
if (!defined('DAY_IN_SECONDS')) { define('DAY_IN_SECONDS', 86400); }
if (!defined('HOUR_IN_SECONDS')) { define('HOUR_IN_SECONDS', 3600); }

$GLOBALS['__wp_options'] = array();
function get_option($k, $d = false) { return array_key_exists($k, $GLOBALS['__wp_options']) ? $GLOBALS['__wp_options'][$k] : $d; }
function update_option($k, $v, $a = false) { $GLOBALS['__wp_options'][$k] = $v; return true; }
function delete_option($k) { unset($GLOBALS['__wp_options'][$k]); return true; }

function wp_normalize_path($p) { $p = str_replace(chr(92), "/", $p); return preg_replace("~^([a-z]):~i", strtolower(substr($p, 0, 1)) . ":", $p); }
function wp_json_encode($v) { return json_encode($v); }
function wp_parse_args($args, $defaults) { return array_merge($defaults, (array) $args); }
function wp_date($f, $t = null) { return date($f, $t ? $t : time()); }
function wp_mail($to, $s, $b) { return true; }
function home_url($p = "") { return "https://example.com" . $p; }
function get_locale() { return "en_US"; }

/**
 * Emulates esc_url_raw with an explicit scheme allow-list.
 */
function esc_url_raw($url, $allowed = array("http", "https")) {
    $url = trim((string) $url);
    $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    if ($scheme === "" || !in_array($scheme, $allowed, true)) { return ""; }
    return $url;
}

function get_stylesheet_directory() { return "/wp/wp-content/themes/child"; }
function get_template_directory() { return "/wp/wp-content/themes/parent"; }
function wp_get_theme($dir = "") { return new WPCA_Fake_Theme(); }
class WPCA_Fake_Theme {
    public function exists() { return false; }
    public function get($p) { return ""; }
}

require_once dirname(__DIR__) . "/includes/class-wpca-sanitizer.php";
require_once dirname(__DIR__) . "/includes/class-wpca-signature.php";
require_once dirname(__DIR__) . "/includes/class-wpca-ratelimiter.php";
require_once dirname(__DIR__) . "/includes/class-wpca-explainer.php";
require_once dirname(__DIR__) . "/includes/class-wpca-attributor.php";
require_once dirname(__DIR__) . "/includes/class-wpca-settings.php";
