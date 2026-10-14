<?php
/**
 * A phone map stored in a custom database table.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Custom table map. Table and column names are only ever accepted when they appear
 * in SHOW TABLES / DESCRIBE results (white list); values always go through prepare().
 */
class MW24_Eitaa_Mapping_Table {

	/** Extra column value types. */
	const EXTRA_TYPES = array( 'fixed', 'now', 'now_gmt', 'timestamp', 'user_id', 'phone', 'empty' );

	/**
	 * Enabled.
	 *
	 * @var bool
	 */
	public $enabled = false;

	/**
	 * Table.
	 *
	 * @var string
	 */
	public $table = '';

	/**
	 * Phone col.
	 *
	 * @var string
	 */
	public $phone_col = '';

	/**
	 * User col.
	 *
	 * @var string
	 */
	public $user_col = '';

	/**
	 * Format.
	 *
	 * @var string
	 */
	public $format = '0';

	/**
	 * Pattern.
	 *
	 * @var string
	 */
	public $pattern = '';

	/**
	 * Read only.
	 *
	 * @var bool
	 */
	public $read_only = true;

	/**
	 * Each: col, type, value.
	 *
	 * @var array[]
	 */
	public $extras = array();

	/**
	 * Cached validation result.
	 *
	 * @var true|WP_Error|null
	 */
	private $validated = null;

	/**
	 * Constructor.
	 *
	 * @param array $config Config.
	 */
	public function __construct( array $config ) {
		$this->enabled   = ! empty( $config['enabled'] );
		$this->table     = isset( $config['table'] ) ? (string) $config['table'] : '';
		$this->phone_col = isset( $config['phone_col'] ) ? (string) $config['phone_col'] : '';
		$this->user_col  = isset( $config['user_col'] ) ? (string) $config['user_col'] : '';
		$this->format    = isset( $config['format'] ) && in_array( $config['format'], MW24_Eitaa_Phone_Normalizer::FORMATS, true ) ? $config['format'] : '0';
		$this->pattern   = isset( $config['pattern'] ) ? (string) $config['pattern'] : '';
		$this->read_only = ! isset( $config['read_only'] ) || ! empty( $config['read_only'] );
		$this->extras    = isset( $config['extras'] ) && is_array( $config['extras'] ) ? array_values( $config['extras'] ) : array();
	}

