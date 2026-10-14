<?php
/**
 * Phone map engine: finds users by phone in every configured place and writes
 * verified numbers back to all of them.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Phone map engine.
 *
 * Sources (all can be active at once, in this priority order):
 *  1. internal   - mw24_eitaa_phone (always on)
 *  2. profiles   - ready-made profiles (auto detected, e.g. Digits, WooCommerce)
 *  3. manual     - usermeta keys picked by the admin (+ fixed helper fields)
 *  4. table      - one custom table
 *  5. core       - user_login / user_email (optional)
 */
class MW24_Eitaa_Phone_Mapper {

	const OPTION       = 'mw24_eitaa_mapping';
	const INTERNAL_KEY = 'mw24_eitaa_phone';

	/**
	 * Logger.
	 *
	 * @var MW24_Eitaa_Logger
	 */
	private $logger;

	/**
	 * Maps.
	 *
	 * @var MW24_Eitaa_Mapping_Meta[]|null
	 */
	private $maps = null;

	/**
	 * Table.
	 *
	 * @var MW24_Eitaa_Mapping_Table|null|false
	 */
	private $table = false;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Logger $logger Logger.
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Default mapping configuration.
	 *
	 * @return array
	 */
	public static function default_config() {
		return array(
			'profiles'          => array(),
			'meta'              => array(),
			'extras'            => array(),
			'table'             => array(
				'enabled'   => false,
				'table'     => '',
				'phone_col' => '',
				'user_col'  => '',
				'format'    => '0',
				'pattern'   => '',
				'read_only' => true,
				'extras'    => array(),
			),
			'match_login_email' => true,
		);
	}

	/**
	 * Current configuration.
	 *
	 * @return array
	 */
	public function get_config() {
		$stored = get_option( self::OPTION, array() );
		$config = array_merge( self::default_config(), is_array( $stored ) ? $stored : array() );
		if ( ! is_array( $config['table'] ) ) {
			$config['table'] = self::default_config()['table'];
		}
		$config['table'] = array_merge( self::default_config()['table'], $config['table'] );
		return $config;
	}

	/**
	 * Saves a sanitized configuration.
	 *
	 * @param array $config Config (output of sanitize_config()).
	 * @return void
	 */
	public function save_config( array $config ) {
		update_option( self::OPTION, $config, false );
		$this->flush();
	}

	/**
	 * Clears cached maps.
	 *
	 * @return void
	 */
	public function flush() {
		$this->maps  = null;
		$this->table = false;
	}

