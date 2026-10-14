<?php
/**
 * Public REST API (namespace mw24-eitaa/v1).
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Public REST routes. initData is accepted ONLY from the X-Eitaa-Init-Data header,
 * never from the query string.
 */
class MW24_Eitaa_Rest {

	const NS = 'mw24-eitaa/v1';

	/**
	 * Settings.
	 *
	 * @var MW24_Eitaa_Settings
	 */
	private $settings;

	/**
	 * Auth.
	 *
	 * @var MW24_Eitaa_Auth
	 */
	private $auth;

	/**
	 * Frontend.
	 *
	 * @var MW24_Eitaa_Frontend
	 */
	private $frontend;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Settings $settings Settings.
	 * @param MW24_Eitaa_Auth     $auth     Auth.
	 * @param MW24_Eitaa_Frontend $frontend Frontend.
	 */
	public function __construct( $settings, $auth, $frontend ) {
		$this->settings = $settings;
		$this->auth     = $auth;
		$this->frontend = $frontend;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/**
	 * Registers routes.
	 *
	 * @return void
	 */
	public function register() {
		register_rest_route(
			self::NS,
			'/session',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'session' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'write_access' => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/contact',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'contact' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'contact' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/link',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'link' ),
				'permission_callback' => 'is_user_logged_in',
				'args'                => array(
					'contact' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/unlink',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'unlink' ),
				'permission_callback' => 'is_user_logged_in',
			)
		);
		register_rest_route(
			self::NS,
			'/route',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'route' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'start' => array(
						'type'     => 'string',
						'required' => true,
					),
				),
			)
		);
		register_rest_route(
			self::NS,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'status' ),
				'permission_callback' => array( $this, 'can_manage' ),
			)
		);
	}

	/**
	 * Capability check for admin routes.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( MW24_Eitaa_Admin_Menu::capability() );
	}

	/**
	 * Reads initData from the header only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private function init_data( WP_REST_Request $request ) {
		return (string) $request->get_header( 'X-Eitaa-Init-Data' );
	}

	/**
	 * Rejects requests that try to send initData in the URL and applies the per-IP limit.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return true|WP_Error
	 */
	private function guard( WP_REST_Request $request ) {
		$query = $request->get_query_params();
		foreach ( array( 'initData', 'init_data', 'tgWebAppData' ) as $forbidden ) {
			if ( isset( $query[ $forbidden ] ) ) {
				return new WP_Error( 'mw24_eitaa_bad_request', __( 'Eitaa data must be sent in the X-Eitaa-Init-Data header, never in the URL.', 'mw24-eitaa' ), array( 'status' => 400 ) );
			}
		}
		if ( ! MW24_Eitaa_Rate_Limiter::hit( 'ip', MW24_Eitaa_Rate_Limiter::client_ip() ) ) {
			return $this->auth->rate_limited();
		}
		return true;
	}

	/**
	 * Builds a private, non-cacheable response.
	 *
	 * @param array|WP_Error $result Result.
	 * @return WP_REST_Response|WP_Error
	 */
	private function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$response = rest_ensure_response( $result );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, private' );
		return $response;
	}

	/**
	 * POST /session.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function session( WP_REST_Request $request ) {
		$guard = $this->guard( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		return $this->respond(
			$this->auth->session(
				$this->init_data( $request ),
				array( 'write_access' => (bool) $request->get_param( 'write_access' ) )
			)
		);
	}

	/**
	 * POST /contact.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function contact( WP_REST_Request $request ) {
		$guard = $this->guard( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		return $this->respond( $this->auth->contact( $this->init_data( $request ), (string) $request->get_param( 'contact' ) ) );
	}

	/**
	 * POST /link (logged-in users).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function link( WP_REST_Request $request ) {
		$guard = $this->guard( $request );
		if ( is_wp_error( $guard ) ) {
			return $guard;
		}
		return $this->respond( $this->auth->link_current( $this->init_data( $request ), (string) $request->get_param( 'contact' ) ) );
	}

	/**
	 * POST /unlink (logged-in users).
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function unlink() {
		$this->auth->unlink( get_current_user_id(), 'self' );
		return $this->respond( array( 'status' => 'ok' ) );
	}

	/**
	 * GET /route?start=... resolves a start parameter to a white-listed URL.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function route( WP_REST_Request $request ) {
		if ( ! $this->settings->is_active() ) {
			return new WP_Error( 'mw24_eitaa_disabled', __( 'Eitaa connection is not active on this site.', 'mw24-eitaa' ), array( 'status' => 503 ) );
		}
		if ( ! MW24_Eitaa_Rate_Limiter::hit( 'route', MW24_Eitaa_Rate_Limiter::client_ip() ) ) {
			return $this->auth->rate_limited();
		}
		$url = $this->frontend->resolve_start_param( (string) $request->get_param( 'start' ) );
		return $this->respond( array( 'url' => $url ) );
	}

	/**
	 * GET /status (administrators only, never exposes the token).
	 *
	 * @return WP_REST_Response
	 */
	public function status() {
		$core = mw24_eitaa();
		return $this->respond(
			array(
				'version'          => MW24_EITAA_VERSION,
				'enabled'          => (bool) $this->settings->get( 'enabled' ),
				'active'           => $this->settings->is_active(),
				'token_configured' => $this->settings->has_token(),
				'token_source'     => $this->settings->token_from_constant() ? 'constant' : ( $this->settings->token_stored() ? 'database' : 'none' ),
				'https'            => $this->settings->is_https(),
				'entry_url'        => $this->settings->entry_url(),
				'woocommerce'      => $core->woo->is_active(),
				'mapping'          => $core->mapper->summary(),
				'linked_users'     => MW24_Eitaa_Admin_Page_Dashboard::linked_users_count(),
				'queue'            => $core->queue->stats(),
				'last_send'        => $core->messenger->last_send(),
				'readiness'        => MW24_Eitaa_Admin_Page_Dashboard::readiness(),
			)
		);
	}
}
