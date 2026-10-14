<?php
/**
 * Eitaayar API client (sendMessage, verify).
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * HTTP client for https://eitaayar.ir/api/app/*.
 * Requests are only made when sending a message, testing the token or verifying a
 * hash; nothing is called while normal admin pages load.
 */
class MW24_Eitaa_Messenger {

	const LAST_SEND_OPTION = 'mw24_eitaa_last_send';
	const MAX_LENGTH       = 4096;

	/**
	 * Settings.
	 *
	 * @var MW24_Eitaa_Settings
	 */
	private $settings;

	/**
	 * Logger.
	 *
	 * @var MW24_Eitaa_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Settings $settings Settings.
	 * @param MW24_Eitaa_Logger   $logger   Logger.
	 */
	public function __construct( $settings, $logger ) {
		$this->settings = $settings;
		$this->logger   = $logger;
	}

	/**
	 * Sends a text message to an Eitaa user (only users who opened the mini-app and
	 * granted permission can receive messages).
	 *
	 * @param string $chat_id Eitaa user id.
	 * @param string $text    Plain text.
	 * @param array  $context event, user_id (for logs).
	 * @return true|WP_Error
	 */
	public function send( $chat_id, $text, array $context = array() ) {
		$token   = $this->settings->get_token();
		$chat_id = preg_replace( '/\D/', '', (string) $chat_id );
		$text    = trim( (string) $text );
		if ( '' === $token ) {
			return new WP_Error( 'mw24_eitaa_no_token', __( 'The Eitaa app token is not configured.', 'mw24-eitaa' ) );
		}
		if ( '' === $chat_id ) {
			return new WP_Error( 'mw24_eitaa_no_chat', __( 'The recipient has no Eitaa account connected.', 'mw24-eitaa' ) );
		}
		if ( '' === $text ) {
			return new WP_Error( 'mw24_eitaa_empty_text', __( 'The message text is empty.', 'mw24-eitaa' ) );
		}
		$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::MAX_LENGTH ) : substr( $text, 0, self::MAX_LENGTH );

		$response = $this->request(
			'sendMessage',
			array(
				'token'   => $token,
				'chat_id' => strlen( $chat_id ) < 19 ? (int) $chat_id : $chat_id,
				'text'    => $text,
			)
		);
		$log_ctx  = array(
			'user_id'  => isset( $context['user_id'] ) ? (int) $context['user_id'] : 0,
			'eitaa_id' => $chat_id,
			'event'    => isset( $context['event'] ) ? (string) $context['event'] : 'manual',
		);