	/**
	 * Sanitizes raw form input into a configuration.
	 *
	 * @param array $raw Raw (unslashed) input.
	 * @return array|WP_Error
	 */
	public function sanitize_config( array $raw ) {
		$config = self::default_config();

		foreach ( MW24_Eitaa_Mapping_Profiles::all() as $id => $profile ) {
			$config['profiles'][ $id ] = array(
				'active'    => ! empty( $raw['profiles'][ $id ]['active'] ),
				'confirmed' => true,
			);
		}

		foreach ( isset( $raw['meta'] ) && is_array( $raw['meta'] ) ? $raw['meta'] : array() as $row ) {
			$key = isset( $row['key'] ) ? substr( preg_replace( '/[^A-Za-z0-9_\-:.]/', '', (string) $row['key'] ), 0, 191 ) : '';
			if ( '' === $key || self::INTERNAL_KEY === $key ) {
				continue;
			}
			$format           = isset( $row['format'] ) && in_array( (string) $row['format'], MW24_Eitaa_Phone_Normalizer::FORMATS, true ) ? (string) $row['format'] : '0';
			$config['meta'][] = array(
				'key'     => $key,
				'format'  => $format,
				'pattern' => 'custom' === $format ? self::sanitize_pattern( isset( $row['pattern'] ) ? $row['pattern'] : '' ) : '',
				'write'   => ! empty( $row['write'] ),
			);
		}

		foreach ( isset( $raw['extras'] ) && is_array( $raw['extras'] ) ? $raw['extras'] : array() as $row ) {
			$key = isset( $row['key'] ) ? substr( preg_replace( '/[^A-Za-z0-9_\-:.]/', '', (string) $row['key'] ), 0, 191 ) : '';
			if ( '' === $key || self::INTERNAL_KEY === $key ) {
				continue;
			}
			$config['extras'][] = array(
				'key'   => $key,
				'value' => sanitize_text_field( isset( $row['value'] ) ? (string) $row['value'] : '' ),
			);
		}

		$table = isset( $raw['table'] ) && is_array( $raw['table'] ) ? $raw['table'] : array();
		if ( ! empty( $table['enabled'] ) || ! empty( $table['table'] ) ) {
			$format          = isset( $table['format'] ) && in_array( (string) $table['format'], MW24_Eitaa_Phone_Normalizer::FORMATS, true ) ? (string) $table['format'] : '0';
			$config['table'] = array(
				'enabled'   => ! empty( $table['enabled'] ),
				'table'     => isset( $table['table'] ) ? (string) $table['table'] : '',
				'phone_col' => isset( $table['phone_col'] ) ? (string) $table['phone_col'] : '',
				'user_col'  => isset( $table['user_col'] ) ? (string) $table['user_col'] : '',
				'format'    => $format,
				'pattern'   => 'custom' === $format ? self::sanitize_pattern( isset( $table['pattern'] ) ? $table['pattern'] : '' ) : '',
				'read_only' => ! empty( $table['read_only'] ),
				'extras'    => array(),
			);
			foreach ( isset( $table['extras'] ) && is_array( $table['extras'] ) ? $table['extras'] : array() as $extra ) {
				$col  = isset( $extra['col'] ) ? (string) $extra['col'] : '';
				$type = isset( $extra['type'] ) && in_array( (string) $extra['type'], MW24_Eitaa_Mapping_Table::EXTRA_TYPES, true ) ? (string) $extra['type'] : '';
				if ( '' === $col || '' === $type ) {
					continue;
				}
				$config['table']['extras'][] = array(
					'col'   => $col,
					'type'  => $type,
					'value' => 'fixed' === $type ? sanitize_text_field( isset( $extra['value'] ) ? (string) $extra['value'] : '' ) : '',
				);
			}
			if ( '' !== $config['table']['table'] ) {
				$check = ( new MW24_Eitaa_Mapping_Table( $config['table'] ) )->validate();
				if ( is_wp_error( $check ) ) {
					return $check;
				}
			}
		}

		$config['match_login_email'] = ! empty( $raw['match_login_email'] );
		return $config;
	}

	/**
	 * Allows only safe characters in custom patterns.
	 *
	 * @param mixed $pattern Raw pattern.
	 * @return string
	 */
	public static function sanitize_pattern( $pattern ) {
		$pattern = is_scalar( $pattern ) ? (string) $pattern : '';
		return substr( preg_replace( '/[^0-9A-Za-z+\-(){} ._]/', '', $pattern ), 0, 64 );
	}

	/**
	 * SQL expression that strips common separators (space, dash, parentheses, dot)
	 * from a column so "0912 123-4567" matches "09121234567".
	 *
	 * @param string $column Column expression (trusted, built by the plugin).
	 * @return string
	 */
	public static function sql_strip( $column ) {
		return "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$column}, ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')";
	}

	/**
	 * Effective state of a profile: active when detected (auto default) unless the
	 * admin disabled it; undetected profiles are active only when enabled manually.
	 *
	 * @param string $id Profile id.
	 * @return bool
	 */
	public function profile_active( $id ) {
		$config = $this->get_config();
		if ( isset( $config['profiles'][ $id ] ) && is_array( $config['profiles'][ $id ] ) ) {
			return ! empty( $config['profiles'][ $id ]['active'] );
		}
		return in_array( $id, MW24_Eitaa_Mapping_Profiles::detected(), true );
	}

	/**
	 * Whether the admin confirmed a profile.
	 *
	 * @param string $id Profile id.
	 * @return bool
	 */
	public function profile_confirmed( $id ) {
		$config = $this->get_config();
		return ! empty( $config['profiles'][ $id ]['confirmed'] );
	}

