<?php
/**
 * Symmetric encryption for secrets stored in the database (app token).
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * AES-256-GCM helper keyed by MW24_EITAA_ENCRYPTION_KEY or the site's auth salt.
 *
 * This protects the token in database dumps/backups. It is not a replacement for
 * defining MW24_EITAA_APP_TOKEN in wp-config.php, which keeps the token out of the
 * database entirely. Binary data is hex encoded (no base64) for transparency.
 */
class MW24_Eitaa_Crypto {

	const PREFIX       = 'mw24e1:';
	const PLAIN_PREFIX = 'mw24p1:';
	const CIPHER       = 'aes-256-gcm';

	/**
	 * Derives the binary key.
	 *
	 * @return string
	 */
	private static function key() {
		if ( defined( 'MW24_EITAA_ENCRYPTION_KEY' ) && '' !== (string) MW24_EITAA_ENCRYPTION_KEY ) {
			$material = (string) MW24_EITAA_ENCRYPTION_KEY;
		} else {
			$material = wp_salt( 'auth' );
		}
		return hash( 'sha256', $material . '|mw24-eitaa', true );
	}

	/**
	 * Whether real encryption is available.
	 *
	 * @return bool
	 */
	public static function available() {
		return function_exists( 'openssl_encrypt' ) && in_array( self::CIPHER, openssl_get_cipher_methods(), true );
	}

	/**
	 * Encrypts a string.
	 *
	 * @param string $plain Plain text.
	 * @return string
	 */
	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( ! self::available() ) {
			return self::PLAIN_PREFIX . bin2hex( $plain );
		}
		$iv     = random_bytes( 12 );
		$tag    = '';
		$cipher = openssl_encrypt( $plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag );
		if ( false === $cipher ) {
			return self::PLAIN_PREFIX . bin2hex( $plain );
		}
		return self::PREFIX . bin2hex( $iv . $tag . $cipher );
	}

	/**
	 * Decrypts a string produced by encrypt(). Returns '' on failure.
	 *
	 * @param string $stored Stored value.
	 * @return string
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( 0 === strpos( $stored, self::PLAIN_PREFIX ) ) {
			$hex = substr( $stored, strlen( self::PLAIN_PREFIX ) );
			return ctype_xdigit( $hex ) && 0 === strlen( $hex ) % 2 ? (string) hex2bin( $hex ) : '';
		}
		if ( 0 !== strpos( $stored, self::PREFIX ) || ! self::available() ) {
			return '';
		}
		$hex = substr( $stored, strlen( self::PREFIX ) );
		if ( ! ctype_xdigit( $hex ) || 0 !== strlen( $hex ) % 2 ) {
			return '';
		}
		$raw = (string) hex2bin( $hex );
		if ( strlen( $raw ) < 29 ) {
			return '';
		}
		$plain = openssl_decrypt( substr( $raw, 28 ), self::CIPHER, self::key(), OPENSSL_RAW_DATA, substr( $raw, 0, 12 ), substr( $raw, 12, 16 ) );
		return false === $plain ? '' : $plain;
	}
}
