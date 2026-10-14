<?php
/**
 * Validation of Eitaa signed strings (initData and requestContact responses).
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Implements the official Eitaa algorithm:
 *  1. parse the query string, take out "hash";
 *  2. sort the remaining keys alphabetically and join "key=value" pairs with "\n";
 *  3. secret = HMAC_SHA256( message = app_token, key = "WebAppData" );
 *  4. compare hex( HMAC_SHA256( data_check_string, secret ) ) with "hash" (constant time);
 *  5. check auth_date (not in the future, not older than the allowed age).
 *
 * The class has no WordPress dependency except WP_Error so it can be unit tested easily.
 */
class MW24_Eitaa_Validator {

	/** Allowed clock skew for auth_date values in the future (seconds). */
	const FUTURE_SKEW = 300;

	/** Maximum accepted length of a signed string. */
	const MAX_LENGTH = 8192;

	/**
	 * Parses a query string exactly like parse_str() decodes values, but keeps the
	 * keys verbatim (no "." / "[]" mangling) and rejects duplicate keys.
	 *
	 * @param string $raw Query string.
	 * @return array|WP_Error
	 */
	public static function parse( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return new WP_Error( 'empty', 'Signed data is empty.' );
		}
		if ( strlen( $raw ) > self::MAX_LENGTH ) {
			return new WP_Error( 'too_long', 'Signed data is too long.' );
		}
		$fields = array();
		foreach ( explode( '&', $raw ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}
			$parts = explode( '=', $pair, 2 );
			$key   = urldecode( $parts[0] );
			$value = isset( $parts[1] ) ? urldecode( $parts[1] ) : '';
			if ( '' === $key ) {
				return new WP_Error( 'malformed', 'Signed data contains an empty key.' );
			}
			if ( array_key_exists( $key, $fields ) ) {
				return new WP_Error( 'duplicate_key', 'Signed data contains a duplicate key.' );
			}
			$fields[ $key ] = $value;
		}
		return $fields;
	}

	/**
	 * Builds the data-check-string.
	 *
	 * @param array $fields Parsed fields (hash is ignored).
	 * @return string
	 */
	public static function data_check_string( array $fields ) {
		unset( $fields['hash'] );
		ksort( $fields, SORT_STRING );
		$lines = array();
		foreach ( $fields as $key => $value ) {
			$lines[] = $key . '=' . $value;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Secret key derived from the app token (order matters: message = token, key = "WebAppData").
	 *
	 * @param string $token App token.
	 * @return string Binary key.
	 */
	public static function secret_key( $token ) {
		return hash_hmac( 'sha256', (string) $token, 'WebAppData', true );
	}

	/**
	 * Computes the expected hex hash for a set of fields.
	 *
	 * @param array  $fields Fields.
	 * @param string $token  App token.
	 * @return string
	 */
	public static function compute_hash( array $fields, $token ) {
		return hash_hmac( 'sha256', self::data_check_string( $fields ), self::secret_key( $token ) );
	}

	/**
	 * Builds a signed query string (used by tests and developer tools).
	 *
	 * @param array  $fields Fields without hash.
	 * @param string $token  App token.
	 * @return string
	 */
	public static function sign( array $fields, $token ) {
		unset( $fields['hash'] );
		$fields['hash'] = self::compute_hash( $fields, $token );
		$pairs          = array();
		foreach ( $fields as $key => $value ) {
			$pairs[] = rawurlencode( (string) $key ) . '=' . rawurlencode( (string) $value );
		}
		return implode( '&', $pairs );
	}

	/**
	 * Validates a signed string.
	 *
	 * @param string   $raw     Signed query string.
	 * @param string   $token   App token.
	 * @param int      $max_age Maximum age in seconds (0 disables the age check).
	 * @param int|null $now     Current timestamp (for tests).
	 * @return array|WP_Error Parsed fields on success.
	 */
	public static function validate( $raw, $token, $max_age = 86400, $now = null ) {
		$token = (string) $token;
		if ( '' === $token ) {
			return new WP_Error( 'no_token', 'App token is not configured.' );
		}
		$fields = self::parse( $raw );
		if ( is_wp_error( $fields ) ) {
			return $fields;
		}
		$hash = isset( $fields['hash'] ) ? strtolower( (string) $fields['hash'] ) : '';
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
			return new WP_Error( 'missing_hash', 'Hash is missing or malformed.' );
		}
		if ( ! hash_equals( self::compute_hash( $fields, $token ), $hash ) ) {
			return new WP_Error( 'bad_hash', 'Signature does not match.' );
		}
		$auth_date = isset( $fields['auth_date'] ) ? (string) $fields['auth_date'] : '';
		if ( '' === $auth_date || ! ctype_digit( $auth_date ) ) {
			return new WP_Error( 'missing_auth_date', 'auth_date is missing.' );
		}
		$now       = null === $now ? time() : (int) $now;
		$auth_date = (int) $auth_date;
		if ( $auth_date > $now + self::FUTURE_SKEW ) {
			return new WP_Error( 'future_auth_date', 'auth_date is in the future.' );
		}
		if ( $max_age > 0 && ( $now - $auth_date ) > $max_age ) {
			return new WP_Error( 'expired', 'Signed data has expired.' );
		}
		$fields['hash'] = $hash;
		return $fields;
	}

	/**
	 * Normalizes a numeric identifier (JSON int, big int string or float) to a digit string.
	 *
	 * @param mixed $value Value.
	 * @return string '' when invalid.
	 */
	public static function normalize_id( $value ) {
		if ( is_int( $value ) ) {
			return $value > 0 ? (string) $value : '';
		}
		if ( is_float( $value ) ) {
			return $value > 0 && $value < 9007199254740993 ? number_format( $value, 0, '', '' ) : '';
		}
		if ( is_string( $value ) && '' !== $value && ctype_digit( $value ) && strlen( $value ) <= 20 ) {
			$value = ltrim( $value, '0' );
			return '' === $value ? '' : $value;
		}
		return '';
	}

	/**
	 * Extracts and normalizes the "user" object of initData.
	 *
	 * @param array $fields Validated fields.
	 * @return array|WP_Error
	 */
	public static function extract_user( array $fields ) {
		if ( empty( $fields['user'] ) ) {
			return new WP_Error( 'no_user', 'initData has no user.' );
		}
		$user = json_decode( (string) $fields['user'], true, 32, JSON_BIGINT_AS_STRING );
		if ( ! is_array( $user ) ) {
			return new WP_Error( 'bad_user', 'User JSON is invalid.' );
		}
		$id = self::normalize_id( isset( $user['id'] ) ? $user['id'] : null );
		if ( '' === $id ) {
			return new WP_Error( 'bad_user_id', 'User id is invalid.' );
		}
		return array(
			'id'                 => $id,
			'first_name'         => isset( $user['first_name'] ) && is_scalar( $user['first_name'] ) ? (string) $user['first_name'] : '',
			'last_name'          => isset( $user['last_name'] ) && is_scalar( $user['last_name'] ) ? (string) $user['last_name'] : '',
			'username'           => isset( $user['username'] ) && is_scalar( $user['username'] ) ? (string) $user['username'] : '',
			'language_code'      => isset( $user['language_code'] ) && is_scalar( $user['language_code'] ) ? (string) $user['language_code'] : '',
			'allows_write_to_pm' => ! empty( $user['allows_write_to_pm'] ),
			'start_param'        => isset( $fields['start_param'] ) ? (string) $fields['start_param'] : '',
			'auth_date'          => isset( $fields['auth_date'] ) ? (int) $fields['auth_date'] : 0,
			'hash'               => isset( $fields['hash'] ) ? (string) $fields['hash'] : '',
		);
	}

	/**
	 * Extracts the contact object of a requestContact response.
	 * Expected: contact={"user_id":..,"phone_number":"98912..","first_name":..}&auth_date=..&hash=..
	 * Other known shapes (user/result JSON, flat phone_number) are accepted defensively.
	 *
	 * @param array $fields Validated fields.
	 * @return array|WP_Error
	 */
	public static function extract_contact( array $fields ) {
		$contact = array();
		foreach ( array( 'contact', 'user', 'result' ) as $key ) {
			if ( empty( $fields[ $key ] ) ) {
				continue;
			}
			$candidate = json_decode( (string) $fields[ $key ], true, 32, JSON_BIGINT_AS_STRING );
			if ( is_array( $candidate ) && ( ! empty( $candidate['phone_number'] ) || ! empty( $candidate['phone'] ) ) ) {
				$contact = $candidate;
				break;
			}
		}
		if ( empty( $contact ) && ! empty( $fields['phone_number'] ) ) {
			$contact = $fields;
		}
		$phone = '';
		if ( ! empty( $contact['phone_number'] ) && is_scalar( $contact['phone_number'] ) ) {
			$phone = (string) $contact['phone_number'];
		} elseif ( ! empty( $contact['phone'] ) && is_scalar( $contact['phone'] ) ) {
			$phone = (string) $contact['phone'];
		}
		if ( '' === $phone ) {
			return new WP_Error( 'no_phone', 'Contact response has no phone number.' );
		}
		$user_id = '';
		foreach ( array( 'user_id', 'id' ) as $key ) {
			if ( isset( $contact[ $key ] ) ) {
				$user_id = self::normalize_id( $contact[ $key ] );
				break;
			}
		}
		return array(
			'user_id'      => $user_id,
			'phone_number' => $phone,
			'first_name'   => isset( $contact['first_name'] ) && is_scalar( $contact['first_name'] ) ? (string) $contact['first_name'] : '',
			'last_name'    => isset( $contact['last_name'] ) && is_scalar( $contact['last_name'] ) ? (string) $contact['last_name'] : '',
			'hash'         => isset( $fields['hash'] ) ? (string) $fields['hash'] : '',
		);
	}
}
