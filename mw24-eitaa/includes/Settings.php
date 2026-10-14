<?php
/**
 * Settings storage, schema, defaults and sanitization.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings repository.
 *
 * All regular settings live in one option (mw24_eitaa_settings). The app token is
 * stored separately (encrypted, never autoloaded, never printed) and runtime state
 * lives in mw24_eitaa_state so that settings forms can never overwrite it.
 */
class MW24_Eitaa_Settings {

	const OPTION       = 'mw24_eitaa_settings';
	const TOKEN_OPTION = 'mw24_eitaa_app_token';
	const STATE_OPTION = 'mw24_eitaa_state';

	/**
	 * Cached raw option.
	 *
	 * @var array|null
	 */
	private $values = null;

	/**
	 * Cached decrypted token.
	 *
	 * @var string|null
	 */
	private $token = null;

	/**
	 * Field schema. Text defaults that must be translated are resolved lazily
	 * (see translated_default()) so nothing calls __() before init.
	 *
	 * @return array
	 */
	public static function schema() {
		static $schema = null;
		if ( null !== $schema ) {
			return $schema;
		}
		$b      = static function ( $section, $fallback ) {
			return array(
				'section' => $section,
				'type'    => 'bool',
				'default' => $fallback,
			);
		};
		$schema = array(
			// Connection.
			'enabled'                   => $b( 'connection', false ),
			'entry_path'                => array(
				'section' => 'connection',
				'type'    => 'path',
				'default' => '/',
			),
			'detect_heuristic'          => $b( 'connection', false ),
			'heuristic_patterns'        => array(
				'section' => 'connection',
				'type'    => 'text',
				'default' => 'Eitaa',
			),
			'max_age_hours'             => array(
				'section' => 'connection',
				'type'    => 'int',
				'default' => 24,
				'min'     => 1,
				'max'     => 168,
			),
			'remote_verify'             => $b( 'connection', false ),
			'ip_header'                 => array(
				'section' => 'connection',
				'type'    => 'select',
				'default' => 'REMOTE_ADDR',
				'options' => array( 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_AR_REAL_IP' ),
			),
			'frame_headers'             => $b( 'connection', true ),
			'frame_ancestors'           => array(
				'section' => 'connection',
				'type'    => 'text',
				'default' => 'https://eitaa.com https://*.eitaa.com https://eitaa.ir https://*.eitaa.ir',
			),
			'samesite_none'             => $b( 'connection', false ),
			'sdk_strict_first'          => $b( 'connection', false ),
			'url_registered'            => $b( 'connection', false ),

			// Appearance & behaviour.
			'expand'                    => $b( 'appearance', true ),
			'fullscreen'                => $b( 'appearance', false ),
			'back_button'               => $b( 'appearance', true ),
			'main_button'               => $b( 'appearance', true ),
			'main_button_woo'           => $b( 'appearance', true ),
			'main_button_text'          => array(
				'section' => 'appearance',
				'type'    => 'text',
				'default' => '',
			),
			'main_button_url'           => array(
				'section' => 'appearance',
				'type'    => 'url',
				'default' => '',
			),
			'main_button_color'         => array(
				'section' => 'appearance',
				'type'    => 'color',
				'default' => '',
			),
			'main_button_text_color'    => array(
				'section' => 'appearance',
				'type'    => 'color',
				'default' => '',
			),
			'secondary_button'          => $b( 'appearance', false ),
			'secondary_button_text'     => array(
				'section' => 'appearance',
				'type'    => 'text',
				'default' => '',
			),
			'secondary_button_url'      => array(
				'section' => 'appearance',
				'type'    => 'url',
				'default' => '',
			),
			'secondary_button_position' => array(
				'section' => 'appearance',
				'type'    => 'select',
				'default' => 'left',
				'options' => array( 'left', 'right', 'top', 'bottom' ),
			),
			'settings_button'           => $b( 'appearance', true ),
			'settings_button_url'       => array(
				'section' => 'appearance',
				'type'    => 'url',
				'default' => '',
			),
			'closing_confirmation'      => array(
				'section' => 'appearance',
				'type'    => 'select',
				'default' => 'cart',
				'options' => array( 'off', 'cart', 'always' ),
			),
			'header_color'              => array(
				'section' => 'appearance',
				'type'    => 'color',
				'default' => '',
			),
			'bg_color'                  => array(
				'section' => 'appearance',
				'type'    => 'color',
				'default' => '',
			),
			'bottom_bar_color'          => array(
				'section' => 'appearance',
				'type'    => 'color',
				'default' => '',
			),
			'haptic'                    => $b( 'appearance', true ),
			'disable_vertical_swipes'   => $b( 'appearance', false ),
			'safe_area'                 => $b( 'appearance', true ),
			'hide_selectors'            => array(
				'section' => 'appearance',
				'type'    => 'selectors',
				'default' => '',
			),
			'custom_css'                => array(
				'section' => 'appearance',
				'type'    => 'css',
				'default' => '',
			),
			'start_routes'              => array(
				'section' => 'appearance',
				'type'    => 'routes',
				'default' => '',
			),

			// Login.
			'login_enabled'             => $b( 'login', true ),
			'allow_registration'        => $b( 'login', true ),
			'default_role'              => array(
				'section' => 'login',
				'type'    => 'role',
				'default' => '',
			),
			'prompt_mode'               => array(
				'section' => 'login',
				'type'    => 'select',
				'default' => 'immediate',
				'options' => array( 'immediate', 'cart_checkout', 'button' ),
			),
			'prompt_direct'             => $b( 'login', false ),
			'bar_text'                  => array(
				'section' => 'login',
				'type'    => 'text',
				'default' => null,
			),
			'share_label'               => array(
				'section' => 'login',
				'type'    => 'text',
				'default' => null,
			),
			'later_label'               => array(
				'section' => 'login',
				'type'    => 'text',
				'default' => null,
			),
			'button_label'              => array(
				'section' => 'login',
				'type'    => 'text',
				'default' => null,
			),
			'button_wp_login'           => $b( 'login', true ),
			'button_woo_login'          => $b( 'login', true ),
			'outside_behavior'          => array(
				'section' => 'login',
				'type'    => 'select',
				'default' => 'hint',
				'options' => array( 'hide', 'hint' ),
			),
			'outside_hint'              => array(
				'section' => 'login',
				'type'    => 'text',
				'default' => null,
			),
			'miniapp_link'              => array(
				'section' => 'login',
				'type'    => 'url',
				'default' => '',
			),
			'trusted_device'            => $b( 'login', true ),
			'trusted_days'              => array(
				'section' => 'login',
				'type'    => 'int',
				'default' => 60,
				'min'     => 1,
				'max'     => 365,
			),
			'allow_privileged'          => $b( 'login', false ),
			'request_write_access'      => $b( 'login', true ),
			'sync_name'                 => $b( 'login', true ),
			'after_login_url'           => array(
				'section' => 'login',
				'type'    => 'url',
				'default' => '',
			),

			// Notifications.
			'send_mode'                 => array(
				'section' => 'notifications',
				'type'    => 'select',
				'default' => 'queue',
				'options' => array( 'queue', 'sync' ),
			),
			'queue_rate'                => array(
				'section' => 'notifications',
				'type'    => 'int',
				'default' => 30,
				'min'     => 1,
				'max'     => 600,
			),
			'only_allows_pm'            => $b( 'notifications', true ),
			'notify_welcome'            => $b( 'notifications', true ),
			'tpl_welcome'               => array(
				'section' => 'notifications',
				'type'    => 'template',
				'default' => null,
			),
			'notify_admin_register'     => $b( 'notifications', false ),
			'tpl_admin_register'        => array(
				'section' => 'notifications',
				'type'    => 'template',
				'default' => null,
			),
			'notify_login'              => $b( 'notifications', false ),
			'tpl_login'                 => array(
				'section' => 'notifications',
				'type'    => 'template',
				'default' => null,
			),
			'notify_password'           => $b( 'notifications', true ),
			'tpl_password'              => array(
				'section' => 'notifications',
				'type'    => 'template',
				'default' => null,
			),
			'notify_order_status'       => $b( 'notifications', true ),
			'order_statuses'            => array(
				'section' => 'notifications',
				'type'    => 'statuses',
				'default' => array( 'processing', 'completed', 'on-hold', 'cancelled', 'refunded' ),
			),
			'tpl_order_status'          => array(
				'section' => 'notifications',
				'type'    => 'template',
				'default' => null,
			),
			'tpl_order_status_map'      => array(
				'section' => 'notifications',
				'type'    => 'template_map',
				'default' => array(),
			),
			'notify_admin_new_order'    => $b( 'notifications', false ),
			'tpl_admin_new_order'       => array(
				'section' => 'notifications',
				'type'    => 'template',
				'default' => null,
			),
			'admin_linked_recipients'   => $b( 'notifications', true ),
			'admin_chat_ids'            => array(
				'section' => 'notifications',
				'type'    => 'chat_ids',
				'default' => '',
			),
			'notify_customer_note'      => $b( 'notifications', true ),
			'tpl_customer_note'         => array(
				'section' => 'notifications',
				'type'    => 'template',
				'default' => null,
			),
			'notify_tracking'           => $b( 'notifications', false ),
			'tracking_meta_key'         => array(
				'section' => 'notifications',
				'type'    => 'meta_key',
				'default' => '',
			),
			'tpl_tracking'              => array(
				'section' => 'notifications',
				'type'    => 'template',
				'default' => null,
			),
			'notify_abandoned_cart'     => $b( 'notifications', false ),
			'abandoned_hours'           => array(
				'section' => 'notifications',
				'type'    => 'int',
				'default' => 3,
				'min'     => 1,
				'max'     => 168,
			),
			'tpl_abandoned_cart'        => array(
				'section' => 'notifications',
				'type'    => 'template',
				'default' => null,
			),

			// Tools / maintenance.
			'log_retention_days'        => array(
				'section' => 'tools',
				'type'    => 'int',
				'default' => 30,
				'min'     => 0,
				'max'     => 3650,
			),
			'delete_data_on_uninstall'  => $b( 'tools', false ),
			'debug'                     => $b( 'tools', false ),
		);
		return $schema;
	}

	/**
	 * Translatable defaults (only call after init).
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	public static function translated_default( $key ) {
		switch ( $key ) {
			case 'bar_text':
				return __( 'Log in instantly with your Eitaa number — no password or SMS code needed.', 'mw24-eitaa' );
			case 'share_label':
				return __( 'Share my number', 'mw24-eitaa' );
			case 'later_label':
				return __( 'Later', 'mw24-eitaa' );
			case 'button_label':
				return __( 'Log in with Eitaa', 'mw24-eitaa' );
			case 'outside_hint':
				return __( 'To log in with Eitaa, open this site from our Eitaa mini-app.', 'mw24-eitaa' );
			case 'tpl_welcome':
				return __( "Hello {name}!\nYour account at {site} was created with your Eitaa number.\n{link}", 'mw24-eitaa' );
			case 'tpl_admin_register':
				return __( "New user registered at {site}: {name} ({user_login})\n{link}", 'mw24-eitaa' );
			case 'tpl_login':
				return __( "Hello {name}, a new login to your {site} account was recorded on {date}.\nIf it wasn't you, change your password: {link}", 'mw24-eitaa' );
			case 'tpl_password':
				return __( "Hello {name}, the password of your {site} account was changed on {date}.\nIf you did not do this, contact support immediately.", 'mw24-eitaa' );
			case 'tpl_order_status':
				return __( "Hello {name}, order #{order_number} at {site} is now: {status}.\nTotal: {total}\n{link}", 'mw24-eitaa' );
			case 'tpl_admin_new_order':
				return __( "New order #{order_number} at {site}\nCustomer: {name}\nTotal: {total}\n{link}", 'mw24-eitaa' );
			case 'tpl_customer_note':
				return __( "Hello {name}, a new note was added to order #{order_number}:\n{note}\n{link}", 'mw24-eitaa' );
			case 'tpl_tracking':
				return __( "Hello {name}, order #{order_number} has been shipped.\nTracking code: {tracking}\n{link}", 'mw24-eitaa' );
			case 'tpl_abandoned_cart':
				return __( "Hello {name}, you left {cart_count} item(s) in your cart at {site}. Complete your purchase here:\n{link}", 'mw24-eitaa' );
		}
		return '';
	}

	/**
	 * Raw stored values.
	 *
	 * @return array
	 */
	private function raw() {
		if ( null === $this->values ) {
			$stored       = get_option( self::OPTION, array() );
			$this->values = is_array( $stored ) ? $stored : array();
		}
		return $this->values;
	}

	/**
	 * Returns one setting (stored value or default).
	 *
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value for unknown keys.
	 * @return mixed
	 */
	public function get( $key, $fallback = null ) {
		$schema = self::schema();
		$raw    = $this->raw();
		if ( ! isset( $schema[ $key ] ) ) {
			return array_key_exists( $key, $raw ) ? $raw[ $key ] : $fallback;
		}
		if ( array_key_exists( $key, $raw ) ) {
			$value = $raw[ $key ];
			if ( 'bool' === $schema[ $key ]['type'] ) {
				return (bool) $value;
			}
			if ( 'int' === $schema[ $key ]['type'] ) {
				return (int) $value;
			}
			return $value;
		}
		if ( null === $schema[ $key ]['default'] ) {
			return self::translated_default( $key );
		}
		return $schema[ $key ]['default'];
	}

	/**
	 * All settings with defaults applied.
	 *
	 * @return array
	 */
	public function all() {
		$out = array();
		foreach ( array_keys( self::schema() ) as $key ) {
			$out[ $key ] = $this->get( $key );
		}
		return $out;
	}

	/**
	 * Merges and persists values (already sanitized).
	 *
	 * @param array $values Key => value.
	 * @return void
	 */
	public function update( array $values ) {
		$merged = array_merge( $this->raw(), $values );
		update_option( self::OPTION, $merged, true );
		$this->values = $merged;
	}

	/**
	 * Restores all settings to defaults (token and state are kept).
	 *
	 * @return void
	 */
	public function reset() {
		delete_option( self::OPTION );
		$this->values = array();
	}

	/**
	 * Sanitizes the fields of one section from raw POST data. Fields that are not
	 * part of the submitted form keep their stored value (checkboxes always submit
	 * a hidden "0", so unticked boxes are still saved).
	 *
	 * @param string $section Section name.
	 * @param array  $input   Raw (unslashed) input.
	 * @return array Sanitized key => value for the submitted fields of the section.
	 */
	public function sanitize_section( $section, array $input ) {
		$out = array();
		foreach ( self::schema() as $key => $field ) {
			if ( $field['section'] !== $section || ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$out[ $key ] = $this->sanitize_value( $key, $field, $input[ $key ] );
		}
		return $out;
	}

	/**
	 * Sanitizes one value according to its schema type.
	 *
	 * @param string $key   Key.
	 * @param array  $field Schema entry.
	 * @param mixed  $raw   Raw value (null when absent).
	 * @return mixed
	 */
	public function sanitize_value( $key, array $field, $raw ) {
		switch ( $field['type'] ) {
			case 'bool':
				return ! empty( $raw ) && '0' !== $raw;

			case 'int':
				$value = is_numeric( $raw ) ? (int) $raw : (int) $field['default'];
				if ( isset( $field['min'] ) ) {
					$value = max( (int) $field['min'], $value );
				}
				if ( isset( $field['max'] ) ) {
					$value = min( (int) $field['max'], $value );
				}
				return $value;

			case 'select':
				$raw = is_scalar( $raw ) ? (string) $raw : '';
				return in_array( $raw, $field['options'], true ) ? $raw : $field['default'];

			case 'url':
				$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
				if ( '' === $raw ) {
					return '';
				}
				if ( 0 === strpos( $raw, '/' ) && 0 !== strpos( $raw, '//' ) ) {
					$raw = home_url( $raw );
				}
				return esc_url_raw( $raw, array( 'http', 'https' ) );

			case 'color':
				$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
				$hex = sanitize_hex_color( $raw );
				return $hex ? $hex : '';

			case 'path':
				$raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
				$raw = '/' . ltrim( (string) wp_parse_url( $raw, PHP_URL_PATH ), '/' );
				return preg_replace( '#[^A-Za-z0-9/_\-.~%]#', '', $raw );

			case 'textarea':
			case 'template':
				$raw = is_scalar( $raw ) ? (string) $raw : '';
				$raw = sanitize_textarea_field( $raw );
				return function_exists( 'mb_substr' ) ? mb_substr( $raw, 0, 4000 ) : substr( $raw, 0, 4000 );

			case 'template_map':
				$out = array();
				if ( is_array( $raw ) ) {
					foreach ( $raw as $status => $template ) {
						$status = sanitize_key( $status );
						$text   = sanitize_textarea_field( is_scalar( $template ) ? (string) $template : '' );
						if ( '' !== $status && '' !== trim( $text ) ) {
							$out[ $status ] = $text;
						}
					}
				}
				return $out;

			case 'statuses':
				$out = array();
				if ( is_array( $raw ) ) {
					foreach ( $raw as $status ) {
						$status = sanitize_key( preg_replace( '/^wc-/', '', (string) $status ) );
						if ( '' !== $status ) {
							$out[] = $status;
						}
					}
				}
				return array_values( array_unique( $out ) );

			case 'chat_ids':
				$raw = is_scalar( $raw ) ? (string) $raw : '';
				$ids = preg_split( '/[\s,،;]+/u', MW24_Eitaa_Phone_Normalizer::to_english_digits( $raw ) );
				$ids = array_filter( array_map( 'trim', (array) $ids ), 'ctype_digit' );
				return implode( "\n", array_unique( $ids ) );

			case 'meta_key':
				$raw = is_scalar( $raw ) ? (string) $raw : '';
				return substr( preg_replace( '/[^A-Za-z0-9_\-:.]/', '', $raw ), 0, 191 );

			case 'role':
				$raw = sanitize_key( is_scalar( $raw ) ? (string) $raw : '' );
				if ( '' === $raw ) {
					return '';
				}
				return array_key_exists( $raw, MW24_Eitaa_Auth::allowed_roles() ) ? $raw : '';

			case 'selectors':
				$raw = is_scalar( $raw ) ? (string) $raw : '';
				$raw = wp_strip_all_tags( $raw );
				return trim( preg_replace( '/[{};<>\\\\]/', '', $raw ) );

			case 'css':
				$raw = is_scalar( $raw ) ? (string) $raw : '';
				$raw = wp_strip_all_tags( $raw );
				return trim( str_replace( array( '</', '<!--', '-->' ), '', $raw ) );

			case 'routes':
				return self::sanitize_routes( is_scalar( $raw ) ? (string) $raw : '' );

			case 'text':
			default:
				$raw = is_scalar( $raw ) ? (string) $raw : '';
				return sanitize_text_field( $raw );
		}
	}

	/**
	 * Sanitizes "slug = /relative/path/" lines.
	 *
	 * @param string $raw Raw textarea.
	 * @return string
	 */
	public static function sanitize_routes( $raw ) {
		$lines = array();
		foreach ( self::parse_routes( $raw ) as $slug => $path ) {
			$lines[] = $slug . ' = ' . $path;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Parses start-parameter routes into slug => relative path.
	 *
	 * @param string $raw Raw textarea.
	 * @return array
	 */
	public static function parse_routes( $raw ) {
		$routes = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			if ( false === strpos( $line, '=' ) ) {
				continue;
			}
			list( $slug, $path ) = array_map( 'trim', explode( '=', $line, 2 ) );
			$slug                = strtolower( preg_replace( '/[^A-Za-z0-9_\-]/', '', $slug ) );
			if ( '' === $slug || '' === $path || 0 !== strpos( $path, '/' ) || 0 === strpos( $path, '//' ) ) {
				continue;
			}
			$path = preg_replace( '#[^A-Za-z0-9/_\-.~%?=&]#', '', $path );
			if ( '' !== $path ) {
				$routes[ substr( $slug, 0, 64 ) ] = $path;
			}
		}
		return $routes;
	}

	// App token.

	/**
	 * Whether the token is defined in wp-config.php.
	 *
	 * @return bool
	 */
	public function token_from_constant() {
		return defined( 'MW24_EITAA_APP_TOKEN' ) && is_string( MW24_EITAA_APP_TOKEN ) && '' !== trim( MW24_EITAA_APP_TOKEN );
	}

	/**
	 * Returns the decrypted app token ('' when missing or undecryptable).
	 *
	 * @return string
	 */
	public function get_token() {
		if ( null !== $this->token ) {
			return $this->token;
		}
		if ( $this->token_from_constant() ) {
			$this->token = trim( MW24_EITAA_APP_TOKEN );
			return $this->token;
		}
		$stored      = get_option( self::TOKEN_OPTION, '' );
		$this->token = is_string( $stored ) && '' !== $stored ? MW24_Eitaa_Crypto::decrypt( $stored ) : '';
		return $this->token;
	}

	/**
	 * Whether a token is stored (even if it can no longer be decrypted).
	 *
	 * @return bool
	 */
	public function token_stored() {
		$stored = get_option( self::TOKEN_OPTION, '' );
		return is_string( $stored ) && '' !== $stored;
	}

	/**
	 * Whether a usable token is available.
	 *
	 * @return bool
	 */
	public function has_token() {
		return '' !== $this->get_token();
	}

	/**
	 * Stores a new token (encrypted).
	 *
	 * @param string $token Raw token.
	 * @return void
	 */
	public function save_token( $token ) {
		$token = trim( preg_replace( '/\s+/', '', (string) $token ) );
		if ( '' === $token ) {
			return;
		}
		update_option( self::TOKEN_OPTION, MW24_Eitaa_Crypto::encrypt( $token ), false );
		$this->token = $token;
		$this->set_state( 'token_tested_at', 0 );
	}

	/**
	 * Deletes the stored token.
	 *
	 * @return void
	 */
	public function delete_token() {
		delete_option( self::TOKEN_OPTION );
		$this->token = null;
		$this->set_state( 'token_tested_at', 0 );
	}

	/**
	 * Light format check of a token ("123456:ABC...").
	 *
	 * @param string $token Token.
	 * @return bool
	 */
	public static function token_looks_valid( $token ) {
		return (bool) preg_match( '/^\d{3,}:[A-Za-z0-9_\-]{10,}$/', (string) $token );
	}

	// Runtime state.

	/**
	 * Reads a state value.
	 *
	 * @param string $key     Key.
	 * @param mixed  $fallback Default.
	 * @return mixed
	 */
	public function get_state( $key, $fallback = null ) {
		$state = get_option( self::STATE_OPTION, array() );
		return is_array( $state ) && array_key_exists( $key, $state ) ? $state[ $key ] : $fallback;
	}

	/**
	 * Writes a state value.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @return void
	 */
	public function set_state( $key, $value ) {
		$state         = get_option( self::STATE_OPTION, array() );
		$state         = is_array( $state ) ? $state : array();
		$state[ $key ] = $value;
		update_option( self::STATE_OPTION, $state, false );
	}

	// Helpers.

	/**
	 * The plugin is "active" only when switched on and a usable token exists.
	 * When inactive, no external script is printed and nothing changes on the site.
	 *
	 * @return bool
	 */
	public function is_active() {
		return (bool) $this->get( 'enabled' ) && $this->has_token();
	}

	/**
	 * Whether the site is served over HTTPS (required by Eitaa).
	 *
	 * @return bool
	 */
	public function is_https() {
		if ( defined( 'MW24_EITAA_ALLOW_HTTP' ) && MW24_EITAA_ALLOW_HTTP ) {
			return true;
		}
		return 'https' === wp_parse_url( home_url(), PHP_URL_SCHEME );
	}

	/**
	 * Mini-app entry URL to register in Eitaayar.
	 *
	 * @return string
	 */
	public function entry_url() {
		$path = (string) $this->get( 'entry_path' );
		$path = '' === $path ? '/' : $path;
		return add_query_arg( MW24_Eitaa_Frontend::QUERY_VAR, '1', home_url( $path ) );
	}

	/**
	 * Maximum accepted initData age in seconds.
	 *
	 * @return int
	 */
	public function max_age() {
		return max( 1, (int) $this->get( 'max_age_hours' ) ) * HOUR_IN_SECONDS;
	}
}
