<?php
/**
 * Signature validation tests (initData and requestContact responses).
 *
 * @package MW24_Eitaa
 */

/**
 * @covers MW24_Eitaa_Validator
 */
class Test_MW24_Eitaa_Validator extends WP_UnitTestCase {

	/** Token published in the official Eitaa documentation example (not a secret). */
	const DOC_TOKEN = '5768337691:AAGDAe6rjxu1cUgxK4BizYi--Utc3J9v5AU';

	/** initData of the official documentation example. */
	const DOC_INIT = 'auth_date=1709144340&device_id=5d41402abc4b2a76b9719d911017c592&chat_instance=-3788475317572404878&chat_type=private&user=%7B%22id%22%3A279058397%2C%22first_name%22%3A%22%D9%85%D9%87%D8%AF%DB%8C%22%2C%22last_name%22%3A%22%D9%87%D8%A7%D8%B4%D9%85%DB%8C%22%2C%22language_code%22%3A%22fa%22%2C%22allows_write_to_pm%22%3Atrue%7D&hash=c3bb1efcada7b75eec382110554ab07e57622e982a72cac38ba3e64f51d40bb9';

	public function test_secret_key_matches_documentation() {
		$this->assertSame(
			'aa492a44bdf019c759defb1698c1d77690189973945491a756051cdc1207a449',
			bin2hex( MW24_Eitaa_Validator::secret_key( self::DOC_TOKEN ) )
		);
	}

	public function test_official_documentation_vector_is_valid() {
		$fields = MW24_Eitaa_Validator::validate( self::DOC_INIT, self::DOC_TOKEN, 0 );
		$this->assertIsArray( $fields );
		$user = MW24_Eitaa_Validator::extract_user( $fields );
		$this->assertSame( '279058397', $user['id'] );
		$this->assertSame( 'مهدی', $user['first_name'] );
		$this->assertSame( 'هاشمی', $user['last_name'] );
		$this->assertTrue( $user['allows_write_to_pm'] );
		$this->assertSame( 'fa', $user['language_code'] );
	}

	public function test_documentation_vector_with_age_check_is_expired() {
		$result = MW24_Eitaa_Validator::validate( self::DOC_INIT, self::DOC_TOKEN, DAY_IN_SECONDS );
		$this->assertWPError( $result );
		$this->assertSame( 'expired', $result->get_error_code() );
	}

	public function test_data_check_string_is_sorted_and_newline_joined() {
		$string = MW24_Eitaa_Validator::data_check_string(
			array(
				'user'      => '{"id":1}',
				'hash'      => 'ignored',
				'auth_date' => '100',
				'chat_type' => 'private',
			)
		);
		$this->assertSame( "auth_date=100\nchat_type=private\nuser={\"id\":1}", $string );
	}

	public function test_fake_token_round_trip() {
		$raw    = MW24_Eitaa_Test_Signer::init_data( array( 'id' => 42, 'first_name' => 'Ali' ) );
		$fields = MW24_Eitaa_Validator::validate( $raw, MW24_Eitaa_Test_Signer::TOKEN );
		$this->assertIsArray( $fields );
		$this->assertSame( '42', MW24_Eitaa_Validator::extract_user( $fields )['id'] );
	}

	public function test_pair_order_does_not_matter() {
		$raw   = MW24_Eitaa_Test_Signer::init_data( array( 'id' => 7 ) );
		$pairs = explode( '&', $raw );
		shuffle( $pairs );
		$this->assertIsArray( MW24_Eitaa_Validator::validate( implode( '&', array_reverse( $pairs ) ), MW24_Eitaa_Test_Signer::TOKEN ) );
	}

	public function test_wrong_token_is_rejected() {
		$raw    = MW24_Eitaa_Test_Signer::init_data( array( 'id' => 42 ) );
		$result = MW24_Eitaa_Validator::validate( $raw, '1:OTHER_TOKEN_xxxxxxxxxxxxxxxx' );
		$this->assertWPError( $result );
		$this->assertSame( 'bad_hash', $result->get_error_code() );
	}

	public function test_tampered_user_is_rejected() {
		$raw      = MW24_Eitaa_Test_Signer::init_data( array( 'id' => 42 ) );
		$tampered = str_replace( rawurlencode( '"id":42' ), rawurlencode( '"id":43' ), $raw );
		$this->assertNotSame( $raw, $tampered );
		$this->assertWPError( MW24_Eitaa_Validator::validate( $tampered, MW24_Eitaa_Test_Signer::TOKEN ) );
	}

	public function test_added_field_is_rejected() {
		$raw = MW24_Eitaa_Test_Signer::init_data( array( 'id' => 42 ) ) . '&start_param=evil';
		$this->assertWPError( MW24_Eitaa_Validator::validate( $raw, MW24_Eitaa_Test_Signer::TOKEN ) );
	}

