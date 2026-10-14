<?php
/**
 * Page cache plugin compatibility.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Keeps mini-app pages out of page caches. Pages served in mini-app mode always send
 * "Cache-Control: private, no-store" and define DONOTCACHEPAGE (see Frontend); here the
 * mini-app cookie is registered with cache plugins that offer an API for it.
 */
class MW24_Eitaa_Compat_Cache {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_filter( 'rocket_cache_reject_cookies', array( $this, 'add_cookie' ) );
		add_action( 'admin_init', array( $this, 'register_wpsc_cookie' ) );
		add_action( 'update_option_' . MW24_Eitaa_Settings::OPTION, array( $this, 'refresh_cache_configs' ) );
	}

	/**
	 * Adds the mini-app cookie to a reject list.
	 *
	 * @param array $cookies Cookies.
	 * @return array
	 */
	public function add_cookie( $cookies ) {
		$cookies   = is_array( $cookies ) ? $cookies : array();
		$cookies[] = MW24_Eitaa_Frontend::COOKIE;
		return array_values( array_unique( $cookies ) );
	}

	/**
	 * WP Super Cache: register the cookie once.
	 *
	 * @return void
	 */
	public function register_wpsc_cookie() {
		if ( function_exists( 'wpsc_add_cookie' ) && current_user_can( 'manage_options' ) && ! get_option( 'mw24_eitaa_wpsc_cookie' ) ) {
			wpsc_add_cookie( MW24_Eitaa_Frontend::COOKIE );
			update_option( 'mw24_eitaa_wpsc_cookie', 1, false );
		}
	}

	/**
	 * Regenerates WP Rocket config after settings changes.
	 *
	 * @return void
	 */
	public function refresh_cache_configs() {
		if ( function_exists( 'rocket_generate_config_file' ) ) {
			rocket_generate_config_file();
		}
	}

	/**
	 * Detected cache plugins (for the system status box).
	 *
	 * @return string[]
	 */
	public static function detected() {
		$found = array();
		$map   = array(
			'WP Rocket'        => 'WP_ROCKET_VERSION',
			'LiteSpeed Cache'  => 'LSCWP_V',
			'W3 Total Cache'   => 'W3TC',
			'WP Super Cache'   => 'WPCACHEHOME',
			'WP Fastest Cache' => 'WPFC_WP_CONTENT_BASENAME',
			'SG Optimizer'     => 'SiteGround_Optimizer\VERSION',
		);
		foreach ( $map as $name => $constant ) {
			if ( defined( $constant ) ) {
				$found[] = $name;
			}
		}
		return $found;
	}
}
