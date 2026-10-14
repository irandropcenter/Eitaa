<?php
/**
 * A phone map stored in wp_usermeta.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Describes one usermeta field that holds (or receives) the phone number.
 */
class MW24_Eitaa_Mapping_Meta {

	/**
	 * Unique id (e.g. "profile:digits:digits_phone").
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * Source: internal|profile|manual.
	 *
	 * @var string
	 */
	public $source = 'manual';

	/**
	 * Human readable label.
	 *
	 * @var string
	 */
	public $label = '';

	/**
	 * Exact meta key.
	 *
	 * @var string
	 */
	public $meta_key = '';

	/**
	 * SQL LIKE pattern (lookup only), used when meta_key is empty.
	 *
	 * @var string
	 */
	public $meta_key_like = '';

	/**
	 * Storage format (see MW24_Eitaa_Phone_Normalizer::FORMATS).
	 *
	 * @var string
	 */
	public $format = '0';

	/**
	 * Custom pattern for the "custom" format.
	 *
	 * @var string
	 */
	public $pattern = '';

	/**
	 * Used when searching users.
	 *
	 * @var bool
	 */
	public $lookup = true;

	/**
	 * Written when a phone is stored.
	 *
	 * @var bool
	 */
	public $write = true;

	/**
	 * Fixed helper value (e.g. "+98" country code); null for phone fields.
	 *
	 * @var string|null
	 */
	public $value = null;

	/**
	 * Constructor.
	 *
	 * @param array $args Properties.
	 */
	public function __construct( array $args ) {
		foreach ( array( 'id', 'source', 'label', 'meta_key', 'meta_key_like', 'format', 'pattern' ) as $prop ) {
			if ( isset( $args[ $prop ] ) && is_scalar( $args[ $prop ] ) ) {
				$this->$prop = (string) $args[ $prop ];
			}
		}
		if ( isset( $args['lookup'] ) ) {
			$this->lookup = (bool) $args['lookup'];
		}
		if ( isset( $args['write'] ) ) {
			$this->write = (bool) $args['write'];
		}
		if ( array_key_exists( 'value', $args ) && null !== $args['value'] ) {
			$this->value  = (string) $args['value'];
			$this->lookup = false;
		}
		if ( ! in_array( $this->format, MW24_Eitaa_Phone_Normalizer::FORMATS, true ) ) {
			$this->format = '0';
		}
		if ( '' !== $this->meta_key_like ) {
			$this->write = false;
		}
		if ( '' === $this->label ) {
			$this->label = '' !== $this->meta_key ? $this->meta_key : $this->meta_key_like;
		}
	}

	/**
	 * Fixed helper field (not a phone).
	 *
	 * @return bool
	 */
	public function is_fixed() {
		return null !== $this->value;
	}

	/**
	 * Pattern based (LIKE) lookup field.
	 *
	 * @return bool
	 */
	public function is_pattern() {
		return '' === $this->meta_key && '' !== $this->meta_key_like;
	}

	/**
	 * Value to store for a canonical phone.
	 *
	 * @param string $normalized Canonical phone.
	 * @return string
	 */
	public function formatted( $normalized ) {
		if ( $this->is_fixed() ) {
			return (string) $this->value;
		}
		return MW24_Eitaa_Phone_Normalizer::format( $normalized, $this->format, $this->pattern );
	}

	/**
	 * Values to search for (5 standard variants + this map's own format).
	 *
	 * @param string $normalized Canonical phone.
	 * @return string[]
	 */
	public function lookup_values( $normalized ) {
		$values   = MW24_Eitaa_Phone_Normalizer::variants( $normalized );
		$values[] = $this->formatted( $normalized );
		return array_values( array_unique( array_filter( $values, 'strlen' ) ) );
	}

