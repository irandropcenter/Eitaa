<?php
/**
 * Public helper functions for theme and plugin developers.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'mw24_eitaa' ) ) {
	/**
	 * Plugin container.
	 *
	 * @return MW24_Eitaa_Core
	 */
	function mw24_eitaa() {
		return MW24_Eitaa_Core::instance();
	}
}

/**
 * Sends an Eitaa message.
 *
 * $recipient can be a WP_User, a WordPress user ID (the linked Eitaa account is used)
 * or a raw Eitaa chat_id. Use $args['type'] = 'user'|'chat' to remove any ambiguity.
 *
 * @param WP_User|int|string $recipient Recipient.
 * @param string             $text      Plain text message.
 * @param array              $args      type (auto|user|chat), queue (bool), event (string).
 * @return true|int|WP_Error True when sent, queue row id when queued, WP_Error on failure.
 */
function mw24_eitaa_send( $recipient, $text, array $args = array() ) {
	$core = mw24_eitaa();
	$args = wp_parse_args(
		$args,
		array(
			'type'  => 'auto',
			'queue' => false,
			'event' => 'api',
		)
	);
	if ( ! $core->settings->has_token() ) {
		return new WP_Error( 'mw24_eitaa_no_token', __( 'The Eitaa app token is not configured.', 'mw24-eitaa' ) );
	}
	$user_id = 0;
	$chat_id = '';
	if ( $recipient instanceof WP_User ) {
		$user_id = (int) $recipient->ID;
	} elseif ( 'chat' !== $args['type'] && is_numeric( $recipient ) && get_userdata( (int) $recipient ) && ( 'user' === $args['type'] || '' !== MW24_Eitaa_Auth::get_chat_id( (int) $recipient ) ) ) {
		$user_id = (int) $recipient;
	} else {
		$chat_id = preg_replace( '/\D/', '', (string) $recipient );
	}
	if ( $user_id ) {
		$chat_id = MW24_Eitaa_Auth::get_chat_id( $user_id );
	}
	if ( '' === $chat_id ) {
		return new WP_Error( 'mw24_eitaa_no_chat', __( 'The recipient has no Eitaa account connected.', 'mw24-eitaa' ) );
	}
	$text = $core->notifications->render( (string) $text, $core->notifications->common_vars( $user_id ? get_userdata( $user_id ) : null ), (string) $args['event'], array( 'user_id' => $user_id ) );
	if ( $args['queue'] ) {
		$id = $core->queue->enqueue(
			$chat_id,
			$text,
			array(
				'user_id' => $user_id,
				'event'   => (string) $args['event'],
			)
		);
		return $id ? $id : new WP_Error( 'mw24_eitaa_queue_failed', __( 'The message could not be queued.', 'mw24-eitaa' ) );
	}
	return $core->messenger->send(
		$chat_id,
		$text,
		array(
			'user_id' => $user_id,
			'event'   => (string) $args['event'],
		)
	);
}

/**
 * Eitaa chat id of a user ('' when not linked).
 *
 * @param int $user_id User ID.
 * @return string
 */
function mw24_eitaa_get_chat_id( $user_id ) {
	return MW24_Eitaa_Auth::get_chat_id( (int) $user_id );
}

/**
 * Whether the current request is rendered inside the Eitaa mini-app.
 *
 * @return bool
 */
function mw24_eitaa_is_mini_app() {
	return mw24_eitaa()->frontend->is_mini_app();
}

/**
 * Normalizes an Iranian mobile number to 09XXXXXXXXX ('' when invalid).
 *
 * @param string $phone Phone.
 * @return string
 */
function mw24_eitaa_normalize_phone( $phone ) {
	return MW24_Eitaa_Phone_Normalizer::normalize( $phone );
}

/**
 * Best known phone of a user across all phone maps.
 *
 * @param int $user_id User ID.
 * @return string
 */
function mw24_eitaa_get_user_phone( $user_id ) {
	return mw24_eitaa()->mapper->read_phone( (int) $user_id );
}

/**
 * Finds the WordPress user that owns a phone number (all maps, all variants).
 *
 * @param string $phone Phone.
 * @return int User ID or 0.
 */
function mw24_eitaa_find_user_by_phone( $phone ) {
	$found = mw24_eitaa()->mapper->find_user( $phone );
	return (int) $found['user_id'];
}

/**
 * Returns the "Log in with Eitaa" button markup.
 *
 * @param array $args label, class, redirect, show_linked.
 * @return string
 */
function mw24_eitaa_login_button( array $args = array() ) {
	return mw24_eitaa()->login_button->render( $args );
}
