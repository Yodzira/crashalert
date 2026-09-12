<?php
/**
 * Error signature: identical errors collapse into one event with a
 * counter; different lines/messages produce different signatures.
 * The message is normalized (numbers and quoted strings replaced)
 * so recurring errors of the same kind share a signature.
 */

defined( 'ABSPATH' ) || exit;

class WPCA_Signature {

	/**
	 * @param int    $type    PHP error level (E_ERROR...).
	 * @param string $file    Absolute file path.
	 * @param int    $line    Line number.
	 * @param string $message Raw message.
	 */
	public static function make( $type, $file, $line, $message ) {
		return md5( (int) $type . '|' . wp_normalize_path( (string) $file ) . '|' . (int) $line . '|' . self::normalize( (string) $message ) );
	}

	/**
	 * Normalize volatile parts of a message so two hits of the same bug
	 * deduplicate: object IDs, numbers and quoted values are masked.
	 */
	public static function normalize( $message ) {
		$m = (string) $message;
		$m = preg_replace( '/\b[0-9a-f]{8,}\b/i', '#hex#', $m );           // long hex (ids, hashes)
		$m = preg_replace( '/\'[^\']*\'/', '\'#s\'', $m );                  // 'quoted strings'
		$m = preg_replace( '/"([^"]*)"/', '"#s"', $m );                     // "quoted strings"
		$m = preg_replace( '/\b\d+\b/', '#n', $m );                         // bare numbers
		$m = preg_replace( '/\s+/', ' ', $m );                              // whitespace collapse
		return trim( $m );
	}
}
