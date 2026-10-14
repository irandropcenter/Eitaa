<?php
/**
 * "Eitaa" column in the users list and Eitaa box on profile screens.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * User screens integration.
 */
class MW24_Eitaa_Admin_User_Profile {

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
		add_filter( 'manage_users_columns', array( $this, 'add_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'render_column' ), 10, 3 );
		add_action( 'show_user_profile', array( $this, 'profile_box' ), 30 );
		add_action( 'edit_user_profile', array( $this, 'profile_box' ), 30 );
		add_action( 'personal_options_update', array( $this, 'save_profile' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile' ) );
	}

	/**
	 * Adds the column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$columns['mw24_eitaa'] = __( 'Eitaa', 'mw24-eitaa' );
		return $columns;
	}

	/**
	 * Column content.
	 *
	 * @param string $output  Output.
	 * @param string $column  Column.
	 * @param int    $user_id User.
	 * @return string
	 */
	public function render_column( $output, $column, $user_id ) {
		if ( 'mw24_eitaa' !== $column ) {
			return $output;
		}
		$chat_id = MW24_Eitaa_Auth::get_chat_id( (int) $user_id );
		if ( '' === $chat_id ) {
			return '<span class="mw24-muted">—</span>';
		}
		$allowed = '1' === (string) get_user_meta( (int) $user_id, MW24_Eitaa_Auth::META_ALLOWS_PM, true );
		$phone   = (string) get_user_meta( (int) $user_id, MW24_Eitaa_Auth::META_PHONE, true );
		return '<span class="mw24-badge mw24-badge--ok">' . esc_html__( 'Connected', 'mw24-eitaa' ) . '</span>'
			. ( '' !== $phone ? '<br><code dir="ltr">' . esc_html( MW24_Eitaa_Phone_Normalizer::mask( $phone ) ) . '</code>' : '' )
			. ( $allowed ? '' : '<br><span class="mw24-muted">' . esc_html__( 'messages not allowed', 'mw24-eitaa' ) . '</span>' );
	}

	/**
	 * Profile box.
	 *
	 * @param WP_User $user User being edited.
	 * @return void
	 */
	public function profile_box( $user ) {
		if ( ! $user instanceof WP_User ) {
			return;
		}
		$manager = current_user_can( MW24_Eitaa_Admin_Menu::capability() );
		$chat_id = MW24_Eitaa_Auth::get_chat_id( $user->ID );
		$phone   = (string) get_user_meta( $user->ID, MW24_Eitaa_Auth::META_PHONE, true );
		$date    = static function ( $timestamp ) {
			$timestamp = (int) $timestamp;
			return $timestamp > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : '—';
		};

		echo '<h2 id="mw24-eitaa">' . esc_html__( 'Eitaa', 'mw24-eitaa' ) . '</h2>';
		echo '<table class="form-table mw24-profile" role="presentation"><tbody>';
		echo '<tr><th>' . esc_html__( 'Status', 'mw24-eitaa' ) . '</th><td>';
		echo '' !== $chat_id
			? '<span class="mw24-badge mw24-badge--ok">' . esc_html__( 'Connected', 'mw24-eitaa' ) . '</span>'
			: '<span class="mw24-badge mw24-badge--muted">' . esc_html__( 'Not connected', 'mw24-eitaa' ) . '</span> <span class="description">' . esc_html__( 'Open the site from the Eitaa mini-app while logged in to connect.', 'mw24-eitaa' ) . '</span>';
		echo '</td></tr>';
		if ( '' !== $chat_id ) {
			echo '<tr><th>' . esc_html__( 'Eitaa ID', 'mw24-eitaa' ) . '</th><td><code dir="ltr">' . esc_html( $chat_id ) . '</code></td></tr>';
			echo '<tr><th>' . esc_html__( 'Name in Eitaa', 'mw24-eitaa' ) . '</th><td>' . esc_html( (string) get_user_meta( $user->ID, MW24_Eitaa_Auth::META_FIRST_NAME, true ) ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Messages allowed', 'mw24-eitaa' ) . '</th><td>' . esc_html( '1' === (string) get_user_meta( $user->ID, MW24_Eitaa_Auth::META_ALLOWS_PM, true ) ? __( 'Yes', 'mw24-eitaa' ) : __( 'No', 'mw24-eitaa' ) ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Connected on', 'mw24-eitaa' ) . '</th><td>' . esc_html( $date( get_user_meta( $user->ID, MW24_Eitaa_Auth::META_LINKED_AT, true ) ) ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Last seen in mini-app', 'mw24-eitaa' ) . '</th><td>' . esc_html( $date( get_user_meta( $user->ID, MW24_Eitaa_Auth::META_LAST_SEEN, true ) ) ) . '</td></tr>';
		}
		if ( '' !== $phone ) {
			echo '<tr><th>' . esc_html__( 'Verified mobile', 'mw24-eitaa' ) . '</th><td><code dir="ltr">' . esc_html( $phone ) . '</code> <span class="description">' . esc_html( $date( get_user_meta( $user->ID, MW24_Eitaa_Auth::META_PHONE_VERIFIED, true ) ) ) . '</span></td></tr>';
		}
		if ( '' !== $chat_id && ( $manager || get_current_user_id() === $user->ID ) ) {
			wp_nonce_field( 'mw24_eitaa_profile_' . $user->ID, 'mw24_eitaa_profile_nonce' );
			echo '<tr><th>' . esc_html__( 'Disconnect', 'mw24-eitaa' ) . '</th><td><label><input type="checkbox" name="mw24_eitaa_unlink" value="1"> ' . esc_html__( 'Disconnect this Eitaa account when saving', 'mw24-eitaa' ) . '</label></td></tr>';
		}
		if ( '' !== $chat_id && $manager ) {
			echo '<tr><th>' . esc_html__( 'Send a message', 'mw24-eitaa' ) . '</th><td><div class="mw24-inline-tool" data-mw24-profile-send data-user="' . esc_attr( (string) $user->ID ) . '">';
			echo '<textarea class="large-text" rows="3" data-mw24-text></textarea>';
			echo '<p><button type="button" class="button" data-mw24-run>' . esc_html__( 'Send now', 'mw24-eitaa' ) . '</button> <span class="mw24-result" data-mw24-result></span></p></div>';
			$last = $this->core->logger->query(
				array(
					'user_id'  => $user->ID,
					'event'    => 'msg.sent',
					'per_page' => 1,
				)
			);
			if ( ! empty( $last['items'] ) ) {
				echo '<p class="description">' . esc_html__( 'Last message:', 'mw24-eitaa' ) . ' ' . esc_html( wp_date( 'Y-m-d H:i', strtotime( $last['items'][0]['created_at'] . ' UTC' ) ) ) . ' — ' . esc_html( $last['items'][0]['message'] ) . '</p>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * Handles the disconnect checkbox.
	 *
	 * @param int $user_id User.
	 * @return void
	 */
	public function save_profile( $user_id ) {
		if ( empty( $_POST['mw24_eitaa_unlink'] ) || ! isset( $_POST['mw24_eitaa_profile_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['mw24_eitaa_profile_nonce'] ) ), 'mw24_eitaa_profile_' . (int) $user_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_user', (int) $user_id ) ) {
			return;
		}
		$this->core->auth->unlink( (int) $user_id, get_current_user_id() === (int) $user_id ? 'self' : 'admin' );
	}
}
