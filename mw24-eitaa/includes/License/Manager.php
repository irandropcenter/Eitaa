<?php
/**
 * Optional, removable marketplace license module (Zhaket / RTL-Theme).
 *
 * The whole includes/License directory can be deleted (or MW24_EITAA_DISABLE_LICENSE
 * defined) without affecting any feature. A missing, invalid or expired license NEVER
 * blocks end users or any plugin feature: it only disables automatic update checks.
 * Network requests happen only when the administrator activates a key or WordPress
 * checks for plugin updates, and only to the endpoint configured by the vendor.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * License manager.
 */
class MW24_Eitaa_License_Manager {

	const OPTION     = 'mw24_eitaa_license';
	const UPDATE_KEY = 'mw24_eitaa_update_info';
	const PRODUCT_ID = 'mw24-eitaa';

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
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
	}

	/**
	 * Marketplace providers. Endpoints are filled in by the vendor with the values
	 * given by the marketplace (constants or the mw24_eitaa_license_providers filter).
	 *
	 * @return array
	 */
	public function providers() {
		$providers = array(
			'zhaket' => array(
				'name'       => __( 'Zhaket', 'mw24-eitaa' ),
				'verify_url' => defined( 'MW24_EITAA_ZHAKET_VERIFY_URL' ) ? (string) MW24_EITAA_ZHAKET_VERIFY_URL : '',
				'update_url' => defined( 'MW24_EITAA_ZHAKET_UPDATE_URL' ) ? (string) MW24_EITAA_ZHAKET_UPDATE_URL : '',
			),
			'rtl'    => array(
				'name'       => __( 'RTL-Theme', 'mw24-eitaa' ),
				'verify_url' => defined( 'MW24_EITAA_RTL_VERIFY_URL' ) ? (string) MW24_EITAA_RTL_VERIFY_URL : '',
				'update_url' => defined( 'MW24_EITAA_RTL_UPDATE_URL' ) ? (string) MW24_EITAA_RTL_UPDATE_URL : '',
			),
		);

		/**
		 * Filters license providers: id => array( name, verify_url, update_url ).
		 *
		 * @param array $providers Providers.
		 */
		return (array) apply_filters( 'mw24_eitaa_license_providers', $providers );
	}

	/**
	 * Stored state.
	 *
	 * @return array
	 */
	public function state() {
		$state = get_option( self::OPTION, array() );
		return wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'provider'   => 'zhaket',
				'key'        => '',
				'status'     => 'inactive',
				'message'    => '',
				'expires'    => '',
				'checked_at' => 0,
			)
		);
	}

	/**
	 * Whether updates may be offered.
	 *
	 * @return bool
	 */
	public function is_valid() {
		return 'valid' === $this->state()['status'];
	}

	/**
	 * Verifies a key with the provider endpoint.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      License key.
	 * @return array New state.
	 */
	public function verify( $provider, $key ) {
		$providers = $this->providers();
		$state     = $this->state();
		$key       = sanitize_text_field( $key );
		$state     = array_merge(
			$state,
			array(
				'provider'   => isset( $providers[ $provider ] ) ? $provider : 'zhaket',
				'key'        => $key,
				'checked_at' => time(),
			)
		);
		$endpoint  = isset( $providers[ $provider ]['verify_url'] ) ? (string) $providers[ $provider ]['verify_url'] : '';
		if ( '' === $key ) {
			$state['status']  = 'inactive';
			$state['message'] = '';
		} elseif ( '' === $endpoint ) {
			$state['status']  = 'unconfigured';
			$state['message'] = __( 'The marketplace license endpoint is not configured in this build.', 'mw24-eitaa' );
		} else {
			$response = wp_remote_post(
				$endpoint,
				array(
					'timeout' => 15,
					'body'    => array(
						'license_key' => $key,
						'product'     => self::PRODUCT_ID,
						'version'     => MW24_EITAA_VERSION,
						'domain'      => wp_parse_url( home_url(), PHP_URL_HOST ),
					),
				)
			);
			$body     = is_wp_error( $response ) ? null : json_decode( (string) wp_remote_retrieve_body( $response ), true );

			/**
			 * Maps a marketplace response to array( status => valid|invalid|expired, message, expires ).
			 *
			 * @param array|null $parsed   Default parsing.
			 * @param mixed      $body     Decoded body.
			 * @param string     $provider Provider id.
			 */
			$parsed = apply_filters(
				'mw24_eitaa_license_parse_response',
				is_array( $body ) ? array(
					'status'  => ! empty( $body['valid'] ) || ( isset( $body['status'] ) && in_array( $body['status'], array( 'valid', 'active', 'success', true, 1, '1' ), true ) ) ? 'valid' : ( ! empty( $body['expired'] ) ? 'expired' : 'invalid' ),
					'message' => isset( $body['message'] ) && is_scalar( $body['message'] ) ? (string) $body['message'] : '',
					'expires' => isset( $body['expires'] ) && is_scalar( $body['expires'] ) ? (string) $body['expires'] : '',
				) : null,
				$body,
				$provider
			);
			if ( is_array( $parsed ) && isset( $parsed['status'] ) ) {
				$state['status']  = in_array( $parsed['status'], array( 'valid', 'invalid', 'expired' ), true ) ? $parsed['status'] : 'invalid';
				$state['message'] = sanitize_text_field( isset( $parsed['message'] ) ? (string) $parsed['message'] : '' );
				$state['expires'] = sanitize_text_field( isset( $parsed['expires'] ) ? (string) $parsed['expires'] : '' );
			} else {
				$state['status']  = 'error';
				$state['message'] = is_wp_error( $response ) ? $response->get_error_message() : __( 'Unexpected response from the license server.', 'mw24-eitaa' );
			}
		}
		update_option( self::OPTION, $state, false );
		delete_site_transient( self::UPDATE_KEY );
		return $state;
	}

	/**
	 * Handles the license form (called by the admin action controller after nonce checks).
	 *
	 * @return void
	 */
	public function handle_form() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified by MW24_Eitaa_Admin_Menu::handle_action().
		$op       = isset( $_POST['license_op'] ) ? sanitize_key( wp_unslash( $_POST['license_op'] ) ) : '';
		$provider = isset( $_POST['license_provider'] ) ? sanitize_key( wp_unslash( $_POST['license_provider'] ) ) : 'zhaket';
		$key      = isset( $_POST['license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['license_key'] ) ) : '';
		// phpcs:enable
		if ( 'deactivate' === $op ) {
			delete_option( self::OPTION );
			delete_site_transient( self::UPDATE_KEY );
			MW24_Eitaa_Admin_Menu::add_notice( 'success', __( 'The license was removed from this site.', 'mw24-eitaa' ) );
			return;
		}
		$state = $this->verify( $provider, '' !== $key ? $key : $this->state()['key'] );
		MW24_Eitaa_Admin_Menu::add_notice( 'valid' === $state['status'] ? 'success' : 'warning', $this->status_label( $state['status'] ) . ( $state['message'] ? ' — ' . $state['message'] : '' ) );
	}

	/**
	 * Human readable status.
	 *
	 * @param string $status Status.
	 * @return string
	 */
	private function status_label( $status ) {
		$labels = array(
			'inactive'     => __( 'No license entered', 'mw24-eitaa' ),
			'valid'        => __( 'License is valid — updates are enabled', 'mw24-eitaa' ),
			'invalid'      => __( 'License is not valid — automatic updates are disabled (all features keep working)', 'mw24-eitaa' ),
			'expired'      => __( 'License has expired — automatic updates are disabled (all features keep working)', 'mw24-eitaa' ),
			'unconfigured' => __( 'License check is not available in this build', 'mw24-eitaa' ),
			'error'        => __( 'The license server could not be reached', 'mw24-eitaa' ),
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * License card on the Tools page.
	 *
	 * @return void
	 */
	public function render_box() {
		$state = $this->state();
		$key   = (string) $state['key'];
		echo '<div class="mw24-card"><h2 class="mw24-card__title">' . esc_html__( 'Product license', 'mw24-eitaa' ) . '</h2>';
		echo '<p class="mw24-card__desc">' . esc_html__( 'The license is only used for automatic updates. Users, logins and messages never depend on it.', 'mw24-eitaa' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="mw24_eitaa_action"><input type="hidden" name="do" value="license">';
		wp_nonce_field( 'mw24_eitaa_action_license' );
		echo '<p><select name="license_provider">';
		foreach ( $this->providers() as $id => $provider ) {
			echo '<option value="' . esc_attr( $id ) . '" ' . selected( $state['provider'], $id, false ) . '>' . esc_html( $provider['name'] ) . '</option>';
		}
		echo '</select> ';
		printf(
			'<input type="text" class="regular-text code" dir="ltr" name="license_key" value="" placeholder="%s" autocomplete="off"></p>',
			esc_attr( '' !== $key ? substr( $key, 0, 4 ) . str_repeat( '•', 8 ) . substr( $key, -4 ) : __( 'License key', 'mw24-eitaa' ) )
		);
		echo '<p><strong>' . esc_html( $this->status_label( $state['status'] ) ) . '</strong>';
		if ( '' !== $state['expires'] ) {
			/* translators: %s: expiry date. */
			echo ' — ' . esc_html( sprintf( __( 'expires: %s', 'mw24-eitaa' ), $state['expires'] ) );
		}
		echo '</p><p><button type="submit" class="button button-primary" name="license_op" value="activate">' . esc_html__( 'Activate / check', 'mw24-eitaa' ) . '</button> ';
		if ( '' !== $key ) {
			echo '<button type="submit" class="button" name="license_op" value="deactivate">' . esc_html__( 'Remove license', 'mw24-eitaa' ) . '</button>';
		}
		echo '</p></form></div>';
	}

	/**
	 * Adds update information for valid licenses only.
	 *
	 * Expected JSON from the vendor update endpoint:
	 * { "version": "1.1.0", "package": "https://…/mw24-eitaa.zip", "tested": "7.1", "requires_php": "7.4" }
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public function check_update( $transient ) {
		if ( ! is_object( $transient ) || ! $this->is_valid() ) {
			return $transient;
		}
		$state     = $this->state();
		$providers = $this->providers();
		$endpoint  = isset( $providers[ $state['provider'] ]['update_url'] ) ? (string) $providers[ $state['provider'] ]['update_url'] : '';
		if ( '' === $endpoint ) {
			return $transient;
		}
		$info = get_site_transient( self::UPDATE_KEY );
		if ( false === $info ) {
			$response = wp_remote_post(
				$endpoint,
				array(
					'timeout' => 10,
					'body'    => array(
						'license_key' => $state['key'],
						'product'     => self::PRODUCT_ID,
						'version'     => MW24_EITAA_VERSION,
						'domain'      => wp_parse_url( home_url(), PHP_URL_HOST ),
					),
				)
			);
			$info     = is_wp_error( $response ) ? array() : json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$info     = is_array( $info ) ? $info : array();
			set_site_transient( self::UPDATE_KEY, $info, 12 * HOUR_IN_SECONDS );
		}
		if ( ! empty( $info['version'] ) && ! empty( $info['package'] ) && version_compare( (string) $info['version'], MW24_EITAA_VERSION, '>' ) ) {
			$transient->response[ MW24_EITAA_BASENAME ] = (object) array(
				'slug'         => self::PRODUCT_ID,
				'plugin'       => MW24_EITAA_BASENAME,
				'new_version'  => (string) $info['version'],
				'package'      => esc_url_raw( (string) $info['package'] ),
				'tested'       => isset( $info['tested'] ) ? (string) $info['tested'] : '',
				'requires_php' => isset( $info['requires_php'] ) ? (string) $info['requires_php'] : MW24_EITAA_MIN_PHP,
			);
		}
		return $transient;
	}
}
