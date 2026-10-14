<?php
/**
 * Integration tests of POST /session and POST /contact.
 *
 * @package MW24_Eitaa
 */

/**
 * @covers MW24_Eitaa_Rest
 * @covers MW24_Eitaa_Auth
 */
class Test_MW24_Eitaa_Rest_Auth extends WP_UnitTestCase {

	/** @var array Eitaa user used by most tests. */
	private $eitaa = array(
		'id'                 => 279058397,
		'first_name'         => 'مهدی',
		'last_name'          => 'هاشمی',
		'language_code'      => 'fa',
		'allows_write_to_pm' => true,
	);

	public function set_up() {
		parent::set_up();
		MW24_Eitaa_Test_Signer::configure();
		$profiles = array();
		foreach ( MW24_Eitaa_Mapping_Profiles::all() as $id => $profile ) {
			$profiles[ $id ] = array(
				'active'    => false,
				'confirmed' => true,
			);
		}
		mw24_eitaa()->mapper->save_config(
			array_merge(
				MW24_Eitaa_Phone_Mapper::default_config(),
				array(
					'profiles' => $profiles,
					'meta'     => array(
						array(
							'key'     => 'billing_phone',
							'format'  => '0',
							'pattern' => '',
							'write'   => true,
						),
					),
				)
			)
		);
		wp_set_current_user( 0 );
	}

	public function tear_down() {
		unset( $GLOBALS['mw24_eitaa_test_strict_limits'] );
		parent::tear_down();
	}

	/**
	 * Dispatches a REST request.
	 *
	 * @param string $route Route.
	 * @param string $init  initData header.
	 * @param array  $body  JSON body.
	 * @return WP_REST_Response
	 */
	private function post( $route, $init = '', array $body = array() ) {
		$request = new WP_REST_Request( 'POST', '/mw24-eitaa/v1' . $route );
		if ( '' !== $init ) {
			$request->set_header( 'X-Eitaa-Init-Data', $init );
		}
		if ( $body ) {
			$request->set_header( 'Content-Type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_do_request( $request );
	}

	/**
	 * Signed contact for the default Eitaa user.
	 *
	 * @param string $phone Phone.
	 * @param array  $extra Contact overrides.
	 * @return string
	 */
	private function contact( $phone, array $extra = array() ) {
		return MW24_Eitaa_Test_Signer::contact(
			array_merge(
				array(
					'user_id'      => $this->eitaa['id'],
					'phone_number' => $phone,
					'first_name'   => 'مهدی',
					'last_name'    => 'هاشمی',
				),
				$extra
			)
		);
	}

	public function test_routes_are_registered() {
		$routes = rest_get_server()->get_routes();
		foreach ( array( '/session', '/contact', '/link', '/unlink', '/route', '/status' ) as $route ) {
			$this->assertArrayHasKey( '/mw24-eitaa/v1' . $route, $routes );
		}
	}

	public function test_session_requires_the_header() {
		$this->assertSame( 400, $this->post( '/session' )->get_status() );
	}

	public function test_init_data_in_query_string_is_refused() {
		$request = new WP_REST_Request( 'POST', '/mw24-eitaa/v1/session' );
		$request->set_query_params( array( 'initData' => MW24_Eitaa_Test_Signer::init_data( $this->eitaa ) ) );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
	}

	public function test_session_rejects_invalid_signature_with_generic_error() {
		$response = $this->post( '/session', MW24_Eitaa_Test_Signer::init_data( $this->eitaa, array(), '1:WRONG_TOKEN_xxxxxxxxxxxxxxxxx' ) );
		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 'mw24_eitaa_auth_failed', $response->get_data()['code'] );
	}

	public function test_session_is_disabled_when_plugin_is_off() {
		mw24_eitaa()->settings->update( array( 'enabled' => false ) );
		$this->assertSame( 503, $this->post( '/session', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ) )->get_status() );
	}

