<?php
/**
 * Mini-app mode: detection, SDK, headers, scripts and start-parameter routing.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Front-end integration.
 */
class MW24_Eitaa_Frontend {

	const COOKIE    = 'mw24_eitaa_app';
	const QUERY_VAR = 'mw24_eitaa';

	/**
	 * Settings.
	 *
	 * @var MW24_Eitaa_Settings
	 */
	private $settings;

	/**
	 * Woo.
	 *
	 * @var MW24_Eitaa_Compat_WooCommerce
	 */
	private $woo;

	/**
	 * Is app.
	 *
	 * @var bool|null
	 */
	private $is_app = null;

	/**
	 * Whether the SDK was injected by the output buffer.
	 *
	 * @var bool
	 */
	private $buffering = false;

	/**
	 * Captured auth cookies (SameSite=None mode).
	 *
	 * @var array
	 */
	private $captured_cookies = array();

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Settings           $settings Settings.
	 * @param MW24_Eitaa_Compat_WooCommerce $woo      WooCommerce compat.
	 */
	public function __construct( $settings, $woo ) {
		$this->settings = $settings;
		$this->woo      = $woo;
	}

	/**
	 * Hooks. Nothing is printed or changed unless the plugin is active AND the
	 * request belongs to the mini-app.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'init', array( $this, 'detect' ), 1 );
		add_action( 'send_headers', array( $this, 'send_headers' ), 99 );
		add_action( 'login_init', array( $this, 'login_init' ), 1 );
		add_action( 'template_redirect', array( $this, 'maybe_start_buffer' ), 9999 );
		add_action( 'wp_head', array( $this, 'print_sdk' ), -9999 );
		add_action( 'login_head', array( $this, 'print_sdk' ), -9999 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_action( 'login_enqueue_scripts', array( $this, 'enqueue' ), 20 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'login_body_class', array( $this, 'body_class' ) );
		add_action( 'wp_footer', array( $this, 'footer' ), 5 );
		add_action( 'login_footer', array( $this, 'footer' ), 5 );
		add_action( 'set_auth_cookie', array( $this, 'capture_auth_cookie' ), 10, 5 );
		add_action( 'set_logged_in_cookie', array( $this, 'capture_logged_in_cookie' ), 10, 2 );
		add_filter( 'send_auth_cookies', array( $this, 'filter_send_auth_cookies' ), 99 );
		add_filter( 'woocommerce_set_cookie_options', array( $this, 'woo_cookie_options' ), 10 );
	}

	// Detection.

	/**
	 * Detects mini-app mode (query var on the entry URL, then a session cookie).
	 *
	 * @return void
	 */
	public function detect() {
		$this->is_app = false;
		if ( ! $this->settings->is_active() || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only mode switch, no state change besides a UI cookie.
		$flag = isset( $_GET[ self::QUERY_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR ] ) ) : null;
		// phpcs:enable
		if ( '1' === $flag ) {
			$this->is_app = true;
			$this->set_cookie( true );
		} elseif ( '0' === $flag ) {
			$this->set_cookie( false );
		} elseif ( isset( $_COOKIE[ self::COOKIE ] ) && '1' === $_COOKIE[ self::COOKIE ] ) {
			$this->is_app = true;
		} elseif ( $this->settings->get( 'detect_heuristic' ) && $this->heuristic_match() ) {
			$this->is_app = true;
			$this->set_cookie( true );
		}

		/**
		 * Filters whether the current request is inside the Eitaa mini-app.
		 *
		 * @param bool $is_app Detected state.
		 */
		$this->is_app = (bool) apply_filters( 'mw24_eitaa_is_mini_app', $this->is_app );
	}

	/**
	 * Whether the current request runs inside the mini-app.
	 *
	 * @return bool
	 */
	public function is_mini_app() {
		if ( null === $this->is_app ) {
			$this->detect();
		}
		return (bool) $this->is_app;
	}