	public function test_missing_or_malformed_hash() {
		$this->assertSame( 'missing_hash', MW24_Eitaa_Validator::validate( 'auth_date=1&user=%7B%7D', MW24_Eitaa_Test_Signer::TOKEN )->get_error_code() );
		$this->assertSame( 'missing_hash', MW24_Eitaa_Validator::validate( 'auth_date=1&hash=xyz', MW24_Eitaa_Test_Signer::TOKEN )->get_error_code() );
	}

	public function test_duplicate_keys_are_rejected() {
		$raw = MW24_Eitaa_Test_Signer::init_data( array( 'id' => 42 ) ) . '&auth_date=1';
		$this->assertSame( 'duplicate_key', MW24_Eitaa_Validator::validate( $raw, MW24_Eitaa_Test_Signer::TOKEN )->get_error_code() );
	}

	public function test_future_auth_date_is_rejected() {
		$raw = MW24_Eitaa_Test_Signer::init_data( array( 'id' => 42 ), array( 'auth_date' => (string) ( time() + HOUR_IN_SECONDS ) ) );
		$this->assertSame( 'future_auth_date', MW24_Eitaa_Validator::validate( $raw, MW24_Eitaa_Test_Signer::TOKEN )->get_error_code() );
	}

	public function test_small_clock_skew_is_tolerated() {
		$raw = MW24_Eitaa_Test_Signer::init_data( array( 'id' => 42 ), array( 'auth_date' => (string) ( time() + 60 ) ) );
		$this->assertIsArray( MW24_Eitaa_Validator::validate( $raw, MW24_Eitaa_Test_Signer::TOKEN ) );
	}

	public function test_age_check_uses_injected_clock() {
		$raw = MW24_Eitaa_Test_Signer::init_data( array( 'id' => 42 ), array( 'auth_date' => '1000' ) );
		$this->assertIsArray( MW24_Eitaa_Validator::validate( $raw, MW24_Eitaa_Test_Signer::TOKEN, 100, 1050 ) );
		$this->assertSame( 'expired', MW24_Eitaa_Validator::validate( $raw, MW24_Eitaa_Test_Signer::TOKEN, 100, 1101 )->get_error_code() );
	}

	public function test_empty_token_is_rejected() {
		$this->assertSame( 'no_token', MW24_Eitaa_Validator::validate( self::DOC_INIT, '' )->get_error_code() );
	}

	public function test_parse_keeps_keys_verbatim_and_decodes_plus() {
		$fields = MW24_Eitaa_Validator::parse( 'a.b=1&c%5Bd%5D=x+y&e=%2B98' );
		$this->assertSame(
			array(
				'a.b'  => '1',
				'c[d]' => 'x y',
				'e'    => '+98',
			),
			$fields
		);
	}

	public function test_contact_response_is_validated_and_extracted() {
		$raw     = MW24_Eitaa_Test_Signer::contact(
			array(
				'user_id'      => 279058397,
				'phone_number' => '989121234567',
				'first_name'   => 'مهدی',
			)
		);
		$fields  = MW24_Eitaa_Validator::validate( $raw, MW24_Eitaa_Test_Signer::TOKEN );
		$contact = MW24_Eitaa_Validator::extract_contact( $fields );
		$this->assertSame( '279058397', $contact['user_id'] );
		$this->assertSame( '989121234567', $contact['phone_number'] );
		$this->assertSame( 'مهدی', $contact['first_name'] );
	}

	public function test_flat_contact_shape_is_supported() {
		$fields  = array(
			'phone_number' => '09121234567',
			'user_id'      => '5',
			'hash'         => str_repeat( 'a', 64 ),
		);
		$contact = MW24_Eitaa_Validator::extract_contact( $fields );
		$this->assertSame( '5', $contact['user_id'] );
		$this->assertSame( '09121234567', $contact['phone_number'] );
	}

	public function test_contact_without_phone_is_an_error() {
		$this->assertWPError( MW24_Eitaa_Validator::extract_contact( array( 'contact' => '{"user_id":1}' ) ) );
	}

	public function test_normalize_id() {
		$this->assertSame( '279058397', MW24_Eitaa_Validator::normalize_id( 279058397 ) );
		$this->assertSame( '4503599627370495', MW24_Eitaa_Validator::normalize_id( '4503599627370495' ) );
		$this->assertSame( '12', MW24_Eitaa_Validator::normalize_id( '0012' ) );
		$this->assertSame( '', MW24_Eitaa_Validator::normalize_id( -5 ) );
		$this->assertSame( '', MW24_Eitaa_Validator::normalize_id( '12a' ) );
		$this->assertSame( '', MW24_Eitaa_Validator::normalize_id( null ) );
	}

	public function test_user_with_big_id_is_kept_as_string() {
		$fields = MW24_Eitaa_Validator::validate( MW24_Eitaa_Test_Signer::init_data( array( 'id' => 4503599627370495 ) ), MW24_Eitaa_Test_Signer::TOKEN );
		$this->assertSame( '4503599627370495', MW24_Eitaa_Validator::extract_user( $fields )['id'] );
	}
}
