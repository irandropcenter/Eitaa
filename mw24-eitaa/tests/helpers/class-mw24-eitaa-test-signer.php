<?php
/**
 * Builds signed initData / contact strings with a fake token (test helper).
 *
 * @package MW24_Eitaa
 */

/**
 * Test signer.
 */
class MW24_Eitaa_Test_Signer {

	/** Fake app token used by the whole suite. */
	const TOKEN = '1234567890:TEST_TOKEN_abcdefghijklmnopqrstuvwxyz';

	/**
	 * Signed initData for an Eitaa user.
	 *
	 * @param array  $user  User object (id, first_name ...).
	 * @param array  $extra Extra fields (auth_date, start_param ...).
	 * @param string $token Token.
	 * @return string
	 */
	public static function init_data( array $user, array $extra = array(), $token = self::TOKEN ) {
		$fields = array_merge(
			array(
				'auth_date'     => (string) time(),
				'device_id'     => 'device-' . $user['id'],
				'chat_instance' => '-3788475317572404878',
				'chat_type'     => 'private',
				'user'          => wp_json_encode( $user ),
			),
			$extra
		);
		return MW24_Eitaa_Validator::sign( $fields, $token );
	}

	/**
	 * Signed requestContact response.
	 *
	 * @param array  $contact Contact (user_id, phone_number, first_name ...).
	 * @param array  $extra   Extra fields.
	 * @param string $token   Token.
	 * @return string
	 */
	public static function contact( array $contact, array $extra = array(), $token = self::TOKEN ) {
		return MW24_Eitaa_Validator::sign(
			array_merge(
				array(
					'contact'   => wp_json_encode( $contact ),
					'auth_date' => (string) time(),
				),
				$extra
			),
			$token
		);
	}

	/**
	 * Configures the plugin as connected.
	 *
	 * @return void
	 */
	public static function configure() {
		$settings = mw24_eitaa()->settings;
		$settings->save_token( self::TOKEN );
		$settings->update(
			array(
				'enabled'            => true,
				'login_enabled'      => true,
				'allow_registration' => true,
			)
		);
	}
}
