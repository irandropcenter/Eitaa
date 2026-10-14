<?php
/**
 * List table of users connected to Eitaa.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Connected users table.
 */
class MW24_Eitaa_Admin_Users_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'mw24-user',
				'plural'   => 'mw24-users',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'user'    => __( 'User', 'mw24-eitaa' ),
			'phone'   => __( 'Mobile', 'mw24-eitaa' ),
			'eitaa'   => __( 'Eitaa ID', 'mw24-eitaa' ),
			'pm'      => __( 'Messages allowed', 'mw24-eitaa' ),
			'linked'  => __( 'Connected on', 'mw24-eitaa' ),
			'seen'    => __( 'Last seen in mini-app', 'mw24-eitaa' ),
			'actions' => __( 'Actions', 'mw24-eitaa' ),
		);
	}

	/**
	 * Loads the items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page = 20;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search.
		$search = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
		$exists = array(
			'key'     => MW24_Eitaa_Auth::META_EITAA_ID,
			'compare' => 'EXISTS',
		);
		$args   = array(
			'number'      => $per_page,
			'paged'       => $this->get_pagenum(),
			'orderby'     => 'ID',
			'order'       => 'DESC',
			'count_total' => true,
			'meta_query'  => array( $exists ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		);
		if ( '' !== $search ) {
			$normalized = MW24_Eitaa_Phone_Normalizer::normalize( $search );
			$digits     = preg_replace( '/\D/', '', MW24_Eitaa_Phone_Normalizer::to_english_digits( $search ) );
			if ( '' !== $normalized ) {
				$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					$exists,
					array(
						'key'   => MW24_Eitaa_Auth::META_PHONE,
						'value' => $normalized,
					),
				);
			} elseif ( strlen( $digits ) >= 5 && $digits === $search ) {
				$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'   => MW24_Eitaa_Auth::META_EITAA_ID,
						'value' => $digits,
					),
				);
			} else {
				$args['search']         = '*' . $search . '*';
				$args['search_columns'] = array( 'user_login', 'user_email', 'user_nicename', 'display_name' );
			}
		}
		$query                 = new WP_User_Query( $args );
		$this->items           = $query->get_results();
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args(
			array(
				'total_items' => (int) $query->get_total(),
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No user has connected an Eitaa account yet.', 'mw24-eitaa' );
	}

	/**
	 * Column output.
	 *
	 * @param WP_User $item   User.
	 * @param string  $column Column.
	 * @return string
	 */
	public function column_default( $item, $column ) {
		switch ( $column ) {
			case 'user':
				return get_avatar( $item->ID, 32 ) . ' <strong><a href="' . esc_url( get_edit_user_link( $item->ID ) ) . '">' . esc_html( $item->display_name ) . '</a></strong><br><span class="mw24-muted" dir="ltr">' . esc_html( $item->user_login ) . '</span>';
			case 'phone':
				$phone = (string) get_user_meta( $item->ID, MW24_Eitaa_Auth::META_PHONE, true );
				return '' !== $phone ? '<code dir="ltr">' . esc_html( $phone ) . '</code>' : '—';
			case 'eitaa':
				return '<code dir="ltr">' . esc_html( MW24_Eitaa_Auth::get_chat_id( $item->ID ) ) . '</code>';
			case 'pm':
				$allowed = (string) get_user_meta( $item->ID, MW24_Eitaa_Auth::META_ALLOWS_PM, true );
				return '1' === $allowed ? '<span class="dashicons dashicons-yes" aria-label="' . esc_attr__( 'Yes', 'mw24-eitaa' ) . '"></span>' : '<span class="dashicons dashicons-minus" aria-label="' . esc_attr__( 'No', 'mw24-eitaa' ) . '"></span>';
			case 'linked':
				return esc_html( $this->date( (int) get_user_meta( $item->ID, MW24_Eitaa_Auth::META_LINKED_AT, true ) ) );
			case 'seen':
				return esc_html( $this->date( (int) get_user_meta( $item->ID, MW24_Eitaa_Auth::META_LAST_SEEN, true ) ) );
			case 'actions':
				$out  = '<button type="button" class="button button-small" data-mw24-send-user="' . esc_attr( (string) $item->ID ) . '" data-mw24-name="' . esc_attr( $item->display_name ) . '">' . esc_html__( 'Send message', 'mw24-eitaa' ) . '</button> ';
				$out .= '<a class="button button-small button-link-delete" data-mw24-confirm="' . esc_attr__( 'Disconnect this Eitaa account?', 'mw24-eitaa' ) . '" href="' . esc_url( MW24_Eitaa_Admin_Menu::action_url( 'unlink_user', array( 'user_id' => $item->ID ) ) ) . '">' . esc_html__( 'Disconnect', 'mw24-eitaa' ) . '</a>';
				return $out;
		}
		return '';
	}

	/**
	 * Date helper.
	 *
	 * @param int $timestamp Timestamp.
	 * @return string
	 */
	private function date( $timestamp ) {
		return $timestamp > 0 ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp ) : '—';
	}
}