	public function test_unknown_account_needs_phone() {
		$response = $this->post( '/session', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'need_phone', $response->get_data()['status'] );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_contact_registers_and_logs_in_a_new_user() {
		$init     = MW24_Eitaa_Test_Signer::init_data( $this->eitaa );
		$response = $this->post( '/contact', $init, array( 'contact' => $this->contact( '989121234567' ) ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'ok', $data['status'] );
		$this->assertTrue( $data['created'] );
		$this->assertTrue( $data['reload'] );

		$user = get_user_by( 'login', '989121234567' );
		$this->assertInstanceOf( 'WP_User', $user );
		$this->assertSame( $user->ID, get_current_user_id() );
		$this->assertSame( '989121234567@eitaa.invalid', $user->user_email );
		$this->assertContains( 'subscriber', $user->roles );
		$this->assertSame( '279058397', get_user_meta( $user->ID, 'mw24_eitaa_user_id', true ) );
		$this->assertSame( '09121234567', get_user_meta( $user->ID, 'mw24_eitaa_phone', true ) );
		$this->assertSame( '09121234567', get_user_meta( $user->ID, 'billing_phone', true ) );
		$this->assertSame( '1', get_user_meta( $user->ID, 'mw24_eitaa_allows_pm', true ) );
		$this->assertSame( 'fa', get_user_meta( $user->ID, 'mw24_eitaa_language', true ) );
		$this->assertNotEmpty( get_user_meta( $user->ID, 'mw24_eitaa_phone_verified_at', true ) );
		$this->assertSame( 'مهدی', $user->first_name );
		$this->assertSame( 1, did_action( 'mw24_eitaa_user_registered' ) );
	}

	public function test_username_collision_uses_prefixed_login() {
		self::factory()->user->create( array( 'user_login' => '989121234567' ) );
		mw24_eitaa()->mapper->save_config( array_merge( mw24_eitaa()->mapper->get_config(), array( 'match_login_email' => false ) ) );
		$this->post( '/contact', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ), array( 'contact' => $this->contact( '989121234567' ) ) );
		$this->assertInstanceOf( 'WP_User', get_user_by( 'login', 'eitaa_09121234567' ) );
	}

	public function test_replayed_contact_is_rejected() {
		$init    = MW24_Eitaa_Test_Signer::init_data( $this->eitaa );
		$contact = $this->contact( '989121234567' );
		$this->assertSame( 200, $this->post( '/contact', $init, array( 'contact' => $contact ) )->get_status() );
		wp_set_current_user( 0 );
		$this->assertSame( 401, $this->post( '/contact', $init, array( 'contact' => $contact ) )->get_status() );
	}

	public function test_contact_of_another_eitaa_user_is_rejected() {
		$response = $this->post( '/contact', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ), array( 'contact' => $this->contact( '989121234567', array( 'user_id' => 1 ) ) ) );
		$this->assertSame( 401, $response->get_status() );
		$this->assertFalse( get_user_by( 'login', '989121234567' ) );
	}