	/**
	 * Whether a meta key belongs to this map.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public function matches_key( $key ) {
		if ( '' !== $this->meta_key ) {
			return $key === $this->meta_key;
		}
		if ( '' === $this->meta_key_like ) {
			return false;
		}
		$like   = $this->meta_key_like;
		$regex  = '';
		$length = strlen( $like );
		for ( $i = 0; $i < $length; $i++ ) {
			$char = $like[ $i ];
			if ( '\\' === $char && $i + 1 < $length ) {
				++$i;
				$regex .= preg_quote( $like[ $i ], '/' );
			} elseif ( '%' === $char ) {
				$regex .= '.*';
			} elseif ( '_' === $char ) {
				$regex .= '.';
			} else {
				$regex .= preg_quote( $char, '/' );
			}
		}
		return (bool) preg_match( '/^' . $regex . '$/i', (string) $key );
	}

	/**
	 * Reads the stored raw value for a user.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function read( $user_id ) {
		if ( '' !== $this->meta_key ) {
			$value = get_user_meta( (int) $user_id, $this->meta_key, true );
			return is_scalar( $value ) ? (string) $value : '';
		}
		if ( '' === $this->meta_key_like ) {
			return '';
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s AND meta_value <> '' ORDER BY umeta_id ASC LIMIT 1", (int) $user_id, $this->meta_key_like ) );
		return null === $value ? '' : (string) $value;
	}

	/**
	 * Export as array (for the admin UI / dry-run report).
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'            => $this->id,
			'source'        => $this->source,
			'label'         => $this->label,
			'meta_key'      => $this->meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Phone map definition, not a query argument.
			'meta_key_like' => $this->meta_key_like,
			'format'        => $this->format,
			'pattern'       => $this->pattern,
			'lookup'        => $this->lookup,
			'write'         => $this->write,
			'value'         => $this->value,
		);
	}

	/**
	 * Meta keys that are never offered in the discovery list.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public static function is_excluded_key( $key ) {
		global $wpdb;
		$exact = array(
			'session_tokens',
			'nickname',
			'first_name',
			'last_name',
			'description',
			'rich_editing',
			'syntax_highlighting',
			'comment_shortcuts',
			'admin_color',
			'use_ssl',
			'show_admin_bar_front',
			'locale',
			'dismissed_wp_pointers',
			'show_welcome_panel',
			'default_password_nag',
			'community-events-location',
			'wc_last_active',
			'last_update',
			'paying_customer',
			$wpdb->prefix . 'capabilities',
			$wpdb->prefix . 'user_level',
			$wpdb->prefix . 'user-settings',
			$wpdb->prefix . 'user-settings-time',
			$wpdb->prefix . 'dashboard_quick_press_last_post_id',
			'mw24_eitaa_user_id',
			'mw24_eitaa_allows_pm',
			'mw24_eitaa_first_name',
			'mw24_eitaa_linked_at',
			'mw24_eitaa_phone_verified_at',
			'mw24_eitaa_language',
			'mw24_eitaa_last_seen',
		);
		if ( in_array( $key, $exact, true ) ) {
			return true;
		}
		return (bool) preg_match( '/^(closedpostboxes_|metaboxhidden_|meta-box-order_|manage.*columnshidden|_woocommerce_persistent_cart|edit_.*_per_page|screen_layout_|mw24_eitaa_cart_)/', $key );
	}

	/**
	 * Lists meta keys that really exist in wp_usermeta with a count, a phone-likeness
	 * ratio and a masked sample. Cached in a transient; pass $refresh to rebuild.
	 *
	 * @param bool $refresh Rebuild the cache.
	 * @return array[]
	 */
	public static function discover_keys( $refresh = false ) {
		$cached = get_transient( 'mw24_eitaa_meta_keys' );
		if ( ! $refresh && is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$regex = '^[+]?[0-9][0-9 ()-]{8,17}$';
		$wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, COUNT(*) AS total,
					SUM( CASE WHEN meta_value REGEXP %s THEN 1 ELSE 0 END ) AS phone_like,
					MAX( CASE WHEN meta_value REGEXP %s THEN meta_value ELSE NULL END ) AS sample
				FROM {$wpdb->usermeta}
				GROUP BY meta_key
				ORDER BY phone_like DESC, total DESC
				LIMIT 400",
				$regex,
				$regex
			),
			ARRAY_A
		);
		if ( empty( $rows ) && '' !== $wpdb->last_error ) {
			// Database without REGEXP support: plain counts, phone-likeness computed from a sample.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( "SELECT meta_key, COUNT(*) AS total, NULL AS phone_like, MAX(meta_value) AS sample FROM {$wpdb->usermeta} GROUP BY meta_key ORDER BY total DESC LIMIT 400", ARRAY_A );
		}
		$wpdb->suppress_errors( false );

		$keys = array();
		foreach ( (array) $rows as $row ) {
			$key = (string) $row['meta_key'];
			if ( '' === $key || self::is_excluded_key( $key ) ) {
				continue;
			}
			$total      = (int) $row['total'];
			$sample     = isset( $row['sample'] ) ? (string) $row['sample'] : '';
			$phone_like = null === $row['phone_like'] ? ( MW24_Eitaa_Phone_Normalizer::is_valid( $sample ) ? $total : 0 ) : (int) $row['phone_like'];
			$is_phone   = MW24_Eitaa_Phone_Normalizer::is_valid( $sample );
			$keys[]     = array(
				'key'        => $key,
				'total'      => $total,
				'phone_like' => $phone_like,
				'ratio'      => $total > 0 ? (int) round( 100 * $phone_like / $total ) : 0,
				'sample'     => $is_phone ? MW24_Eitaa_Phone_Normalizer::mask( $sample ) : '',
				'format'     => $is_phone ? MW24_Eitaa_Phone_Normalizer::detect_format( $sample ) : '',
			);
		}
		usort(
			$keys,
			static function ( $a, $b ) {
				if ( $a['phone_like'] !== $b['phone_like'] ) {
					return $b['phone_like'] <=> $a['phone_like'];
				}
				return $b['total'] <=> $a['total'];
			}
		);
		set_transient( 'mw24_eitaa_meta_keys', $keys, 12 * HOUR_IN_SECONDS );
		return $keys;
	}
}
