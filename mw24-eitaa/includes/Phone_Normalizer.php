<?php
/**
 * Iranian mobile number normalization and formatting.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Canonical form used everywhere inside the plugin: 09XXXXXXXXX (11 digits).
 */
class MW24_Eitaa_Phone_Normalizer {

	/**
	 * Supported storage formats.
	 * '0' = 09121234567, '9' = 9121234567, '98' = 989121234567,
	 * '+98' = +989121234567, '0098' = 00989121234567, 'custom' = pattern with {cc}{national}{local}.
	 */
	const FORMATS = array( '0', '9', '98', '+98', '0098', 'custom' );

	/**
	 * Converts Persian (۰-۹) and Arabic-Indic (٠-٩) digits to ASCII.
	 *
	 * @param string $value Input.
	 * @return string
	 */
	public static function to_english_digits( $value ) {
		static $map = null;
		if ( null === $map ) {
			$map     = array();
			$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );
			$arabic  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );
			for ( $i = 0; $i < 10; $i++ ) {
				$map[ $persian[ $i ] ] = (string) $i;
				$map[ $arabic[ $i ] ]  = (string) $i;
			}
		}
		return strtr( (string) $value, $map );
	}

	/**
	 * Normalizes any common representation to 09XXXXXXXXX.
	 * Accepts +98…, 0098…, 98…, 0…, 9…, with spaces/dashes and Persian/Arabic digits.
	 *
	 * @param mixed $raw Raw phone.
	 * @return string Canonical phone or '' when not a valid Iranian mobile number.
	 */
	public static function normalize( $raw ) {
		if ( ! is_scalar( $raw ) ) {
			return '';
		}
		$digits = preg_replace( '/\D+/', '', self::to_english_digits( (string) $raw ) );
		if ( 0 === strpos( $digits, '0098' ) ) {
			$digits = substr( $digits, 4 );
		} elseif ( 0 === strpos( $digits, '98' ) && strlen( $digits ) >= 12 ) {
			$digits = substr( $digits, 2 );
		}
		if ( 11 === strlen( $digits ) && '0' === $digits[0] ) {
			$digits = substr( $digits, 1 );
		}
		$result = preg_match( '/^9\d{9}$/', $digits ) ? '0' . $digits : '';

		/**
		 * Filters the normalized phone number.
		 *
		 * @param string $result Canonical 09XXXXXXXXX or ''.
		 * @param mixed  $raw    Raw input.
		 */
		return (string) apply_filters( 'mw24_eitaa_normalize_phone', $result, $raw );
	}

	/**
	 * Whether a value is a valid Iranian mobile number.
	 *
	 * @param mixed $raw Raw phone.
	 * @return bool
	 */
	public static function is_valid( $raw ) {
		return '' !== self::normalize( $raw );
	}

	/**
	 * The five standard variants of a canonical phone.
	 *
	 * @param string $normalized Canonical phone.
	 * @return string[]
	 */
	public static function variants( $normalized ) {
		$normalized = (string) $normalized;
		if ( '' === $normalized ) {
			return array();
		}
		$national = substr( $normalized, 1 );
		return array( '0' . $national, $national, '98' . $national, '+98' . $national, '0098' . $national );
	}

	/**
	 * Formats a canonical phone for storage.
	 *
	 * @param string $normalized Canonical phone.
	 * @param string $format     One of self::FORMATS.
	 * @param string $pattern    Custom pattern, e.g. "+{cc}-{national}".
	 * @return string
	 */
	public static function format( $normalized, $format, $pattern = '' ) {
		$normalized = (string) $normalized;
		if ( '' === $normalized ) {
			return '';
		}
		$national = substr( $normalized, 1 );
		$format   = (string) $format;
		// Strict comparisons on purpose: with switch(), "+98" == "98" == "0098" numerically.
		if ( '9' === $format ) {
			return $national;
		}
		if ( '98' === $format ) {
			return '98' . $national;
		}
		if ( '+98' === $format ) {
			return '+98' . $national;
		}
		if ( '0098' === $format ) {
			return '0098' . $national;
		}
		if ( 'custom' === $format && '' !== (string) $pattern ) {
			return strtr(
				(string) $pattern,
				array(
					'{cc}'       => '98',
					'{national}' => $national,
					'{local}'    => $normalized,
				)
			);
		}
		return $normalized;
	}

	/**
	 * Guesses the storage format of an existing value (used to pre-fill the manual map).
	 *
	 * @param string $value Stored value.
	 * @return string
	 */
	public static function detect_format( $value ) {
		$value = trim( self::to_english_digits( (string) $value ) );
		if ( 0 === strpos( $value, '+98' ) ) {
			return '+98';
		}
		if ( 0 === strpos( $value, '0098' ) ) {
			return '0098';
		}
		if ( 0 === strpos( $value, '98' ) && strlen( preg_replace( '/\D/', '', $value ) ) >= 12 ) {
			return '98';
		}
		if ( 0 === strpos( $value, '09' ) ) {
			return '0';
		}
		if ( 0 === strpos( $value, '9' ) ) {
			return '9';
		}
		return '0';
	}

	/**
	 * Masks a phone for logs and previews: 0912***4567.
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public static function mask( $phone ) {
		$normalized = self::normalize( $phone );
		$digits     = '' !== $normalized ? $normalized : preg_replace( '/\D/', '', self::to_english_digits( (string) $phone ) );
		$length     = strlen( $digits );
		if ( $length < 8 ) {
			return str_repeat( '*', $length );
		}
		return substr( $digits, 0, 4 ) . str_repeat( '*', $length - 8 ) . substr( $digits, -4 );
	}

	/**
	 * Human readable labels for formats.
	 *
	 * @return array
	 */
	public static function format_labels() {
		return array(
			'0'      => '09121234567',
			'9'      => '9121234567',
			'98'     => '989121234567',
			'+98'    => '+989121234567',
			'0098'   => '00989121234567',
			'custom' => __( 'Custom pattern', 'mw24-eitaa' ),
		);
	}
}
