<?php
/**
 * Login, registration and account linking with Eitaa identities.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Authentication service used by the REST endpoints.
 */
class MW24_Eitaa_Auth {

	const META_EITAA_ID       = 'mw24_eitaa_user_id';
	const META_ALLOWS_PM      = 'mw24_eitaa_allows_pm';
	const META_FIRST_NAME     = 'mw24_eitaa_first_name';
	const META_LINKED_AT      = 'mw24_eitaa_linked_at';
	const META_PHONE          = 'mw24_eitaa_phone';
	const META_PHONE_VERIFIED = 'mw24_eitaa_phone_verified_at';
	const META_LANGUAGE       = 'mw24_eitaa_language';
	const META_LAST_SEEN      = 'mw24_eitaa_last_seen';

	/** Anti-replay window for contact hashes. */
	const REPLAY_TTL = 20 * MINUTE_IN_SECONDS;

	/**
	 * Settings.
	 *
	 * @var MW24_Eitaa_Settings
	 */
	private $settings;

	/**
	 * Mapper.
	 *
	 * @var MW24_Eitaa_Phone_Mapper
	 */
	private $mapper;

	/**
	 * Messenger.
	 *
	 * @var MW24_Eitaa_Messenger
	 */
	private $messenger;

	/**
	 * Logger.
	 *
	 * @var MW24_Eitaa_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Settings     $settings  Settings.
	 * @param MW24_Eitaa_Phone_Mapper $mapper    Phone mapper.
	 * @param MW24_Eitaa_Messenger    $messenger Messenger.
	 * @param MW24_Eitaa_Logger       $logger    Logger.
	 */
	public function __construct( $settings, $mapper, $messenger, $logger ) {
		$this->settings  = $settings;
		$this->mapper    = $mapper;
		$this->messenger = $messenger;
		$this->logger    = $logger;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'delete_user', array( $this, 'on_delete_user' ) );
	}

	// Public flows.

	/**
	 * POST /session: signs in a linked Eitaa account, links the logged-in visitor,
	 * or asks the client for the phone number.
	 *
	 * @param string $init_raw initData from the X-Eitaa-Init-Data header.
	 * @param array  $args     Options: write_access (bool).
	 * @return array|WP_Error
	 */
	public function session( $init_raw, array $args = array() ) {
		$check = $this->ensure_active();
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$init = $this->validate_init_data( $init_raw );
		if ( is_wp_error( $init ) ) {
			return $init;
		}
		$eitaa = $init['user'];
		if ( ! MW24_Eitaa_Rate_Limiter::hit( 'eitaa', $eitaa['id'] ) ) {
			return $this->rate_limited();
		}
		if ( ! empty( $args['write_access'] ) ) {
			$eitaa['allows_write_to_pm'] = true;
		}
		$current = wp_get_current_user();
		$linked  = $this->find_user_by_eitaa_id( $eitaa['id'] );

		if ( $current->exists() ) {
			if ( $linked && (int) $linked->ID !== (int) $current->ID ) {
				$this->logger->warning(
					'auth.link_conflict',
					'Eitaa account already linked to another user.',
					array(
						'user_id'  => $current->ID,
						'eitaa_id' => $eitaa['id'],
					)
				);
				return array(
					'status'  => 'ok',
					'linked'  => false,
					'reload'  => false,
					'message' => __( 'This Eitaa account is already connected to another user account.', 'mw24-eitaa' ),
				);
			}
			$was_linked = (bool) $linked;
			$result     = $this->link( (int) $current->ID, $eitaa );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return array(
				'status'   => 'ok',
				'linked'   => true,
				'new_link' => ! $was_linked,
				'reload'   => false,
			);
		}

		if ( ! $this->settings->get( 'login_enabled' ) ) {
			return array( 'status' => 'disabled' );
		}

		if ( $linked ) {
			$allowed = $this->can_login( $linked );
			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}
			$this->update_eitaa_meta( (int) $linked->ID, $eitaa );
			$this->login( $linked, 'eitaa_id' );
			return array(
				'status'   => 'ok',
				'reload'   => true,
				'redirect' => $this->after_login_url(),
			);
		}

		$this->logger->debug( 'auth.need_phone', 'Unknown Eitaa account, phone requested.', array( 'eitaa_id' => $eitaa['id'] ) );
		return array( 'status' => 'need_phone' );
	}

	/**
	 * POST /contact: validates initData + signed contact, finds or creates the user,
	 * writes the number to every phone map and logs the user in.
	 *
	 * @param string $init_raw    initData.
	 * @param string $contact_raw Signed contact response (data.response of requestContact).
	 * @return array|WP_Error
	 */
	public function contact( $init_raw, $contact_raw ) {
		$check = $this->ensure_active();
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$init = $this->validate_init_data( $init_raw );
		if ( is_wp_error( $init ) ) {
			return $init;
		}
		$eitaa = $init['user'];
		if ( ! MW24_Eitaa_Rate_Limiter::hit( 'eitaa', $eitaa['id'] ) ) {
			return $this->rate_limited();
		}
		$contact = $this->validate_contact( $contact_raw, $eitaa );
		if ( is_wp_error( $contact ) ) {
			return $contact;
		}
		$phone = MW24_Eitaa_Phone_Normalizer::normalize( $contact['phone_number'] );
		if ( '' === $phone ) {
			$this->logger->warning(
				'auth.phone_invalid',
				'Shared phone is not an Iranian mobile number.',
				array(
					'eitaa_id' => $eitaa['id'],
					'phone'    => $contact['phone_number'],
				)
			);
			return new WP_Error( 'mw24_eitaa_phone_invalid', __( 'Only Iranian mobile numbers can be used to log in.', 'mw24-eitaa' ), array( 'status' => 422 ) );
		}

		$current = wp_get_current_user();
		$linked  = $this->find_user_by_eitaa_id( $eitaa['id'] );

		if ( $current->exists() ) {
			if ( $linked && (int) $linked->ID !== (int) $current->ID ) {
				return new WP_Error( 'mw24_eitaa_conflict', __( 'This Eitaa account is already connected to another user account.', 'mw24-eitaa' ), array( 'status' => 409 ) );
			}
			$owner = $this->mapper->find_user( $phone, (int) $current->ID );
			if ( $owner['user_id'] ) {
				$this->logger->warning(
					'auth.phone_conflict',
					'Phone belongs to another account; not linked.',
					array(
						'user_id'  => $current->ID,
						'eitaa_id' => $eitaa['id'],
						'phone'    => $phone,
					)
				);
				return new WP_Error( 'mw24_eitaa_conflict', __( 'This mobile number belongs to another account on this site.', 'mw24-eitaa' ), array( 'status' => 409 ) );
			}
			$result = $this->link( (int) $current->ID, $eitaa );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$this->store_phone( (int) $current->ID, $phone, $contact, false );
			return array(
				'status' => 'ok',
				'linked' => true,
				'reload' => true,
			);
		}

		if ( ! $this->settings->get( 'login_enabled' ) ) {
			return array( 'status' => 'disabled' );
		}

		$created = false;
		$user    = $linked ? $linked : null;
		$found   = $this->mapper->find_user( $phone );
		if ( ! $user && $found['user_id'] ) {
			$user = get_user_by( 'id', $found['user_id'] );
		}
		if ( $found['conflict'] ) {
			$this->logger->warning(
				'mapping.conflict',
				'Phone matches several users; the first match by priority was used.',
				array(
					'phone' => $phone,
					'users' => array_values( array_unique( wp_list_pluck( $found['matches'], 'user_id' ) ) ),
				)
			);
		}

		if ( ! $user ) {
			if ( ! $this->settings->get( 'allow_registration' ) ) {
				$this->logger->info(
					'auth.registration_closed',
					'No account for this phone and registration is disabled.',
					array(
						'eitaa_id' => $eitaa['id'],
						'phone'    => $phone,
					)
				);
				return new WP_Error( 'mw24_eitaa_registration_disabled', __( 'No account was found for this mobile number and new registrations are closed.', 'mw24-eitaa' ), array( 'status' => 403 ) );
			}
			$user = $this->create_user( $phone, $eitaa, $contact );
			if ( is_wp_error( $user ) ) {
				return $user;
			}
			$created = true;
		} else {
			$allowed = $this->can_login( $user );
			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}
			$this->ensure_site_membership( $user );
			$previous = (string) get_user_meta( $user->ID, self::META_EITAA_ID, true );
			if ( '' !== $previous && $previous !== $eitaa['id'] ) {
				$this->logger->warning(
					'auth.relink',
					'Verified phone owner re-linked to a new Eitaa account.',
					array(
						'user_id'  => $user->ID,
						'eitaa_id' => $eitaa['id'],
						'previous' => $previous,
					)
				);
			}
		}

		$result = $this->link( (int) $user->ID, $eitaa, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->store_phone( (int) $user->ID, $phone, $contact, $created );
		$this->login( $user, $created ? 'register' : 'phone' );

		if ( $created ) {
			/**
			 * Fires after a new user was registered through Eitaa.
			 *
			 * @param int    $user_id User ID.
			 * @param string $phone   Canonical phone.
			 * @param array  $eitaa   Eitaa user data.
			 */
			do_action( 'mw24_eitaa_user_registered', (int) $user->ID, $phone, $eitaa );
		}

		return array(
			'status'   => 'ok',
			'reload'   => true,
			'created'  => $created,
			'redirect' => $this->after_login_url(),
		);
	}

	/**
	 * POST /link for a logged-in user (optionally with a signed contact).
	 *
	 * @param string $init_raw    initData.
	 * @param string $contact_raw Optional contact.
	 * @return array|WP_Error
	 */
	public function link_current( $init_raw, $contact_raw = '' ) {
		if ( '' !== (string) $contact_raw ) {
			return $this->contact( $init_raw, $contact_raw );
		}
		return $this->session( $init_raw );
	}

	// Validation.

	/**
	 * Ensures the connection is configured.
	 *
	 * @return true|WP_Error
	 */
	private function ensure_active() {
		if ( ! $this->settings->is_active() ) {
			return new WP_Error( 'mw24_eitaa_disabled', __( 'Eitaa connection is not active on this site.', 'mw24-eitaa' ), array( 'status' => 503 ) );
		}
		return true;
	}

	/**
	 * Validates initData and (optionally) verifies the hash with Eitaa's server.
	 *
	 * @param string $raw initData.
	 * @return array|WP_Error array( fields, user )
	 */
	public function validate_init_data( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return new WP_Error( 'mw24_eitaa_bad_request', __( 'Eitaa data is missing. Please open the site from the Eitaa mini-app.', 'mw24-eitaa' ), array( 'status' => 400 ) );
		}
		$fields = MW24_Eitaa_Validator::validate( $raw, $this->settings->get_token(), $this->settings->max_age() );
		if ( is_wp_error( $fields ) ) {
			return $this->auth_failed( 'init_' . $fields->get_error_code() );
		}
		$user = MW24_Eitaa_Validator::extract_user( $fields );
		if ( is_wp_error( $user ) ) {
			return $this->auth_failed( 'init_' . $user->get_error_code() );
		}
		if ( $this->settings->get( 'remote_verify' ) ) {
			$verified = $this->remote_verify( $user['hash'] );
			if ( is_wp_error( $verified ) ) {
				return $verified;
			}
		}
		return array(
			'fields' => $fields,
			'user'   => $user,
		);
	}

	/**
	 * Optional second check with https://eitaayar.ir/api/app/verify.
	 * A hash can be verified only once and only within one minute, so a successful
	 * result is remembered for the lifetime of the initData.
	 *
	 * @param string $hash initData hash.
	 * @return true|WP_Error
	 */
	private function remote_verify( $hash ) {
		$key = 'mw24_eitaa_rv_' . md5( $hash );
		if ( get_transient( $key ) ) {
			return true;
		}
		$result = $this->messenger->verify_hash( $hash, MW24_Eitaa_Rate_Limiter::client_ip() );
		if ( true === $result ) {
			set_transient( $key, 1, $this->settings->max_age() );
			return true;
		}
		if ( is_wp_error( $result ) ) {
			// Network problems do not block users: the HMAC signature is already valid.
			$this->logger->warning( 'auth.remote_verify_error', 'Eitaa verify service unreachable; HMAC result used.', array( 'error' => $result->get_error_message() ) );
			return true;
		}
		return $this->auth_failed( 'remote_verify_rejected' );
	}

	/**
	 * Validates a requestContact response against the current Eitaa user.
	 *
	 * @param string $raw   Signed contact string.
	 * @param array  $eitaa Eitaa user from initData.
	 * @return array|WP_Error
	 */
	public function validate_contact( $raw, array $eitaa ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return new WP_Error( 'mw24_eitaa_bad_request', __( 'The shared contact is missing.', 'mw24-eitaa' ), array( 'status' => 400 ) );
		}
		$max_age = min( HOUR_IN_SECONDS, $this->settings->max_age() );
		$fields  = MW24_Eitaa_Validator::validate( $raw, $this->settings->get_token(), $max_age );
		if ( is_wp_error( $fields ) ) {
			return $this->auth_failed( 'contact_' . $fields->get_error_code(), array( 'eitaa_id' => $eitaa['id'] ) );
		}
		$replay_key = 'mw24_eitaa_ch_' . md5( $fields['hash'] );
		if ( get_transient( $replay_key ) ) {
			return $this->auth_failed( 'contact_replay', array( 'eitaa_id' => $eitaa['id'] ) );
		}
		set_transient( $replay_key, 1, self::REPLAY_TTL );

		$contact = MW24_Eitaa_Validator::extract_contact( $fields );
		if ( is_wp_error( $contact ) ) {
			return $this->auth_failed( 'contact_' . $contact->get_error_code(), array( 'eitaa_id' => $eitaa['id'] ) );
		}
		if ( '' === $contact['user_id'] || $contact['user_id'] !== $eitaa['id'] ) {
			return $this->auth_failed( 'contact_user_mismatch', array( 'eitaa_id' => $eitaa['id'] ) );
		}
		return $contact;
	}

	/**
	 * Generic authentication error (same message for every reason to avoid leaking
	 * information); the real reason goes to the log only.
	 *
	 * @param string $reason  Internal reason.
	 * @param array  $context Log context.
	 * @return WP_Error
	 */
	public function auth_failed( $reason, array $context = array() ) {
		$this->logger->warning( 'auth.failed', 'Authentication rejected: ' . $reason, $context );
		return new WP_Error(
			'mw24_eitaa_auth_failed',
			__( 'Eitaa login failed. Please close the mini-app, open it again and retry.', 'mw24-eitaa' ),
			array( 'status' => 401 )
		);
	}

	/**
	 * Rate limit error.
	 *
	 * @return WP_Error
	 */
	public function rate_limited() {
		$this->logger->warning( 'auth.rate_limited', 'Too many requests.' );
		return new WP_Error( 'mw24_eitaa_rate_limited', __( 'Too many attempts. Please wait a few minutes and try again.', 'mw24-eitaa' ), array( 'status' => 429 ) );
	}

	// Users.

	/**
	 * Finds the user linked to an Eitaa id (network wide on multisite).
	 *
	 * @param string $eitaa_id Eitaa user id.
	 * @return WP_User|null
	 */
	public function find_user_by_eitaa_id( $eitaa_id ) {
		$eitaa_id = MW24_Eitaa_Validator::normalize_id( (string) $eitaa_id );
		if ( '' === $eitaa_id ) {
			return null;
		}
		$users = get_users(
			array(
				'blog_id'     => 0,
				'meta_key'    => self::META_EITAA_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => $eitaa_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'      => 2,
				'orderby'     => 'ID',
				'order'       => 'ASC',
				'count_total' => false,
			)
		);
		if ( count( $users ) > 1 ) {
			$this->logger->warning( 'auth.duplicate_link', 'Eitaa id linked to several users; the oldest one is used.', array( 'eitaa_id' => $eitaa_id ) );
		}
		return $users ? $users[0] : null;
	}

	/**
	 * Eitaa chat id of a WordPress user ('' if not linked).
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_chat_id( $user_id ) {
		return (string) get_user_meta( (int) $user_id, self::META_EITAA_ID, true );
	}

	/**
	 * Links a user to an Eitaa account.
	 *
	 * @param int   $user_id    User ID.
	 * @param array $eitaa      Eitaa user data.
	 * @param bool  $take_over  Remove the link from another user (phone-verified flows only).
	 * @return true|WP_Error
	 */
	public function link( $user_id, array $eitaa, $take_over = false ) {
		$user_id = (int) $user_id;
		$other   = $this->find_user_by_eitaa_id( $eitaa['id'] );
		if ( $other && (int) $other->ID !== $user_id ) {
			if ( ! $take_over ) {
				return new WP_Error( 'mw24_eitaa_conflict', __( 'This Eitaa account is already connected to another user account.', 'mw24-eitaa' ), array( 'status' => 409 ) );
			}
			$this->unlink( (int) $other->ID, 'taken_over' );
		}
		$previous = self::get_chat_id( $user_id );
		update_user_meta( $user_id, self::META_EITAA_ID, $eitaa['id'] );
		$this->update_eitaa_meta( $user_id, $eitaa );
		if ( $previous !== $eitaa['id'] ) {
			update_user_meta( $user_id, self::META_LINKED_AT, time() );
			$this->logger->info(
				'auth.link',
				'Eitaa account linked.',
				array(
					'user_id'  => $user_id,
					'eitaa_id' => $eitaa['id'],
				)
			);

			/**
			 * Fires when a WordPress user gets linked to an Eitaa account.
			 *
			 * @param int    $user_id  User ID.
			 * @param string $eitaa_id Eitaa user id (= chat_id).
			 * @param array  $eitaa    Eitaa user data from the signed initData.
			 */
			do_action( 'mw24_eitaa_user_linked', $user_id, $eitaa['id'], $eitaa );
		}
		return true;
	}

	/**
	 * Refreshes Eitaa profile meta.
	 *
	 * @param int   $user_id User ID.
	 * @param array $eitaa   Eitaa data.
	 * @return void
	 */
	public function update_eitaa_meta( $user_id, array $eitaa ) {
		$user_id = (int) $user_id;
		update_user_meta( $user_id, self::META_ALLOWS_PM, ! empty( $eitaa['allows_write_to_pm'] ) ? '1' : '0' );
		update_user_meta( $user_id, self::META_FIRST_NAME, sanitize_text_field( $eitaa['first_name'] ) );
		update_user_meta( $user_id, self::META_LANGUAGE, sanitize_key( $eitaa['language_code'] ) );
		update_user_meta( $user_id, self::META_LAST_SEEN, time() );
	}

	/**
	 * Removes the Eitaa link of a user (the phone number is kept).
	 *
	 * @param int    $user_id User ID.
	 * @param string $reason  Reason for the log.
	 * @return bool
	 */
	public function unlink( $user_id, $reason = '' ) {
		$user_id  = (int) $user_id;
		$eitaa_id = self::get_chat_id( $user_id );
		if ( '' === $eitaa_id ) {
			return false;
		}
		foreach ( array( self::META_EITAA_ID, self::META_ALLOWS_PM, self::META_FIRST_NAME, self::META_LINKED_AT, self::META_LANGUAGE, self::META_LAST_SEEN ) as $key ) {
			delete_user_meta( $user_id, $key );
		}
		$this->logger->info(
			'auth.unlink',
			'Eitaa account unlinked (' . sanitize_key( $reason ) . ').',
			array(
				'user_id'  => $user_id,
				'eitaa_id' => $eitaa_id,
			)
		);

		/**
		 * Fires after a user was unlinked from Eitaa.
		 *
		 * @param int    $user_id  User ID.
		 * @param string $eitaa_id Former Eitaa id.
		 * @param string $reason   Reason.
		 */
		do_action( 'mw24_eitaa_user_unlinked', $user_id, $eitaa_id, $reason );
		return true;
	}

	/**
	 * Stores a verified phone everywhere and syncs names.
	 *
	 * @param int    $user_id User ID.
	 * @param string $phone   Canonical phone.
	 * @param array  $contact Contact data.
	 * @param bool   $is_new  Newly created user.
	 * @return void
	 */
	private function store_phone( $user_id, $phone, array $contact, $is_new ) {
		$this->mapper->write_phone( $user_id, $phone, false, true );
		update_user_meta( $user_id, self::META_PHONE, $phone );
		update_user_meta( $user_id, self::META_PHONE_VERIFIED, time() );

		if ( $this->settings->get( 'sync_name' ) ) {
			$first = sanitize_text_field( $contact['first_name'] );
			$last  = sanitize_text_field( $contact['last_name'] );
			$user  = get_userdata( $user_id );
			if ( $user ) {
				$update = array( 'ID' => $user_id );
				if ( '' !== $first && '' === (string) $user->first_name ) {
					$update['first_name'] = $first;
				}
				if ( '' !== $last && '' === (string) $user->last_name ) {
					$update['last_name'] = $last;
				}
				if ( count( $update ) > 1 ) {
					wp_update_user( $update );
				}
				if ( mw24_eitaa()->woo->is_active() ) {
					if ( '' !== $first && '' === (string) get_user_meta( $user_id, 'billing_first_name', true ) ) {
						update_user_meta( $user_id, 'billing_first_name', $first );
					}
					if ( '' !== $last && '' === (string) get_user_meta( $user_id, 'billing_last_name', true ) ) {
						update_user_meta( $user_id, 'billing_last_name', $last );
					}
				}
			}
		}

		/**
		 * Fires after an Eitaa-verified phone was stored for a user.
		 *
		 * @param int    $user_id User ID.
		 * @param string $phone   Canonical phone (09XXXXXXXXX).
		 * @param bool   $is_new  Whether the user was just created.
		 */
		do_action( 'mw24_eitaa_phone_verified', $user_id, $phone, $is_new );
	}

	/**
	 * Creates a WordPress user for a verified phone.
	 *
	 * @param string $phone   Canonical phone.
	 * @param array  $eitaa   Eitaa data.
	 * @param array  $contact Contact data.
	 * @return WP_User|WP_Error
	 */
	public function create_user( $phone, array $eitaa, array $contact ) {
		$national = substr( $phone, 1 );
		$login    = '98' . $national;
		if ( username_exists( $login ) ) {
			$login = 'eitaa_' . $phone;
			if ( username_exists( $login ) ) {
				$login = 'eitaa_' . $phone . '_' . strtolower( wp_generate_password( 5, false, false ) );
			}
		}
		$email = '98' . $national . '@eitaa.invalid';
		if ( email_exists( $email ) ) {
			$email = 'eitaa_' . $eitaa['id'] . '_' . strtolower( wp_generate_password( 5, false, false ) ) . '@eitaa.invalid';
		}
		$first = sanitize_text_field( '' !== $contact['first_name'] ? $contact['first_name'] : $eitaa['first_name'] );
		$last  = sanitize_text_field( '' !== $contact['last_name'] ? $contact['last_name'] : $eitaa['last_name'] );
		$name  = trim( $first . ' ' . $last );

		$userdata = array(
			'user_login'   => $login,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 32, true, true ),
			'role'         => $this->default_role(),
			'first_name'   => $first,
			'last_name'    => $last,
			'display_name' => '' !== $name ? $name : $login,
			'nickname'     => '' !== $first ? $first : $login,
		);

		/**
		 * Filters the data used to create a user registered through Eitaa.
		 *
		 * @param array  $userdata wp_insert_user() data.
		 * @param string $phone    Canonical phone.
		 * @param array  $eitaa    Eitaa user data.
		 * @param array  $contact  Contact data.
		 */
		$userdata = (array) apply_filters( 'mw24_eitaa_new_user_data', $userdata, $phone, $eitaa, $contact );
		$user_id  = wp_insert_user( wp_slash( $userdata ) );
		if ( is_wp_error( $user_id ) ) {
			$this->logger->error(
				'auth.register_failed',
				'wp_insert_user failed: ' . $user_id->get_error_message(),
				array(
					'phone'    => $phone,
					'eitaa_id' => $eitaa['id'],
				)
			);
			return new WP_Error( 'mw24_eitaa_register_failed', __( 'Your account could not be created. Please contact the site administrator.', 'mw24-eitaa' ), array( 'status' => 500 ) );
		}
		$this->logger->info(
			'auth.register',
			'New user registered with Eitaa.',
			array(
				'user_id'  => $user_id,
				'eitaa_id' => $eitaa['id'],
				'phone'    => $phone,
				'role'     => $userdata['role'],
			)
		);
		return get_user_by( 'id', $user_id );
	}

	/**
	 * Role for new users.
	 *
	 * @return string
	 */
	public function default_role() {
		$allowed = self::allowed_roles();
		$role    = (string) $this->settings->get( 'default_role' );
		if ( '' !== $role && isset( $allowed[ $role ] ) ) {
			return $role;
		}
		if ( mw24_eitaa()->woo->is_active() && isset( $allowed['customer'] ) ) {
			return 'customer';
		}
		$site_default = (string) get_option( 'default_role', 'subscriber' );
		if ( isset( $allowed[ $site_default ] ) ) {
			return $site_default;
		}
		return isset( $allowed['subscriber'] ) ? 'subscriber' : (string) key( $allowed );
	}

	/**
	 * Capabilities that mark a privileged account.
	 *
	 * @return string[]
	 */
	public static function privileged_caps() {
		/**
		 * Filters the capabilities that block logging in through Eitaa.
		 *
		 * @param string[] $caps Capabilities.
		 */
		return (array) apply_filters( 'mw24_eitaa_privileged_caps', array( 'edit_posts', 'manage_options' ) );
	}

	/**
	 * Roles that may be assigned to new Eitaa users (no privileged capabilities).
	 *
	 * @return array role => name
	 */
	public static function allowed_roles() {
		$out = array();
		foreach ( wp_roles()->roles as $slug => $role ) {
			$caps       = isset( $role['capabilities'] ) ? (array) $role['capabilities'] : array();
			$privileged = false;
			foreach ( self::privileged_caps() as $cap ) {
				if ( ! empty( $caps[ $cap ] ) ) {
					$privileged = true;
					break;
				}
			}
			if ( ! $privileged ) {
				$out[ $slug ] = translate_user_role( $role['name'] );
			}
		}
		return $out;
	}

	/**
	 * Whether a user has a privileged capability.
	 *
	 * @param WP_User $user User.
	 * @return bool
	 */
	public function is_privileged( $user ) {
		if ( is_multisite() && is_super_admin( $user->ID ) ) {
			return true;
		}
		foreach ( self::privileged_caps() as $cap ) {
			if ( user_can( $user, $cap ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a user may log in through Eitaa.
	 *
	 * @param WP_User $user User.
	 * @return true|WP_Error
	 */
	public function can_login( $user ) {
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return $this->auth_failed( 'user_missing' );
		}
		if ( is_multisite() && ( ! empty( $user->spam ) || ! empty( $user->deleted ) ) ) {
			return $this->auth_failed( 'user_spam', array( 'user_id' => $user->ID ) );
		}
		if ( $this->is_privileged( $user ) && ! $this->settings->get( 'allow_privileged' ) ) {
			return $this->auth_failed( 'privileged_user', array( 'user_id' => $user->ID ) );
		}

		/**
		 * Filters whether a user may log in through Eitaa.
		 *
		 * @param bool|WP_Error $allowed True to allow.
		 * @param WP_User       $user    User.
		 */
		$allowed = apply_filters( 'mw24_eitaa_can_login', true, $user );
		if ( true !== $allowed ) {
			return $this->auth_failed( is_wp_error( $allowed ) ? $allowed->get_error_code() : 'filtered', array( 'user_id' => $user->ID ) );
		}
		return true;
	}

	/**
	 * Adds an existing network user to the current site.
	 *
	 * @param WP_User $user User.
	 * @return void
	 */
	private function ensure_site_membership( $user ) {
		if ( is_multisite() && ! is_user_member_of_blog( $user->ID, get_current_blog_id() ) ) {
			add_user_to_blog( get_current_blog_id(), $user->ID, $this->default_role() );
		}
	}

	/**
	 * Sets the auth cookies.
	 *
	 * @param WP_User $user   User.
	 * @param string  $method eitaa_id|phone|register.
	 * @return void
	 */
	public function login( $user, $method ) {
		$remember = (bool) $this->settings->get( 'trusted_device' );
		$days     = (int) $this->settings->get( 'trusted_days' );
		$callback = null;
		if ( $remember && $days > 0 ) {
			$callback = static function () use ( $days ) {
				return $days * DAY_IN_SECONDS;
			};
			add_filter( 'auth_cookie_expiration', $callback, 99 );
		}
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, $remember );
		if ( $callback ) {
			remove_filter( 'auth_cookie_expiration', $callback, 99 );
		}
		update_user_meta( $user->ID, self::META_LAST_SEEN, time() );
		$this->logger->info(
			'auth.login',
			'User logged in with Eitaa (' . $method . ').',
			array(
				'user_id'  => $user->ID,
				'eitaa_id' => self::get_chat_id( $user->ID ),
			)
		);

		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.

		/**
		 * Fires after a user logged in through Eitaa.
		 *
		 * @param int    $user_id User ID.
		 * @param string $method  eitaa_id|phone|register.
		 */
		do_action( 'mw24_eitaa_user_logged_in', (int) $user->ID, $method );
	}

	/**
	 * URL to open after login ('' = reload the current page).
	 *
	 * @return string
	 */
	public function after_login_url() {
		return (string) $this->settings->get( 'after_login_url' );
	}

	/**
	 * Clears queue/log references when a user is deleted (nothing else to do: user meta
	 * is removed by WordPress).
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function on_delete_user( $user_id ) {
		$eitaa_id = self::get_chat_id( (int) $user_id );
		if ( '' !== $eitaa_id ) {
			$this->logger->info(
				'auth.user_deleted',
				'Linked user deleted.',
				array(
					'user_id'  => (int) $user_id,
					'eitaa_id' => $eitaa_id,
				)
			);
		}
	}
}