		if ( ! is_wp_error( $response ) ) {
			$body = $response['body'];
			if ( isset( $body['ok'] ) && true === $body['ok'] && isset( $body['result'] ) && 'success' === $body['result'] ) {
				$this->record_last( true, $chat_id, $log_ctx['event'], '' );
				$preview = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 80 ) : substr( $text, 0, 80 );
				$this->logger->info( 'msg.sent', 'Message sent: ' . $preview, $log_ctx );

				/**
				 * Fires after a message was delivered to Eitaayar.
				 *
				 * @param string $chat_id Eitaa user id.
				 * @param string $text    Text.
				 * @param array  $context Context.
				 */
				do_action( 'mw24_eitaa_message_sent', $chat_id, $text, $context );
				return true;
			}
			$description = '';
			foreach ( array( 'description', 'message', 'error', 'result' ) as $key ) {
				if ( isset( $body[ $key ] ) && is_scalar( $body[ $key ] ) && '' !== (string) $body[ $key ] ) {
					$description = (string) $body[ $key ];
					break;
				}
			}
			$response = new WP_Error(
				'mw24_eitaa_send_failed',
				'' !== $description
					/* translators: %s: error returned by Eitaayar. */
					? sprintf( __( 'Eitaayar rejected the message: %s', 'mw24-eitaa' ), $description )
					: __( 'Eitaayar did not confirm the delivery. Make sure the user opened your mini-app and allowed messages.', 'mw24-eitaa' )
			);
		}

		$this->record_last( false, $chat_id, $log_ctx['event'], $response->get_error_message() );
		$this->logger->error( 'msg.failed', 'Message failed: ' . $response->get_error_message(), $log_ctx );

		/**
		 * Fires when sending failed.
		 *
		 * @param string   $chat_id Eitaa user id.
		 * @param string   $text    Text.
		 * @param WP_Error $error   Error.
		 * @param array    $context Context.
		 */
		do_action( 'mw24_eitaa_message_failed', $chat_id, $text, $response, $context );
		return $response;
	}

	/**
	 * Optional server-side verification of an initData hash
	 * (valid once, within one minute; max 20 requests per second per IP).
	 *
	 * @param string $hash Hash.
	 * @param string $ip   Client IP.
	 * @return bool|WP_Error true = genuine, false = rejected, WP_Error = network problem.
	 */
	public function verify_hash( $hash, $ip ) {
		$token = $this->settings->get_token();
		if ( '' === $token ) {
			return new WP_Error( 'mw24_eitaa_no_token', 'No token.' );
		}
		$response = $this->request(
			'verify',
			array(
				'token' => $token,
				'hash'  => (string) $hash,
				'ip'    => (string) $ip,
			),
			8
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		return isset( $response['body']['ok'] ) && true === $response['body']['ok'];
	}

	/**
	 * Performs a JSON POST request.
	 *
	 * @param string $method  API method.
	 * @param array  $body    Body.
	 * @param int    $timeout Timeout in seconds.
	 * @return array|WP_Error array( code, body )
	 */
	private function request( $method, array $body, $timeout = 15 ) {
		$args = array(
			'timeout'     => $timeout,
			'redirection' => 0,
			'headers'     => array(
				'Content-Type' => 'application/json; charset=utf-8',
				'Accept'       => 'application/json',
			),
			'body'        => wp_json_encode( $body ),
			'data_format' => 'body',
		);

		/**
		 * Filters HTTP arguments for Eitaayar requests (e.g. proxy settings).
		 *
		 * @param array  $args   wp_remote_post() arguments.
		 * @param string $method API method.
		 */
		$args = (array) apply_filters( 'mw24_eitaa_http_args', $args, $method );
		unset( $args['sslverify'] );

		$response = wp_remote_post( MW24_EITAA_API_BASE . $method, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'mw24_eitaa_http_error',
				/* translators: %s: transport error. */
				sprintf( __( 'Could not reach Eitaayar: %s', 'mw24-eitaa' ), $response->get_error_message() )
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$json = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $json ) ) {
			return new WP_Error(
				'mw24_eitaa_bad_response',
				/* translators: %d: HTTP status code. */
				sprintf( __( 'Unexpected response from Eitaayar (HTTP %d).', 'mw24-eitaa' ), $code )
			);
		}
		return array(
			'code' => $code,
			'body' => $json,
		);
	}

	/**
	 * Remembers the last send result for the dashboard.
	 *
	 * @param bool   $ok      Success.
	 * @param string $chat_id Recipient.
	 * @param string $event   Event.
	 * @param string $error   Error message.
	 * @return void
	 */
	private function record_last( $ok, $chat_id, $event, $error ) {
		update_option(
			self::LAST_SEND_OPTION,
			array(
				'time'    => time(),
				'ok'      => (bool) $ok,
				'chat_id' => substr( $chat_id, 0, 3 ) . str_repeat( '*', max( 0, strlen( $chat_id ) - 5 ) ) . substr( $chat_id, -2 ),
				'event'   => sanitize_key( $event ),
				'error'   => substr( wp_strip_all_tags( $error ), 0, 300 ),
			),
			false
		);
	}

	/**
	 * Last send result.
	 *
	 * @return array
	 */
	public function last_send() {
		$last = get_option( self::LAST_SEND_OPTION, array() );
		return is_array( $last ) ? $last : array();
	}
}