	/**
	 * Detected profiles that the admin has not reviewed yet.
	 *
	 * @return string[]
	 */
	public function unconfirmed_profiles() {
		$out = array();
		foreach ( MW24_Eitaa_Mapping_Profiles::detected() as $id ) {
			if ( ! $this->profile_confirmed( $id ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * All active usermeta maps, de-duplicated by meta key (first wins).
	 *
	 * @return MW24_Eitaa_Mapping_Meta[]
	 */
	public function meta_maps() {
		if ( null !== $this->maps ) {
			return $this->maps;
		}
		$config = $this->get_config();
		$maps   = array(
			new MW24_Eitaa_Mapping_Meta(
				array(
					'id'       => 'internal',
					'source'   => 'internal',
					'label'    => 'Eitaa Connect — ' . self::INTERNAL_KEY,
					'meta_key' => self::INTERNAL_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Phone map definition, not a query argument.
					'format'   => '0',
				)
			),
		);
		foreach ( MW24_Eitaa_Mapping_Profiles::all() as $id => $profile ) {
			if ( $this->profile_active( $id ) ) {
				$maps = array_merge( $maps, MW24_Eitaa_Mapping_Profiles::maps_for( $profile ) );
			}
		}
		foreach ( $config['meta'] as $index => $row ) {
			$maps[] = new MW24_Eitaa_Mapping_Meta(
				array(
					'id'       => 'manual:' . $index,
					'source'   => 'manual',
					'meta_key' => $row['key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Phone map definition, not a query argument.
					'format'   => $row['format'],
					'pattern'  => $row['pattern'],
					'lookup'   => true,
					'write'    => ! empty( $row['write'] ),
				)
			);
		}
		foreach ( $config['extras'] as $index => $row ) {
			$maps[] = new MW24_Eitaa_Mapping_Meta(
				array(
					'id'       => 'extra:' . $index,
					'source'   => 'manual',
					'meta_key' => $row['key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Phone map definition, not a query argument.
					'value'    => $row['value'],
					'write'    => true,
				)
			);
		}
		$seen   = array();
		$unique = array();
		foreach ( $maps as $map ) {
			$signature = '' !== $map->meta_key ? 'k:' . $map->meta_key : 'l:' . $map->meta_key_like;
			if ( isset( $seen[ $signature ] ) ) {
				continue;
			}
			$seen[ $signature ] = true;
			$unique[]           = $map;
		}

		/**
		 * Filters the active usermeta phone maps.
		 *
		 * @param MW24_Eitaa_Mapping_Meta[] $unique Maps.
		 */
		$this->maps = (array) apply_filters( 'mw24_eitaa_phone_maps', $unique );
		return $this->maps;
	}

	/**
	 * Custom table map when enabled and valid.
	 *
	 * @return MW24_Eitaa_Mapping_Table|null
	 */
	public function table_map() {
		if ( false === $this->table ) {
			$config      = $this->get_config();
			$table       = new MW24_Eitaa_Mapping_Table( $config['table'] );
			$this->table = $table->enabled ? $table : null;
		}
		return $this->table;
	}

	/**
	 * Finds the user that owns a phone number.
	 *
	 * @param string $phone       Any phone representation.
	 * @param int    $exclude_user Ignore this user (uniqueness checks).
	 * @return array{user_id:int,normalized:string,matches:array,conflict:bool}
	 */
	public function find_user( $phone, $exclude_user = 0 ) {
		$normalized = MW24_Eitaa_Phone_Normalizer::normalize( $phone );
		$result     = array(
			'user_id'    => 0,
			'normalized' => $normalized,
			'matches'    => array(),
			'conflict'   => false,
		);
		if ( '' === $normalized ) {
			return $result;
		}
		global $wpdb;
		$matches = array();

		// 1-3. usermeta maps in one query.
		$lookup = array_filter(
			$this->meta_maps(),
			static function ( $map ) {
				return $map->lookup && ! $map->is_fixed();
			}
		);
		$keys   = array();
		$likes  = array();
		$values = MW24_Eitaa_Phone_Normalizer::variants( $normalized );
		foreach ( $lookup as $map ) {
			if ( $map->is_pattern() ) {
				$likes[] = $map->meta_key_like;
			} else {
				$keys[] = $map->meta_key;
			}
			$values = array_merge( $values, $map->lookup_values( $normalized ) );
		}
		$values = array_values( array_unique( $values ) );
		if ( $keys || $likes ) {
			$where_key = array();
			$params    = array();
			if ( $keys ) {
				$where_key[] = 'um.meta_key IN (' . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ')';
				$params      = array_merge( $params, $keys );
			}
			foreach ( $likes as $like ) {
				$where_key[] = 'um.meta_key LIKE %s';
				$params[]    = $like;
			}
			$in     = implode( ',', array_fill( 0, count( $values ), '%s' ) );
			$params = array_merge( $params, $values, $values );
			$sql    = "SELECT um.user_id, um.meta_key FROM {$wpdb->usermeta} um INNER JOIN {$wpdb->users} u ON u.ID = um.user_id"
				. ' WHERE (' . implode( ' OR ', $where_key ) . ')'
				. " AND ( um.meta_value IN ($in) OR " . self::sql_strip( 'um.meta_value' ) . " IN ($in) )";
			if ( $exclude_user > 0 ) {
				$sql     .= ' AND um.user_id <> %d';
				$params[] = (int) $exclude_user;
			}
			$sql .= ' ORDER BY um.user_id ASC LIMIT 100';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders -- SQL built only from placeholders and trusted expressions.
			$rows = (array) $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
			foreach ( $lookup as $map ) {
				foreach ( $rows as $row ) {
					if ( $map->matches_key( (string) $row['meta_key'] ) ) {
						$matches[] = array(
							'map'     => $map->id,
							'label'   => $map->label,
							'user_id' => (int) $row['user_id'],
						);
					}
				}
			}
		}

		// 4. Custom table.
		$table = $this->table_map();
		if ( $table && $table->is_usable() ) {
			$table_values = array_values( array_unique( array_merge( MW24_Eitaa_Phone_Normalizer::variants( $normalized ), array( $table->formatted( $normalized ) ) ) ) );
			foreach ( $table->find_users( $table_values, $exclude_user ) as $user_id ) {
				$matches[] = array(
					'map'     => 'table',
					'label'   => $table->table . '.' . $table->phone_col,
					'user_id' => $user_id,
				);
			}
		}

		// 5. user_login / user_email.
		$config = $this->get_config();
		if ( ! empty( $config['match_login_email'] ) ) {
			$variants = MW24_Eitaa_Phone_Normalizer::variants( $normalized );
			$params   = $variants;
			$sql      = "SELECT ID FROM {$wpdb->users} WHERE user_login IN (" . implode( ',', array_fill( 0, count( $variants ), '%s' ) ) . ')';
			if ( $exclude_user > 0 ) {
				$sql     .= ' AND ID <> %d';
				$params[] = (int) $exclude_user;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders -- SQL built only from placeholders and trusted expressions.
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( $sql . ' LIMIT 10', $params ) ) as $user_id ) {
				$matches[] = array(
					'map'     => 'user_login',
					'label'   => 'user_login',
					'user_id' => (int) $user_id,
				);
			}
			$likes  = array();
			$params = array();
			foreach ( $variants as $variant ) {
				$likes[]  = 'user_email LIKE %s';
				$params[] = $wpdb->esc_like( $variant ) . '@%';
			}
			$sql = "SELECT ID FROM {$wpdb->users} WHERE (" . implode( ' OR ', $likes ) . ')';
			if ( $exclude_user > 0 ) {
				$sql     .= ' AND ID <> %d';
				$params[] = (int) $exclude_user;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders -- SQL built only from placeholders and trusted expressions.
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( $sql . ' LIMIT 10', $params ) ) as $user_id ) {
				$matches[] = array(
					'map'     => 'user_email',
					'label'   => 'user_email',
					'user_id' => (int) $user_id,
				);
			}
		}

		$user_ids           = array_values( array_unique( wp_list_pluck( $matches, 'user_id' ) ) );
		$result['matches']  = $matches;
		$result['conflict'] = count( $user_ids ) > 1;
		$user_id            = $user_ids ? (int) $user_ids[0] : 0;

		/**
		 * Filters the user resolved for a phone number.
		 *
		 * @param int    $user_id    Chosen user (first match by priority) or 0.
		 * @param array  $matches    All matches: map, label, user_id.
		 * @param string $normalized Canonical phone.
		 */
		$result['user_id'] = (int) apply_filters( 'mw24_eitaa_resolve_user', $user_id, $matches, $normalized );
		return $result;
	}

	/**
	 * Reads the first valid phone of a user across all maps.
	 *
	 * @param int $user_id User ID.
	 * @return string Canonical phone or ''.
	 */
	public function read_phone( $user_id ) {
		foreach ( $this->meta_maps() as $map ) {
			if ( ! $map->lookup || $map->is_fixed() ) {
				continue;
			}
			$normalized = MW24_Eitaa_Phone_Normalizer::normalize( $map->read( $user_id ) );
			if ( '' !== $normalized ) {
				return $normalized;
			}
		}
		$table = $this->table_map();
		if ( $table && $table->is_usable() ) {
			$normalized = MW24_Eitaa_Phone_Normalizer::normalize( $table->read( $user_id ) );
			if ( '' !== $normalized ) {
				return $normalized;
			}
		}
		return '';
	}

	/**
	 * Writes a phone number to every writable map of a user.
	 *
	 * @param int    $user_id   User ID (0 = simulate for a not-yet-created user).
	 * @param string $phone     Phone (any representation).
	 * @param bool   $dry_run   Only report what would happen.
	 * @param bool   $overwrite Replace a different existing number (true for Eitaa-verified numbers).
	 * @return array[] Report rows: map, label, value, action, current.
	 */
	public function write_phone( $user_id, $phone, $dry_run = false, $overwrite = true ) {
		$user_id    = (int) $user_id;
		$normalized = MW24_Eitaa_Phone_Normalizer::normalize( $phone );
		$report     = array();
		if ( '' === $normalized ) {
			return $report;
		}
		global $wpdb;
		foreach ( $this->meta_maps() as $map ) {
			if ( ! $map->write || '' === $map->meta_key ) {
				continue;
			}
			$value   = $map->formatted( $normalized );
			$current = $user_id > 0 ? $map->read( $user_id ) : '';
			$row     = array(
				'map'     => $map->id,
				'label'   => $map->label,
				'value'   => $value,
				'current' => $current,
				'action'  => 'write',
			);
			if ( $current === $value ) {
				$row['action'] = 'unchanged';
				$report[]      = $row;
				continue;
			}
			if ( ! $map->is_fixed() ) {
				$current_normalized = MW24_Eitaa_Phone_Normalizer::normalize( $current );
				if ( '' !== $current && '' !== $current_normalized && $current_normalized !== $normalized && ! $overwrite ) {
					$row['action'] = 'conflict';
					$report[]      = $row;
					continue;
				}
				// Uniqueness: never give a number that another user already owns in this field.
				$values       = $map->lookup_values( $normalized );
				$placeholders = implode( ',', array_fill( 0, count( $values ), '%s' ) );
				$params       = array_merge( array( $map->meta_key ), $values, $values, array( $user_id ) );
				$stripped     = self::sql_strip( 'meta_value' );
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders -- SQL built only from placeholders and trusted expressions.
				$owner = (int) $wpdb->get_var( $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND ( meta_value IN ($placeholders) OR {$stripped} IN ($placeholders) ) AND user_id <> %d LIMIT 1", $params ) );
				if ( $owner > 0 ) {
					$row['action'] = 'duplicate';
					$row['owner']  = $owner;
					$report[]      = $row;
					continue;
				}
			}
			if ( ! $dry_run && $user_id > 0 ) {
				update_user_meta( $user_id, $map->meta_key, $value );
			}
			$report[] = $row;
		}

		$table = $this->table_map();
		if ( $table ) {
			$value   = $table->formatted( $normalized );
			$current = $user_id > 0 ? $table->read( $user_id ) : '';
			$row     = array(
				'map'     => 'table',
				'label'   => $table->table . '.' . $table->phone_col,
				'value'   => $value,
				'current' => $current,
				'action'  => 'write',
			);
			if ( ! $table->is_usable() ) {
				$row['action'] = 'invalid';
			} elseif ( $table->read_only ) {
				$row['action'] = 'read_only';
			} elseif ( $current === $value ) {
				$row['action'] = 'unchanged';
			} elseif ( '' !== $current && MW24_Eitaa_Phone_Normalizer::normalize( $current ) !== $normalized && ! $overwrite ) {
				$row['action'] = 'conflict';
			} elseif ( $table->find_users( array_values( array_unique( array_merge( MW24_Eitaa_Phone_Normalizer::variants( $normalized ), array( $value ) ) ) ), $user_id ) ) {
				$row['action'] = 'duplicate';
			} elseif ( ! $dry_run && $user_id > 0 ) {
				$written = $table->write( $user_id, $normalized );
				if ( is_wp_error( $written ) ) {
					$row['action'] = 'error';
					$row['error']  = $written->get_error_message();
				}
			}
			$report[] = $row;
		}

		if ( ! $dry_run && $user_id > 0 ) {
			foreach ( $report as $row ) {
				if ( in_array( $row['action'], array( 'duplicate', 'error' ), true ) ) {
					$this->logger->warning(
						'mapping.write_skipped',
						sprintf( 'Phone not written to %s (%s).', $row['label'], $row['action'] ),
						array(
							'user_id' => $user_id,
							'phone'   => $normalized,
						)
					);
				}
			}
			/**
			 * Fires after a phone number was written to all maps.
			 *
			 * @param int    $user_id    User ID.
			 * @param string $normalized Canonical phone.
			 * @param array  $report     Report rows.
			 */
			do_action( 'mw24_eitaa_phone_written', $user_id, $normalized, $report );
		}
		return $report;
	}

	/**
	 * Dry-run used by the admin tool: who would be found and what would be written.
	 *
	 * @param string $phone Phone.
	 * @return array|WP_Error
	 */
	public function dry_run( $phone ) {
		$normalized = MW24_Eitaa_Phone_Normalizer::normalize( $phone );
		if ( '' === $normalized ) {
			return new WP_Error( 'mw24_eitaa_phone_invalid', __( 'This is not a valid Iranian mobile number.', 'mw24-eitaa' ) );
		}
		$found  = $this->find_user( $normalized );
		$user   = $found['user_id'] ? get_userdata( $found['user_id'] ) : false;
		$writes = $this->write_phone( $found['user_id'], $normalized, true, true );
		$maps   = array();
		foreach ( $this->meta_maps() as $map ) {
			$maps[] = $map->to_array();
		}
		$table = $this->table_map();
		return array(
			'normalized' => $normalized,
			'variants'   => MW24_Eitaa_Phone_Normalizer::variants( $normalized ),
			'user'       => $user ? array(
				'id'           => $user->ID,
				'login'        => $user->user_login,
				'display_name' => $user->display_name,
				'edit_link'    => get_edit_user_link( $user->ID ),
			) : null,
			'matches'    => $found['matches'],
			'conflict'   => $found['conflict'],
			'writes'     => $writes,
			'maps'       => $maps,
			'table'      => $table ? $table->to_array() : null,
		);
	}

	/**
	 * Short summary for the dashboard / status endpoint.
	 *
	 * @return array
	 */
	public function summary() {
		$active = array();
		foreach ( MW24_Eitaa_Mapping_Profiles::all() as $id => $profile ) {
			if ( $this->profile_active( $id ) ) {
				$active[] = $profile['name'];
			}
		}
		$config = $this->get_config();
		$table  = $this->table_map();
		return array(
			'profiles'          => $active,
			'detected'          => MW24_Eitaa_Mapping_Profiles::detected(),
			'manual'            => count( $config['meta'] ),
			'extras'            => count( $config['extras'] ),
			'table'             => $table ? ( $table->is_usable() ? 'ok' : 'invalid' ) : 'off',
			'match_login_email' => ! empty( $config['match_login_email'] ),
		);
	}
}
