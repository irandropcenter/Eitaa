<?php
/**
 * Fixed-window rate limiting and client IP detection.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Transient based rate limiter (works with or without a persistent object cache).
 */
class MW24_Eitaa_Rate_Limiter {

	/**
	 * Default limits: bucket => array( limit, window seconds ).
	 *
	 * @return array
	 */
	public static function limits() {
		/**
		 * Filters rate limits.
		 *
		 * @param array $limits bucket => array( max requests, window in seconds ).
		 */
		return (array) apply_filters(
			'mw24_eitaa_rate_limits',
			array(
				'ip'    => array( 40, 10 * MINUTE_IN_SECONDS ),
				'eitaa' => array( 20, 10 * MINUTE_IN_SECONDS ),
				'route' => array( 60, 10 * MINUTE_IN_SECONDS ),
			)
		);
	}

	/**
	 * Registers a hit and tells whether the request is allowed.
	 *
	 * @param string $type Bucket type (key of limits()).
	 * @param string $id   Identifier (IP, Eitaa id...).
	 * @return bool
	 */
	public static function hit( $type, $id ) {
		$limits = self::limits();
		if ( empty( $limits[ $type ] ) ) {
			return true;
		}
		list( $limit, $window ) = array_map( 'intval', $limits[ $type ] );
		if ( $limit <= 0 ) {
			return true;
		}
		$key  = 'mw24_eitaa_rl_' . md5( $type . '|' . $id );
		$now  = time();
		$data = get_transient( $key );
		if ( ! is_array( $data ) || empty( $data['reset'] ) || (int) $data['reset'] <= $now ) {
			$data = array(
				'count' => 0,
				'reset' => $now + max( 1, $window ),
			);
		}
		++$data['count'];
		set_transient( $key, $data, max( 1, (int) $data['reset'] - $now ) );
		return $data['count'] <= $limit;
	}

	/**
	 * Client IP using the header configured by the admin (REMOTE_ADDR by default,
	 * because forwarded headers can be spoofed unless a trusted proxy/CDN sets them).
	 *
	 * @return string
	 */
	public static function client_ip() {
		$header = 'REMOTE_ADDR';
		if ( function_exists( 'mw24_eitaa' ) && mw24_eitaa()->settings ) {
			$header = (string) mw24_eitaa()->settings->get( 'ip_header' );
		}
		$candidates = array();
		if ( 'REMOTE_ADDR' !== $header && ! empty( $_SERVER[ $header ] ) ) {
			$candidates = array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) ) );
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		foreach ( $candidates as $ip ) {
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return (string) apply_filters( 'mw24_eitaa_client_ip', $ip );
			}
		}
		return '';
	}
}
