<?php
/**
 * Admin REST routes used by the settings screens (capability + REST nonce protected).
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin REST API (mw24-eitaa/v1/admin/*).
 */
class MW24_Eitaa_Admin_Rest {

	/**
	 * Core.
	 *
	 * @var MW24_Eitaa_Core
	 */
	private $core;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Core $core Core.
	 */
	public function __construct( $core ) {
		$this->core = $core;
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
	 * Permission callback.
	 *
	 * @return bool
	 */
	public function can_manage() {
		return current_user_can( MW24_Eitaa_Admin_Menu::capability() );
	}

	/**
	 * Registers the routes.
	 *
	 * @return void
	 */
	public function register() {
		$routes = array(
			'/admin/meta-keys'    => array( WP_REST_Server::READABLE, 'meta_keys' ),
			'/admin/tables'       => array( WP_REST_Server::READABLE, 'tables' ),
			'/admin/columns'      => array( WP_REST_Server::READABLE, 'columns' ),
			'/admin/dry-run'      => array( WP_REST_Server::CREATABLE, 'dry_run' ),
			'/admin/backfill'     => array( WP_REST_Server::CREATABLE, 'backfill' ),
			'/admin/test-message' => array( WP_REST_Server::CREATABLE, 'test_message' ),
			'/admin/send-user'    => array( WP_REST_Server::CREATABLE, 'send_user' ),
			'/admin/bulk-preview' => array( WP_REST_Server::CREATABLE, 'bulk_preview' ),
		);
		foreach ( $routes as $route => $config ) {
			register_rest_route(
				MW24_Eitaa_Rest::NS,
				$route,
				array(
					'methods'             => $config[0],
					'callback'            => array( $this, $config[1] ),
					'permission_callback' => array( $this, 'can_manage' ),
				)
			);
		}
	}

	/**
	 * GET /admin/meta-keys?refresh=1
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function meta_keys( WP_REST_Request $request ) {
		$used = array();
		foreach ( $this->core->mapper->meta_maps() as $map ) {
			if ( '' !== $map->meta_key ) {
				$used[] = $map->meta_key;
			}
		}
		return rest_ensure_response(
			array(
				'keys' => MW24_Eitaa_Mapping_Meta::discover_keys( (bool) $request->get_param( 'refresh' ) ),
				'used' => $used,
			)
		);
	}

	/**
	 * GET /admin/tables
	 *
	 * @return WP_REST_Response
	 */
	public function tables() {
		return rest_ensure_response( array( 'tables' => MW24_Eitaa_Mapping_Table::list_tables() ) );
	}

	/**
	 * GET /admin/columns?table=
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function columns( WP_REST_Request $request ) {
		$columns = MW24_Eitaa_Mapping_Table::describe( (string) $request->get_param( 'table' ) );
		if ( is_wp_error( $columns ) ) {
			$columns->add_data( array( 'status' => 400 ) );
			return $columns;
		}
		return rest_ensure_response( array( 'columns' => $columns ) );
	}

	/**
	 * POST /admin/dry-run {phone}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function dry_run( WP_REST_Request $request ) {
		$result = $this->core->mapper->dry_run( (string) $request->get_param( 'phone' ) );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * POST /admin/backfill {op: status|preview|start|batch|cancel}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function backfill( WP_REST_Request $request ) {
		$backfill = $this->core->backfill;
		switch ( (string) $request->get_param( 'op' ) ) {
			case 'preview':
				return rest_ensure_response(
					array(
						'preview' => $backfill->preview( 200 ),
						'status'  => $backfill->status(),
					)
				);
			case 'start':
				return rest_ensure_response( array( 'status' => $backfill->start() ) );
			case 'batch':
				return rest_ensure_response( array( 'status' => $backfill->run_batch() ) );
			case 'cancel':
				return rest_ensure_response( array( 'status' => $backfill->cancel() ) );
		}
		return rest_ensure_response( array( 'status' => $backfill->status() ) );
	}

	/**
	 * POST /admin/test-message {chat_id, text}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_message( WP_REST_Request $request ) {
		$chat_id = preg_replace( '/\D/', '', MW24_Eitaa_Phone_Normalizer::to_english_digits( (string) $request->get_param( 'chat_id' ) ) );
		if ( '' === $chat_id ) {
			$chat_id = MW24_Eitaa_Auth::get_chat_id( get_current_user_id() );
		}
		$text = sanitize_textarea_field( (string) $request->get_param( 'text' ) );
		if ( '' === $text ) {
			$text = __( 'Test message from Eitaa Connect.', 'mw24-eitaa' );
		}
		$sent = $this->core->messenger->send(
			$chat_id,
			$text,
			array(
				'event'   => 'test',
				'user_id' => get_current_user_id(),
			)
		);
		if ( is_wp_error( $sent ) ) {
			$sent->add_data( array( 'status' => 400 ) );
			return $sent;
		}
		$this->core->settings->set_state( 'token_tested_at', time() );
		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => __( 'The test message was delivered. Your token works.', 'mw24-eitaa' ),
			)
		);
	}

	/**
	 * POST /admin/send-user {user_id, text}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function send_user( WP_REST_Request $request ) {
		$user_id = absint( $request->get_param( 'user_id' ) );
		$text    = sanitize_textarea_field( (string) $request->get_param( 'text' ) );
		$result  = mw24_eitaa_send(
			$user_id,
			$text,
			array(
				'type'  => 'user',
				'event' => 'manual',
			)
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return rest_ensure_response(
			array(
				'ok'      => true,
				'message' => __( 'Message sent.', 'mw24-eitaa' ),
			)
		);
	}

	/**
	 * POST /admin/bulk-preview {role, since, until, only_allows_pm}
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function bulk_preview( WP_REST_Request $request ) {
		$filters = array(
			'role'           => sanitize_key( (string) $request->get_param( 'role' ) ),
			'since'          => sanitize_text_field( (string) $request->get_param( 'since' ) ),
			'until'          => sanitize_text_field( (string) $request->get_param( 'until' ) ),
			'only_allows_pm' => (bool) $request->get_param( 'only_allows_pm' ),
		);
		return rest_ensure_response( array( 'count' => MW24_Eitaa_Queue::count_linked_users( $filters ) ) );
	}
}
