<?php
/**
 * Ready-made phone profiles for popular login/registration plugins.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Loads profiles/*.json and detects which of them apply to this site.
 *
 * Profile JSON:
 * {
 *   "id": "digits", "name": "Digits", "version": "1.0.0", "verified": true,
 *   "detect": { "plugins": ["digits"], "functions": [], "classes": [], "constants": [] },
 *   "fields": [
 *     { "meta_key": "digits_phone", "format": "+98", "lookup": true, "write": true },
 *     { "meta_key": "digt_countrycode", "value": "+98", "write": true },
 *     { "meta_key_like": "user_registration_phone%", "lookup": true }
 *   ],
 *   "suggest_like": ["%mobile%"],
 *   "notes": { "en": "...", "fa": "..." }
 * }
 * Developers can add/override profiles with the "mw24_eitaa_phone_profiles" filter.
 */
class MW24_Eitaa_Mapping_Profiles {

	/**
	 * Loaded profiles.
	 *
	 * @var array|null
	 */
	private static $profiles = null;

	/**
	 * Detection cache.
	 *
	 * @var array|null
	 */
	private static $detected = null;

	/**
	 * All profiles keyed by id.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$profiles ) {
			return self::$profiles;
		}
		$profiles = array();
		$files    = glob( MW24_EITAA_PATH . 'profiles/*.json' );
		foreach ( (array) $files as $file ) {
			$json    = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local bundled file.
			$profile = json_decode( (string) $json, true );
			$profile = self::sanitize_profile( $profile );
			if ( $profile ) {
				$profiles[ $profile['id'] ] = $profile;
			}
		}

		/**
		 * Filters the phone profiles. Return an array keyed by profile id; each value
		 * follows the profiles/*.json structure.
		 *
		 * @param array $profiles Profiles.
		 */
		$filtered = apply_filters( 'mw24_eitaa_phone_profiles', $profiles );
		$clean    = array();
		foreach ( (array) $filtered as $profile ) {
			$profile = self::sanitize_profile( $profile );
			if ( $profile ) {
				$clean[ $profile['id'] ] = $profile;
			}
		}
		uasort(
			$clean,
			static function ( $a, $b ) {
				return $a['priority'] <=> $b['priority'];
			}
		);
		self::$profiles = $clean;
		return self::$profiles;
	}

	/**
	 * Resets static caches (tests / after plugin changes).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$profiles = null;
		self::$detected = null;
	}

	/**
	 * Validates/normalizes a profile array.
	 *
	 * @param mixed $profile Raw profile.
	 * @return array|null
	 */
	public static function sanitize_profile( $profile ) {
		if ( ! is_array( $profile ) || empty( $profile['id'] ) ) {
			return null;
		}
		$id = sanitize_key( (string) $profile['id'] );
		if ( '' === $id ) {
			return null;
		}
		$fields = array();
		foreach ( isset( $profile['fields'] ) && is_array( $profile['fields'] ) ? $profile['fields'] : array() as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}
			$key  = isset( $field['meta_key'] ) ? preg_replace( '/[^A-Za-z0-9_\-:.]/', '', (string) $field['meta_key'] ) : '';
			$like = isset( $field['meta_key_like'] ) ? preg_replace( '/[^A-Za-z0-9_\-:.%\\\\]/', '', (string) $field['meta_key_like'] ) : '';
			if ( '' === $key && '' === $like ) {
				continue;
			}
			$fields[] = array(
				'meta_key'      => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Phone map definition, not a query argument.
				'meta_key_like' => '' === $key ? $like : '',
				'format'        => isset( $field['format'] ) && in_array( (string) $field['format'], MW24_Eitaa_Phone_Normalizer::FORMATS, true ) ? (string) $field['format'] : '0',
				'pattern'       => isset( $field['pattern'] ) ? (string) $field['pattern'] : '',
				'lookup'        => ! isset( $field['lookup'] ) || ! empty( $field['lookup'] ),
				'write'         => '' !== $key && ( ! isset( $field['write'] ) || ! empty( $field['write'] ) ),
				'value'         => isset( $field['value'] ) && is_scalar( $field['value'] ) ? (string) $field['value'] : null,
			);
		}
		$detect = isset( $profile['detect'] ) && is_array( $profile['detect'] ) ? $profile['detect'] : array();
		$clean  = array();
		foreach ( array( 'plugins', 'functions', 'classes', 'constants' ) as $type ) {
			$clean[ $type ] = isset( $detect[ $type ] ) ? array_values( array_filter( array_map( 'strval', (array) $detect[ $type ] ) ) ) : array();
		}
		$notes = array(
			'en' => '',
			'fa' => '',
		);
		if ( isset( $profile['notes'] ) ) {
			if ( is_array( $profile['notes'] ) ) {
				$notes['en'] = isset( $profile['notes']['en'] ) ? (string) $profile['notes']['en'] : '';
				$notes['fa'] = isset( $profile['notes']['fa'] ) ? (string) $profile['notes']['fa'] : '';
			} else {
				$notes['en'] = (string) $profile['notes'];
			}
		}
		return array(
			'id'           => $id,
			'name'         => isset( $profile['name'] ) ? sanitize_text_field( (string) $profile['name'] ) : $id,
			'version'      => isset( $profile['version'] ) ? sanitize_text_field( (string) $profile['version'] ) : '1.0.0',
			'verified'     => ! empty( $profile['verified'] ),
			'priority'     => isset( $profile['priority'] ) ? (int) $profile['priority'] : 50,
			'source'       => isset( $profile['source'] ) ? sanitize_text_field( (string) $profile['source'] ) : '',
			'detect'       => $clean,
			'fields'       => $fields,
			'suggest_like' => isset( $profile['suggest_like'] ) ? array_values( array_filter( array_map( 'strval', (array) $profile['suggest_like'] ) ) ) : array(),
			'notes'        => $notes,
		);
	}

	/**
	 * One profile.
	 *
	 * @param string $id Profile id.
	 * @return array|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Directory slugs of active plugins (site + network).
	 *
	 * @return string[]
	 */
	public static function active_plugin_slugs() {
		$plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$slugs = array();
		foreach ( $plugins as $plugin ) {
			$plugin  = (string) $plugin;
			$dir     = dirname( $plugin );
			$slugs[] = '.' === $dir ? basename( $plugin, '.php' ) : $dir;
		}
		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Whether a profile's plugin is present.
	 *
	 * @param array $profile Profile.
	 * @return bool
	 */
	public static function is_detected( array $profile ) {
		$detect = $profile['detect'];
		if ( ! empty( $detect['plugins'] ) && array_intersect( $detect['plugins'], self::active_plugin_slugs() ) ) {
			return true;
		}
		foreach ( $detect['functions'] as $function ) {
			if ( function_exists( $function ) ) {
				return true;
			}
		}
		foreach ( $detect['classes'] as $class ) {
			if ( class_exists( $class, false ) ) {
				return true;
			}
		}
		foreach ( $detect['constants'] as $constant ) {
			if ( defined( $constant ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ids of detected profiles.
	 *
	 * @return string[]
	 */
	public static function detected() {
		if ( null === self::$detected ) {
			self::$detected = array();
			foreach ( self::all() as $id => $profile ) {
				if ( self::is_detected( $profile ) ) {
					self::$detected[] = $id;
				}
			}
		}
		return self::$detected;
	}

	/**
	 * Builds meta maps for a profile.
	 *
	 * @param array $profile Profile.
	 * @return MW24_Eitaa_Mapping_Meta[]
	 */
	public static function maps_for( array $profile ) {
		$maps = array();
		foreach ( $profile['fields'] as $field ) {
			$key    = '' !== $field['meta_key'] ? $field['meta_key'] : $field['meta_key_like'];
			$maps[] = new MW24_Eitaa_Mapping_Meta(
				array(
					'id'            => 'profile:' . $profile['id'] . ':' . $key,
					'source'        => 'profile',
					'label'         => $profile['name'] . ' — ' . $key,
					'meta_key'      => $field['meta_key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Phone map definition, not a query argument.
					'meta_key_like' => $field['meta_key_like'],
					'format'        => $field['format'],
					'pattern'       => $field['pattern'],
					'lookup'        => $field['lookup'],
					'write'         => $field['write'],
					'value'         => $field['value'],
				)
			);
		}
		return $maps;
	}

	/**
	 * Notes in the current admin language.
	 *
	 * @param array $profile Profile.
	 * @return string
	 */
	public static function notes( array $profile ) {
		$locale = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		if ( 0 === strpos( (string) $locale, 'fa' ) && '' !== $profile['notes']['fa'] ) {
			return $profile['notes']['fa'];
		}
		return '' !== $profile['notes']['en'] ? $profile['notes']['en'] : $profile['notes']['fa'];
	}
}