	/**
	 * Optional (unreliable) User-Agent / Referer heuristic.
	 *
	 * @return bool
	 */
	private function heuristic_match() {
		$patterns = array_filter( array_map( 'trim', explode( ',', (string) $this->settings->get( 'heuristic_patterns' ) ) ) );
		if ( empty( $patterns ) ) {
			return false;
		}
		$haystack = '';
		if ( isset( $_SERVER['HTTP_USER_AGENT'] ) ) {
			$haystack .= sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) . ' ';
		}
		if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
			$haystack .= sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
		}
		foreach ( $patterns as $pattern ) {
			if ( '' !== $pattern && false !== stripos( $haystack, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Sets or clears the session cookie that keeps mini-app mode on later pages.
	 *
	 * @param bool $on Enable.
	 * @return void
	 */
	private function set_cookie( $on ) {
		if ( headers_sent() ) {
			return;
		}
		$secure   = is_ssl();
		$samesite = $secure && $this->settings->get( 'samesite_none' ) ? 'None' : 'Lax';
		setcookie(
			self::COOKIE,
			$on ? '1' : '',
			array(
				'expires'  => $on ? 0 : time() - YEAR_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => $secure,
				'httponly' => false,
				'samesite' => $samesite,
			)
		);
		if ( $on ) {
			$_COOKIE[ self::COOKIE ] = '1';
		} else {
			unset( $_COOKIE[ self::COOKIE ] );
		}
	}

	/**
	 * Whether this is the entry request (query var present).
	 *
	 * @return bool
	 */
	public function is_entry_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET[ self::QUERY_VAR ] ) && '1' === sanitize_key( wp_unslash( $_GET[ self::QUERY_VAR ] ) );
	}

	// Headers.

	/**
	 * Cache and frame headers for mini-app pages.
	 *
	 * @return void
	 */
	public function send_headers() {
		if ( $this->is_mini_app() ) {
			$this->apply_app_headers();
		}
	}

	/**
	 * Login screen (wp-login.php): keep frame options removable and send app headers.
	 *
	 * @return void
	 */
	public function login_init() {
		if ( ! $this->is_mini_app() ) {
			return;
		}
		if ( $this->settings->get( 'frame_headers' ) ) {
			remove_action( 'login_init', 'send_frame_options_header' );
		}
		$this->apply_app_headers();
	}

	/**
	 * Sends private/no-cache headers and (optionally) relaxes framing for Eitaa.
	 *
	 * @return void
	 */
	private function apply_app_headers() {
		if ( headers_sent() ) {
			return;
		}
		nocache_headers();
		header( 'Vary: Cookie', false );
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Standard cache-plugin constant.
		}
		do_action( 'litespeed_control_set_nocache', 'mw24-eitaa mini-app' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache API.
		if ( $this->settings->get( 'frame_headers' ) ) {
			header_remove( 'X-Frame-Options' );
			header( 'Content-Security-Policy: frame-ancestors ' . $this->frame_ancestors() );
		}
	}

	/**
	 * Sanitized frame-ancestors source list.
	 *
	 * @return string
	 */
	public function frame_ancestors() {
		$sources = array( "'self'" );
		foreach ( preg_split( '/\s+/', (string) $this->settings->get( 'frame_ancestors' ) ) as $source ) {
			if ( preg_match( '#^(https://)?(\*\.)?[a-z0-9.-]+\.[a-z]{2,}(:\d{1,5})?$#i', $source ) ) {
				$sources[] = $source;
			}
		}
		return implode( ' ', array_unique( $sources ) );
	}

	// SDK & assets.

	/**
	 * Optional strict mode: output buffer that injects the SDK right after <head>.
	 *
	 * @return void
	 */
	public function maybe_start_buffer() {
		if ( $this->is_mini_app() && $this->settings->get( 'sdk_strict_first' ) && ! is_feed() && ! wp_doing_ajax() ) {
			$this->buffering = true;
			ob_start( array( $this, 'inject_sdk' ) );
		}
	}

	/**
	 * Output buffer callback.
	 *
	 * @param string $html Page HTML.
	 * @return string
	 */
	public function inject_sdk( $html ) {
		if ( ! is_string( $html ) || false === stripos( $html, '<head' ) ) {
			return $html;
		}
		// The official SDK must be the first script in <head>; it cannot be enqueued at that position.
		$tags  = '<script src="' . esc_url( MW24_EITAA_SDK_URL ) . '" id="mw24-eitaa-sdk"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript
		$tags .= '<script id="mw24-eitaa-early">' . $this->early_script() . '</script>';
		$out   = preg_replace( '/<head(\s[^>]*)?>/i', '$0' . $tags, $html, 1 );
		return null === $out ? $html : $out;
	}

	/**
	 * Tiny inline script that flags mini-app mode before the theme renders.
	 *
	 * @return string
	 */
	private function early_script() {
		return "document.documentElement.className+=' mw24-eitaa-app';";
	}

	/**
	 * Prints the official SDK as the first script in <head> (only in mini-app mode).
	 *
	 * @return void
	 */
	public function print_sdk() {
		if ( ! $this->is_mini_app() || $this->buffering ) {
			return;
		}
		wp_print_script_tag(
			array(
				'src' => MW24_EITAA_SDK_URL,
				'id'  => 'mw24-eitaa-sdk',
			)
		);
		wp_print_inline_script_tag( $this->early_script(), array( 'id' => 'mw24-eitaa-early' ) );
	}

	/**
	 * Enqueues the app script/style with its configuration.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! $this->is_mini_app() ) {
			return;
		}
		wp_enqueue_style( 'mw24-eitaa-app', MW24_EITAA_URL . 'assets/css/eitaa-app.css', array(), MW24_EITAA_VERSION );
		$css = $this->inline_css();
		if ( '' !== $css ) {
			wp_add_inline_style( 'mw24-eitaa-app', $css );
		}
		wp_enqueue_script( 'mw24-eitaa-app', MW24_EITAA_URL . 'assets/js/eitaa-app.js', array(), MW24_EITAA_VERSION, true );
		wp_add_inline_script( 'mw24-eitaa-app', 'window.MW24Eitaa = ' . wp_json_encode( $this->js_config() ) . ';', 'before' );
	}

	/**
	 * Admin-defined CSS for app mode.
	 *
	 * @return string
	 */
	private function inline_css() {
		$css       = '';
		$selectors = array_filter( array_map( 'trim', explode( ',', str_replace( array( "\r", "\n" ), ',', (string) $this->settings->get( 'hide_selectors' ) ) ) ) );
		if ( $selectors ) {
			$scoped = array();
			foreach ( $selectors as $selector ) {
				$scoped[] = 'html.mw24-eitaa-app ' . $selector;
			}
			$css .= implode( ',', $scoped ) . '{display:none!important}';
		}
		$custom = (string) $this->settings->get( 'custom_css' );
		if ( '' !== $custom ) {
			$css .= "\n" . $custom;
		}
		return $css;
	}

	/**
	 * Configuration passed to assets/js/eitaa-app.js.
	 *
	 * @return array
	 */
	public function js_config() {
		$s        = $this->settings;
		$user     = wp_get_current_user();
		$woo      = $this->woo->is_active();
		$is_login = isset( $GLOBALS['pagenow'] ) && 'wp-login.php' === $GLOBALS['pagenow'];
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$start       = isset( $_GET['tgWebAppStartParam'] ) ? sanitize_text_field( wp_unslash( $_GET['tgWebAppStartParam'] ) ) : '';
		$redirect_to = isset( $_REQUEST['redirect_to'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_REQUEST['redirect_to'] ) ), '' ) : '';
		// phpcs:enable
		$config = array(
			'version'    => MW24_EITAA_VERSION,
			'restUrl'    => esc_url_raw( rest_url( MW24_Eitaa_Rest::NS . '/' ) ),
			'nonce'      => $user->exists() ? wp_create_nonce( 'wp_rest' ) : '',
			'debug'      => (bool) $s->get( 'debug' ),
			'cookie'     => array(
				'name' => self::COOKIE,
				'path' => COOKIEPATH ? COOKIEPATH : '/',
			),
			'user'       => array(
				'loggedIn' => $user->exists(),
				'linked'   => $user->exists() && '' !== MW24_Eitaa_Auth::get_chat_id( $user->ID ),
			),
			'page'       => array(
				'isEntry'    => $this->is_entry_request(),
				'isLogin'    => $is_login,
				'isCart'     => $woo && $this->woo->is_cart(),
				'isCheckout' => $woo && $this->woo->is_checkout(),
				'home'       => home_url( '/' ),
				'redirectTo' => $is_login ? ( $redirect_to ? $redirect_to : $this->account_url() ) : '',
			),
			'startRoute' => '' !== $start ? $this->resolve_start_param( $start ) : '',
			'app'        => array(
				'expand'                => (bool) $s->get( 'expand' ),
				'fullscreen'            => (bool) $s->get( 'fullscreen' ),
				'backButton'            => (bool) $s->get( 'back_button' ),
				'haptic'                => (bool) $s->get( 'haptic' ),
				'disableVerticalSwipes' => (bool) $s->get( 'disable_vertical_swipes' ),
				'safeArea'              => (bool) $s->get( 'safe_area' ),
				'closingConfirmation'   => (string) $s->get( 'closing_confirmation' ),
				'colors'                => array(
					'header'     => (string) $s->get( 'header_color' ),
					'background' => (string) $s->get( 'bg_color' ),
					'bottomBar'  => (string) $s->get( 'bottom_bar_color' ),
				),
				'mainButton'            => array(
					'enabled'   => (bool) $s->get( 'main_button' ),
					'woo'       => (bool) $s->get( 'main_button_woo' ),
					'text'      => (string) $s->get( 'main_button_text' ),
					'url'       => (string) $s->get( 'main_button_url' ),
					'color'     => (string) $s->get( 'main_button_color' ),
					'textColor' => (string) $s->get( 'main_button_text_color' ),
				),
				'secondaryButton'       => array(
					'enabled'  => (bool) $s->get( 'secondary_button' ),
					'text'     => (string) $s->get( 'secondary_button_text' ),
					'url'      => (string) $s->get( 'secondary_button_url' ),
					'position' => (string) $s->get( 'secondary_button_position' ),
				),
				'settingsButton'        => array(
					'enabled' => (bool) $s->get( 'settings_button' ),
					'url'     => $this->settings_button_url(),
				),
			),
			'woo'        => array(
				'active'       => $woo,
				'cartCount'    => $woo ? $this->woo->cart_count() : 0,
				'cartUrl'      => $woo ? $this->woo->cart_url() : '',
				'checkoutUrl'  => $woo ? $this->woo->checkout_url() : '',
				'storeCartUrl' => $woo ? esc_url_raw( rest_url( 'wc/store/v1/cart' ) ) : '',
			),
			'login'      => array(
				'enabled'            => (bool) $s->get( 'login_enabled' ),
				'prompt'             => (string) $s->get( 'prompt_mode' ),
				'direct'             => (bool) $s->get( 'prompt_direct' ),
				'requestWriteAccess' => (bool) $s->get( 'request_write_access' ),
				'redirect'           => (string) $s->get( 'after_login_url' ),
			),
			'i18n'       => array(
				/* translators: %d: number of items in the cart. */
				'cart'           => __( 'Cart (%d)', 'mw24-eitaa' ),
				'checkout'       => __( 'Continue to checkout', 'mw24-eitaa' ),
				'loggingIn'      => __( 'Logging you in…', 'mw24-eitaa' ),
				'loginFailed'    => __( 'Eitaa login failed. Please close the mini-app, open it again and retry.', 'mw24-eitaa' ),
				'shareCancelled' => __( 'You can log in with your Eitaa number any time from the login button.', 'mw24-eitaa' ),
				'notSupported'   => __( 'Please update your Eitaa app to use this feature.', 'mw24-eitaa' ),
				'networkError'   => __( 'Connection problem. Please check your internet and try again.', 'mw24-eitaa' ),
				'linked'         => __( 'Your Eitaa account is now connected.', 'mw24-eitaa' ),
			),
		);

		/**
		 * Filters the configuration passed to the mini-app script.
		 *
		 * @param array $config Configuration.
		 */
		return (array) apply_filters( 'mw24_eitaa_js_config', $config );
	}

	/**
	 * Adds body classes in mini-app mode.
	 *
	 * @param string[] $classes Classes.
	 * @return string[]
	 */
	public function body_class( $classes ) {
		if ( $this->is_mini_app() ) {
			$classes[] = 'mw24-eitaa-app';
			if ( $this->settings->get( 'safe_area' ) ) {
				$classes[] = 'mw24-eitaa-safe-area';
			}
		}
		return $classes;
	}

	/**
	 * Footer markup: phone bar and cart counter.
	 *
	 * @return void
	 */
	public function footer() {
		if ( ! $this->is_mini_app() ) {
			return;
		}
		if ( $this->settings->get( 'login_enabled' ) && ! is_user_logged_in() ) {
			$bar = MW24_Eitaa_Template::render(
				'phone-bar.php',
				array(
					'text'        => (string) $this->settings->get( 'bar_text' ),
					'share_label' => (string) $this->settings->get( 'share_label' ),
					'later_label' => (string) $this->settings->get( 'later_label' ),
				)
			);
			echo $bar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The template escapes every value.
		}
		if ( $this->woo->is_active() ) {
			echo $this->woo->cart_count_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in the method.
		}
	}

	// URLs & routing.

	/**
	 * Account URL (WooCommerce "My account", otherwise profile/login).
	 *
	 * @return string
	 */
	public function account_url() {
		if ( $this->woo->is_active() ) {
			$url = $this->woo->account_url();
			if ( $url ) {
				return $url;
			}
		}
		return is_user_logged_in() ? admin_url( 'profile.php' ) : wp_login_url();
	}

	/**
	 * URL opened by the Settings button.
	 *
	 * @return string
	 */
	public function settings_button_url() {
		$url = (string) $this->settings->get( 'settings_button_url' );
		return '' !== $url ? $url : $this->account_url();
	}

	/**
	 * Resolves a start parameter to a same-site URL:
	 * p<ID> post/product, cart, checkout, account, home, u:<slug> (white-listed route).
	 *
	 * @param string $param Start parameter.
	 * @return string '' when not resolvable.
	 */
	public function resolve_start_param( $param ) {
		$param = trim( (string) $param );
		$url   = '';
		if ( '' === $param || strlen( $param ) > 128 ) {
			return '';
		}
		if ( preg_match( '/^p(\d{1,20})$/i', $param, $m ) ) {
			$post = get_post( (int) $m[1] );
			if ( $post && 'publish' === $post->post_status && is_post_type_viewable( $post->post_type ) ) {
				$url = (string) get_permalink( $post );
			}
		} elseif ( 'cart' === $param && $this->woo->is_active() ) {
			$url = $this->woo->cart_url();
		} elseif ( 'checkout' === $param && $this->woo->is_active() ) {
			$url = $this->woo->checkout_url();
		} elseif ( 'account' === $param ) {
			$url = $this->account_url();
		} elseif ( 'home' === $param ) {
			$url = home_url( '/' );
		} elseif ( preg_match( '/^u[:_\-]([A-Za-z0-9_\-]{1,64})$/', $param, $m ) ) {
			$routes = MW24_Eitaa_Settings::parse_routes( (string) $this->settings->get( 'start_routes' ) );
			$slug   = strtolower( $m[1] );
			if ( isset( $routes[ $slug ] ) ) {
				$url = home_url( $routes[ $slug ] );
			}
		}

		/**
		 * Filters the URL a start parameter resolves to.
		 *
		 * @param string $url   Resolved URL ('' = none).
		 * @param string $param Start parameter.
		 */
		$url = (string) apply_filters( 'mw24_eitaa_start_param_url', $url, $param );
		return '' !== $url ? wp_validate_redirect( $url, '' ) : '';
	}

	// SameSite=None cookies (only needed when Eitaa embeds the site in an iframe).

	/**
	 * Whether cookies must be cross-site capable.
	 *
	 * @return bool
	 */
	private function samesite_none_active() {
		return is_ssl() && $this->settings->get( 'samesite_none' ) && $this->is_mini_app();
	}

	/**
	 * Captures the auth cookie value.
	 *
	 * @param string $value      Cookie value.
	 * @param int    $expire     Expiry.
	 * @param int    $expiration Expiration.
	 * @param int    $user_id    User.
	 * @param string $scheme     auth|secure_auth.
	 * @return void
	 */
	public function capture_auth_cookie( $value, $expire, $expiration, $user_id, $scheme ) {
		$name                     = 'secure_auth' === $scheme ? SECURE_AUTH_COOKIE : AUTH_COOKIE;
		$this->captured_cookies[] = array( $name, $value, (int) $expire, array( PLUGINS_COOKIE_PATH, ADMIN_COOKIE_PATH ) );
	}

	/**
	 * Captures the logged-in cookie value.
	 *
	 * @param string $value  Cookie value.
	 * @param int    $expire Expiry.
	 * @return void
	 */
	public function capture_logged_in_cookie( $value, $expire ) {
		$paths                    = array_unique( array( COOKIEPATH, SITECOOKIEPATH ) );
		$this->captured_cookies[] = array( LOGGED_IN_COOKIE, $value, (int) $expire, $paths );
	}

	/**
	 * Re-sends WordPress auth cookies with SameSite=None; Secure in iframe mode.
	 *
	 * @param bool $send Whether WordPress should send them.
	 * @return bool
	 */
	public function filter_send_auth_cookies( $send ) {
		if ( ! $send || ! $this->samesite_none_active() || empty( $this->captured_cookies ) || headers_sent() ) {
			$this->captured_cookies = array();
			return $send;
		}
		foreach ( $this->captured_cookies as $cookie ) {
			list( $name, $value, $expire, $paths ) = $cookie;
			foreach ( $paths as $path ) {
				setcookie(
					$name,
					$value,
					array(
						'expires'  => $expire,
						'path'     => $path,
						'domain'   => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
						'secure'   => true,
						'httponly' => true,
						'samesite' => 'None',
					)
				);
			}
		}
		$this->captured_cookies = array();
		return false;
	}

	/**
	 * WooCommerce session cookies with SameSite=None in iframe mode.
	 *
	 * @param array $options Cookie options.
	 * @return array
	 */
	public function woo_cookie_options( $options ) {
		if ( is_array( $options ) && $this->samesite_none_active() ) {
			$options['samesite'] = 'None';
			$options['secure']   = true;
		}
		return $options;
	}
}
