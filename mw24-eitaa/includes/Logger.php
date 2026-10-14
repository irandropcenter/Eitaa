<?php
/**
 * Event log stored in {$prefix}mw24_eitaa_log.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Logger. Phone numbers are always masked and the app token is never stored.
 */
class MW24_Eitaa_Logger {

	const LEVELS = array( 'debug', 'info', 'warning', 'error' );

	/**
	 * Settings.
	 *
	 * @var MW24_Eitaa_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Settings $settings Settings.
	 */
	public function __construct( $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'mw24_eitaa_log';
	}

	/**
	 * Writes a log entry.
	 *
	 * @param string $level   debug|info|warning|error.
	 * @param string $event   Machine event name, e.g. auth.login.
	 * @param string $message Short English message (technical log).
	 * @param array  $context Extra data (phone/eitaa_id/user_id are handled specially).
	 * @return void
	 */
	public function log( $level, $event, $message, array $context = array() ) {
		if ( ! in_array( $level, self::LEVELS, true ) ) {
			$level = 'info';
		}
		if ( 'debug' === $level && ! $this->settings->get( 'debug' ) ) {
			return;
		}
		if ( ! MW24_Eitaa_Installer::tables_ready() ) {
			return;
		}
		$user_id  = isset( $context['user_id'] ) ? (int) $context['user_id'] : 0;
		$eitaa_id = isset( $context['eitaa_id'] ) ? substr( preg_replace( '/\D/', '', (string) $context['eitaa_id'] ), 0, 32 ) : '';
		unset( $context['user_id'], $context['eitaa_id'] );
		$context = self::sanitize_context( $context );
		$ip      = isset( $context['ip'] ) ? (string) $context['ip'] : ( wp_doing_cron() ? '' : MW24_Eitaa_Rate_Limiter::client_ip() );
		unset( $context['ip'] );
		$event = preg_replace( '/[^a-z0-9_.\-]/', '', strtolower( (string) $event ) );
		$event = '' === $event ? 'event' : $event;

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			self::table(),
			array(
				'created_at' => current_time( 'mysql', true ),
				'level'      => $level,
				'event'      => substr( $event, 0, 64 ),
				'message'    => substr( wp_strip_all_tags( $message ), 0, 2000 ),
				'user_id'    => $user_id,
				'eitaa_id'   => $eitaa_id,
				'ip'         => substr( $ip, 0, 45 ),
				'context'    => $context ? wp_json_encode( $context ) : null,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' )
		);

		/**
		 * Fires after a log entry was written.
		 *
		 * @param string $level   Level.
		 * @param string $event   Event.
		 * @param string $message Message.
		 * @param array  $context Sanitized context.
		 */
		do_action( 'mw24_eitaa_logged', $level, $event, $message, $context );
	}

	/**
	 * Debug entry (only stored when debug mode is on).
	 *
	 * @param string $event   Event.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public function debug( $event, $message, array $context = array() ) {
		$this->log( 'debug', $event, $message, $context );
	}

	/**
	 * Info entry.
	 *
	 * @param string $event   Event.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public function info( $event, $message, array $context = array() ) {
		$this->log( 'info', $event, $message, $context );
	}

	/**
	 * Warning entry.
	 *
	 * @param string $event   Event.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public function warning( $event, $message, array $context = array() ) {
		$this->log( 'warning', $event, $message, $context );
	}

	/**
	 * Error entry.
	 *
	 * @param string $event   Event.
	 * @param string $message Message.
	 * @param array  $context Context.
	 * @return void
	 */
	public function error( $event, $message, array $context = array() ) {
		$this->log( 'error', $event, $message, $context );
	}

	/**
	 * Masks phones, removes secrets, limits size.
	 *
	 * @param array $context Context.
	 * @return array
	 */
	public static function sanitize_context( array $context ) {
		$out = array();
		foreach ( $context as $key => $value ) {
			$key = (string) $key;
			if ( preg_match( '/token|secret|password|hash|init_?data|contact_raw/i', $key ) ) {
				continue;
			}
			if ( preg_match( '/phone|mobile/i', $key ) && is_scalar( $value ) ) {
				$out[ $key ] = MW24_Eitaa_Phone_Normalizer::mask( (string) $value );
				continue;
			}
			if ( is_array( $value ) ) {
				$out[ $key ] = self::sanitize_context( $value );
				continue;
			}
			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				$out[ $key ] = $value;
				continue;
			}
			$out[ $key ] = substr( wp_strip_all_tags( (string) $value ), 0, 500 );
		}
		return $out;
	}

	/**
	 * Paginated query for the admin log screen.
	 *
	 * @param array $args level, event, search, user_id, per_page, page.
	 * @return array{items:array,total:int}
	 */
	public function query( array $args = array() ) {
		global $wpdb;
		$args   = wp_parse_args(
			$args,
			array(
				'level'    => '',
				'event'    => '',
				'search'   => '',
				'user_id'  => 0,
				'per_page' => 50,
				'page'     => 1,
			)
		);
		$where  = array( '1=1' );
		$params = array();
		if ( in_array( $args['level'], self::LEVELS, true ) ) {
			$where[]  = 'level = %s';
			$params[] = $args['level'];
		}
		if ( '' !== $args['event'] ) {
			$where[]  = 'event = %s';
			$params[] = (string) $args['event'];
		}
		if ( (int) $args['user_id'] > 0 ) {
			$where[]  = 'user_id = %d';
			$params[] = (int) $args['user_id'];
		}
		if ( '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$where[] = '(message LIKE %s OR event LIKE %s OR eitaa_id LIKE %s OR ip LIKE %s)';
			$params  = array_merge( $params, array( $like, $like, $like, $like ) );
		}
		$table    = self::table();
		$where    = implode( ' AND ', $where );
		$per_page = max( 1, min( 500, (int) $args['per_page'] ) );
		$offset   = ( max( 1, (int) $args['page'] ) - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		$items_sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );
		$items = $wpdb->get_results( $wpdb->prepare( $items_sql, array_merge( $params, array( $per_page, $offset ) ) ), ARRAY_A );
		// phpcs:enable
		return array(
			'items' => (array) $items,
			'total' => $total,
		);
	}

	/**
	 * Distinct event names (for filters).
	 *
	 * @return string[]
	 */
	public function events() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (array) $wpdb->get_col( "SELECT DISTINCT event FROM {$table} ORDER BY event ASC LIMIT 200" );
	}

	/**
	 * Most recent entries.
	 *
	 * @param int $limit Limit.
	 * @return array
	 */
	public function recent( $limit = 10 ) {
		$result = $this->query( array( 'per_page' => $limit ) );
		return $result['items'];
	}

	/**
	 * Deletes all entries.
	 *
	 * @return void
	 */
	public function clear() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * Deletes entries older than N days.
	 *
	 * @param int $days Days.
	 * @return void
	 */
	public function cleanup( $days ) {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS ) ) );
	}
}