	/**
	 * Tables of the current site (current prefix; on multisite also the base prefix).
	 *
	 * @return string[]
	 */
	public static function list_tables() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}
		global $wpdb;
		$prefixes = array_unique( array( $wpdb->prefix, $wpdb->base_prefix ) );
		$tables   = array();
		foreach ( $prefixes as $prefix ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$found  = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $prefix ) . '%' ) );
			$tables = array_merge( $tables, (array) $found );
		}
		$own   = array( $wpdb->prefix . 'mw24_eitaa_log', $wpdb->prefix . 'mw24_eitaa_queue' );
		$cache = array_values( array_diff( array_unique( $tables ), $own ) );
		sort( $cache );
		return $cache;
	}

	/**
	 * Column description of a white-listed table.
	 *
	 * @param string $table Table name.
	 * @return array[]|WP_Error
	 */
	public static function describe( $table ) {
		static $cache = array();
		$table        = (string) $table;
		if ( isset( $cache[ $table ] ) ) {
			return $cache[ $table ];
		}
		if ( ! in_array( $table, self::list_tables(), true ) ) {
			return new WP_Error( 'mw24_eitaa_bad_table', __( 'The selected table does not exist.', 'mw24-eitaa' ) );
		}
		global $wpdb;
		// The table name comes from the SHOW TABLES white list above.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL -- White-listed identifier, no user values.
		$rows    = $wpdb->get_results( 'DESCRIBE ' . self::quote( $table ), ARRAY_A );
		$columns = array();
		foreach ( (array) $rows as $row ) {
			$columns[] = array(
				'name'     => (string) $row['Field'],
				'type'     => (string) $row['Type'],
				'null'     => 'YES' === strtoupper( (string) $row['Null'] ),
				'key'      => (string) $row['Key'],
				'default'  => $row['Default'],
				'extra'    => (string) $row['Extra'],
				'required' => 'YES' !== strtoupper( (string) $row['Null'] ) && null === $row['Default'] && false === stripos( (string) $row['Extra'], 'auto_increment' ),
			);
		}
		$cache[ $table ] = $columns;
		return $columns;
	}

	/**
	 * Quotes a white-listed identifier.
	 *
	 * @param string $name Identifier.
	 * @return string
	 */
	public static function quote( $name ) {
		return '`' . str_replace( '`', '', (string) $name ) . '`';
	}

	/**
	 * Column names of the configured table.
	 *
	 * @return string[]
	 */
	private function column_names() {
		$columns = self::describe( $this->table );
		return is_wp_error( $columns ) ? array() : wp_list_pluck( $columns, 'name' );
	}

	/**
	 * Validates the configuration against the live schema.
	 *
	 * @return true|WP_Error
	 */
	public function validate() {
		if ( null !== $this->validated ) {
			return $this->validated;
		}
		if ( '' === $this->table ) {
			$this->validated = new WP_Error( 'mw24_eitaa_table_missing', __( 'No table selected.', 'mw24-eitaa' ) );
			return $this->validated;
		}
		$columns = self::describe( $this->table );
		if ( is_wp_error( $columns ) ) {
			$this->validated = $columns;
			return $columns;
		}
		$names = wp_list_pluck( $columns, 'name' );
		if ( ! in_array( $this->phone_col, $names, true ) || ! in_array( $this->user_col, $names, true ) ) {
			$this->validated = new WP_Error( 'mw24_eitaa_bad_column', __( 'The phone or user ID column does not exist in the selected table.', 'mw24-eitaa' ) );
			return $this->validated;
		}
		if ( $this->phone_col === $this->user_col ) {
			$this->validated = new WP_Error( 'mw24_eitaa_bad_column', __( 'The phone column and the user ID column must be different.', 'mw24-eitaa' ) );
			return $this->validated;
		}
		foreach ( $this->extras as $extra ) {
			if ( empty( $extra['col'] ) || ! in_array( $extra['col'], $names, true ) ) {
				$this->validated = new WP_Error( 'mw24_eitaa_bad_column', __( 'An extra column does not exist in the selected table.', 'mw24-eitaa' ) );
				return $this->validated;
			}
		}
		$this->validated = true;
		return true;
	}

	/**
	 * Enabled and valid.
	 *
	 * @return bool
	 */
	public function is_usable() {
		return $this->enabled && true === $this->validate();
	}

	/**
	 * Value to store.
	 *
	 * @param string $normalized Canonical phone.
	 * @return string
	 */
	public function formatted( $normalized ) {
		return MW24_Eitaa_Phone_Normalizer::format( $normalized, $this->format, $this->pattern );
	}

	/**
	 * User IDs whose row matches one of the values.
	 *
	 * @param string[] $values Phone variants.
	 * @param int      $exclude_user Ignore this user ID.
	 * @return int[]
	 */
	public function find_users( array $values, $exclude_user = 0 ) {
		if ( ! $this->is_usable() || empty( $values ) ) {
			return array();
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );
		$phone        = 't.' . self::quote( $this->phone_col );
		$sql          = 'SELECT DISTINCT t.' . self::quote( $this->user_col ) . ' FROM ' . self::quote( $this->table ) . ' t'
			. ' INNER JOIN ' . $wpdb->users . ' u ON u.ID = t.' . self::quote( $this->user_col )
			. " WHERE ( {$phone} IN ($placeholders) OR " . MW24_Eitaa_Phone_Mapper::sql_strip( $phone ) . " IN ($placeholders) )";
		$params       = array_merge( $values, $values );
		if ( $exclude_user > 0 ) {
			$sql     .= ' AND t.' . self::quote( $this->user_col ) . ' <> %d';
			$params[] = (int) $exclude_user;
		}
		$sql .= ' LIMIT 20';
		// Identifiers are white-listed by validate(); values are prepared.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- Identifiers are white-listed; values use placeholders.
		$ids = $wpdb->get_col( $wpdb->prepare( $sql, $params ) );
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Reads the stored phone of a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function read( $user_id ) {
		if ( ! $this->is_usable() ) {
			return '';
		}
		global $wpdb;
		$sql = 'SELECT ' . self::quote( $this->phone_col ) . ' FROM ' . self::quote( $this->table ) . ' WHERE ' . self::quote( $this->user_col ) . ' = %d LIMIT 1';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- Identifiers are white-listed; values use placeholders.
		$value = $wpdb->get_var( $wpdb->prepare( $sql, (int) $user_id ) );
		return null === $value ? '' : (string) $value;
	}

	/**
	 * Resolves extra column values for an insert.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $normalized Canonical phone.
	 * @return array col => value
	 */
	public function extra_values( $user_id, $normalized ) {
		$data = array();
		foreach ( $this->extras as $extra ) {
			$col  = (string) $extra['col'];
			$type = isset( $extra['type'] ) ? (string) $extra['type'] : 'fixed';
			if ( $col === $this->phone_col || $col === $this->user_col ) {
				continue;
			}
			switch ( $type ) {
				case 'now':
					$data[ $col ] = current_time( 'mysql' );
					break;
				case 'now_gmt':
					$data[ $col ] = current_time( 'mysql', true );
					break;
				case 'timestamp':
					$data[ $col ] = (string) time();
					break;
				case 'user_id':
					$data[ $col ] = (string) (int) $user_id;
					break;
				case 'phone':
					$data[ $col ] = $this->formatted( $normalized );
					break;
				case 'empty':
					$data[ $col ] = '';
					break;
				default:
					$data[ $col ] = isset( $extra['value'] ) ? (string) $extra['value'] : '';
			}
		}
		return $data;
	}

	/**
	 * Inserts or updates the row of a user.
	 *
	 * @param int    $user_id    User ID.
	 * @param string $normalized Canonical phone.
	 * @return true|WP_Error
	 */
	public function write( $user_id, $normalized ) {
		if ( ! $this->is_usable() ) {
			return new WP_Error( 'mw24_eitaa_table_unusable', __( 'The custom table map is not valid.', 'mw24-eitaa' ) );
		}
		if ( $this->read_only ) {
			return new WP_Error( 'mw24_eitaa_table_read_only', __( 'The custom table map is read-only.', 'mw24-eitaa' ) );
		}
		global $wpdb;
		$value = $this->formatted( $normalized );
		$sql   = 'SELECT COUNT(*) FROM ' . self::quote( $this->table ) . ' WHERE ' . self::quote( $this->user_col ) . ' = %d';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- Identifiers are white-listed; values use placeholders.
		$exists = (int) $wpdb->get_var( $wpdb->prepare( $sql, (int) $user_id ) );
		if ( $exists > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update( $this->table, array( $this->phone_col => $value ), array( $this->user_col => (int) $user_id ), array( '%s' ), array( '%d' ) );
		} else {
			$data = array_merge(
				$this->extra_values( $user_id, $normalized ),
				array(
					$this->user_col  => (string) (int) $user_id,
					$this->phone_col => $value,
				)
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert( $this->table, $data, array_fill( 0, count( $data ), '%s' ) );
		}
		if ( false === $result ) {
			return new WP_Error( 'mw24_eitaa_table_write_failed', __( 'Writing to the custom table failed.', 'mw24-eitaa' ) );
		}
		return true;
	}

	/**
	 * Export as array.
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'enabled'   => $this->enabled,
			'table'     => $this->table,
			'phone_col' => $this->phone_col,
			'user_col'  => $this->user_col,
			'format'    => $this->format,
			'pattern'   => $this->pattern,
			'read_only' => $this->read_only,
			'extras'    => $this->extras,
		);
	}
}
