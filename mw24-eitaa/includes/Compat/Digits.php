<?php
/**
 * Digits integration.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Digits stores the phone as digits_phone (+98XXXXXXXXXX), digits_phone_no
 * (XXXXXXXXXX) and digt_countrycode (+98). The "digits" profile already writes these
 * fields; when Digits' own API is available it is called too so that any internal
 * logic of Digits runs as well.
 */
class MW24_Eitaa_Compat_Digits {

	/**
	 * Mapper.
	 *
	 * @var MW24_Eitaa_Phone_Mapper
	 */
	private $mapper;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Phone_Mapper $mapper Mapper.
	 */
	public function __construct( $mapper ) {
		$this->mapper = $mapper;
	}

	/**
	 * Whether Digits is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return function_exists( 'digits_update_mobile' ) || function_exists( 'getUserFromPhone' ) || defined( 'DIGITS_VERSION' );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'mw24_eitaa_phone_verified', array( $this, 'sync' ), 20, 2 );
	}

	/**
	 * Calls Digits' own updater after an Eitaa-verified number was stored.
	 *
	 * @param int    $user_id User.
	 * @param string $phone   Canonical phone.
	 * @return void
	 */
	public function sync( $user_id, $phone ) {
		if ( ! function_exists( 'digits_update_mobile' ) || ! $this->mapper->profile_active( 'digits' ) ) {
			return;
		}
		$national = substr( (string) $phone, 1 );
		$owner    = $this->mapper->find_user( $phone, (int) $user_id );
		foreach ( $owner['matches'] as $match ) {
			if ( 0 === strpos( $match['map'], 'profile:digits:' ) ) {
				return; // Another user already owns this number in Digits.
			}
		}
		digits_update_mobile( (int) $user_id, '+98', $national );
	}
}