	public function test_contact_without_user_id_is_rejected() {
		$contact  = MW24_Eitaa_Test_Signer::contact( array( 'phone_number' => '989121234567' ) );
		$response = $this->post( '/contact', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ), array( 'contact' => $contact ) );
		$this->assertSame( 401, $response->get_status() );
	}

	public function test_forged_contact_signature_is_rejected() {
		$contact = MW24_Eitaa_Test_Signer::contact(
			array(
				'user_id'      => $this->eitaa['id'],
				'phone_number' => '989121234567',
			),
			array(),
			'1:FORGED_TOKEN_xxxxxxxxxxxxxxxx'
		);
		$this->assertSame( 401, $this->post( '/contact', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ), array( 'contact' => $contact ) )->get_status() );
	}

	public function test_existing_user_is_found_through_the_phone_map() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		update_user_meta( $user_id, 'billing_phone', '0912 123 4567' );

		$response = $this->post( '/contact', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ), array( 'contact' => $this->contact( '+989121234567' ) ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['created'] );
		$this->assertSame( $user_id, get_current_user_id() );
		$this->assertSame( '279058397', get_user_meta( $user_id, 'mw24_eitaa_user_id', true ) );
		$this->assertSame( '09121234567', get_user_meta( $user_id, 'billing_phone', true ) );
	}

	public function test_linked_account_logs_in_from_session() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		update_user_meta( $user_id, 'mw24_eitaa_user_id', '279058397' );

		$response = $this->post( '/session', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ) );
		$this->assertSame( 'ok', $response->get_data()['status'] );
		$this->assertTrue( $response->get_data()['reload'] );
		$this->assertSame( $user_id, get_current_user_id() );
	}

	public function test_privileged_accounts_never_log_in() {
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_user_meta( $admin, 'mw24_eitaa_user_id', '279058397' );
		$response = $this->post( '/session', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ) );
		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 0, get_current_user_id() );

		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		update_user_meta( $editor, 'billing_phone', '09350000000' );
		$other    = array( 'id' => 5005 ) + $this->eitaa;
		$contact  = MW24_Eitaa_Test_Signer::contact(
			array(
				'user_id'      => 5005,
				'phone_number' => '989350000000',
			)
		);
		$response = $this->post( '/contact', MW24_Eitaa_Test_Signer::init_data( $other ), array( 'contact' => $contact ) );
		$this->assertSame( 401, $response->get_status() );
		$this->assertSame( 0, get_current_user_id() );
	}

	public function test_privileged_login_can_be_allowed_explicitly() {
		mw24_eitaa()->settings->update( array( 'allow_privileged' => true ) );
		$editor = self::factory()->user->create( array( 'role' => 'editor' ) );
		update_user_meta( $editor, 'mw24_eitaa_user_id', '279058397' );
		$this->assertSame( 'ok', $this->post( '/session', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ) )->get_data()['status'] );
		$this->assertSame( $editor, get_current_user_id() );
	}

	public function test_logged_in_visitor_is_linked_without_reload() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );
		$data = $this->post( '/session', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ) )->get_data();
		$this->assertTrue( $data['linked'] );
		$this->assertFalse( $data['reload'] );
		$this->assertSame( '279058397', get_user_meta( $user_id, 'mw24_eitaa_user_id', true ) );
		$this->assertSame( 1, did_action( 'mw24_eitaa_user_linked' ) );
	}

	public function test_logged_in_visitor_cannot_steal_a_phone_of_another_account() {
		$owner = self::factory()->user->create();
		update_user_meta( $owner, 'billing_phone', '09121234567' );
		$visitor = self::factory()->user->create();
		wp_set_current_user( $visitor );
		$response = $this->post( '/contact', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ), array( 'contact' => $this->contact( '989121234567' ) ) );
		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( '', get_user_meta( $visitor, 'mw24_eitaa_user_id', true ) );
	}

	public function test_registration_can_be_closed() {
		mw24_eitaa()->settings->update( array( 'allow_registration' => false ) );
		$response = $this->post( '/contact', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ), array( 'contact' => $this->contact( '989121234567' ) ) );
		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'mw24_eitaa_registration_disabled', $response->get_data()['code'] );
	}

	public function test_non_iranian_numbers_are_refused() {
		$response = $this->post( '/contact', MW24_Eitaa_Test_Signer::init_data( $this->eitaa ), array( 'contact' => $this->contact( '+447700900123' ) ) );
		$this->assertSame( 422, $response->get_status() );
	}

	public function test_requests_are_rate_limited() {
		$GLOBALS['mw24_eitaa_test_strict_limits'] = true;
		$filter                                   = static function () {
			return array(
				'ip'    => array( 1000, 600 ),
				'eitaa' => array( 2, 600 ),
				'route' => array( 1000, 600 ),
			);
		};
		add_filter( 'mw24_eitaa_rate_limits', $filter, 20 );
		$eitaa = array( 'id' => 777000 + wp_rand( 1, 99999 ) ) + $this->eitaa;
		$codes = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$codes[] = $this->post( '/session', MW24_Eitaa_Test_Signer::init_data( $eitaa ) )->get_status();
		}
		remove_filter( 'mw24_eitaa_rate_limits', $filter, 20 );
		$this->assertSame( array( 200, 200, 429 ), $codes );
	}

	public function test_route_resolves_white_listed_targets_only() {
		$post_id = self::factory()->post->create();
		mw24_eitaa()->settings->update( array( 'start_routes' => "offers = /shop/offers/\nevil = https://evil.example/" ) );
		$request = new WP_REST_Request( 'GET', '/mw24-eitaa/v1/route' );
		$request->set_query_params( array( 'start' => 'p' . $post_id ) );
		$this->assertSame( get_permalink( $post_id ), rest_do_request( $request )->get_data()['url'] );

		$request->set_query_params( array( 'start' => 'u:offers' ) );
		$this->assertSame( home_url( '/shop/offers/' ), rest_do_request( $request )->get_data()['url'] );

		$request->set_query_params( array( 'start' => 'u:evil' ) );
		$this->assertSame( '', rest_do_request( $request )->get_data()['url'] );
	}

	public function test_status_endpoint_is_admin_only_and_hides_the_token() {
		$request = new WP_REST_Request( 'GET', '/mw24-eitaa/v1/status' );
		$this->assertContains( rest_do_request( $request )->get_status(), array( 401, 403 ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['token_configured'] );
		$this->assertStringNotContainsString( 'TEST_TOKEN', wp_json_encode( $response->get_data() ) );
	}
}
