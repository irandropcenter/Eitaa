<?php
/**
 * Log list table.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Log table.
 */
class MW24_Eitaa_Admin_Log_Table extends WP_List_Table {

	/**
	 * Logger.
	 *
	 * @var MW24_Eitaa_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Logger $logger Logger.
	 */
	public function __construct( $logger ) {
		$this->logger = $logger;
		parent::__construct(
			array(
				'singular' => 'mw24-log',
				'plural'   => 'mw24-logs',
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
			'created_at' => __( 'Time', 'mw24-eitaa' ),
			'level'      => __( 'Level', 'mw24-eitaa' ),
			'event'      => __( 'Event', 'mw24-eitaa' ),
			'message'    => __( 'Message', 'mw24-eitaa' ),
			'user_id'    => __( 'User', 'mw24-eitaa' ),
			'eitaa_id'   => __( 'Eitaa ID', 'mw24-eitaa' ),
			'ip'         => __( 'IP', 'mw24-eitaa' ),
		);
	}

	/**
	 * Current filter value.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private function filter( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filters.
		return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : '';
	}

	/**
	 * Loads items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$per_page              = 50;
		$result                = $this->logger->query(
			array(
				'level'    => $this->filter( 'level' ),
				'event'    => $this->filter( 'event' ),
				'search'   => $this->filter( 's' ),
				'per_page' => $per_page,
				'page'     => $this->get_pagenum(),
			)
		);
		$this->items           = $result['items'];
		$this->_column_headers = array( $this->get_columns(), array(), array() );
		$this->set_pagination_args(
			array(
				'total_items' => $result['total'],
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Filters above the table.
	 *
	 * @param string $which top|bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$level = $this->filter( 'level' );
		$event = $this->filter( 'event' );
		echo '<div class="alignleft actions">';
		echo '<select name="level"><option value="">' . esc_html__( 'All levels', 'mw24-eitaa' ) . '</option>';
		foreach ( MW24_Eitaa_Logger::LEVELS as $item ) {
			echo '<option value="' . esc_attr( $item ) . '" ' . selected( $level, $item, false ) . '>' . esc_html( $item ) . '</option>';
		}
		echo '</select><select name="event"><option value="">' . esc_html__( 'All events', 'mw24-eitaa' ) . '</option>';
		foreach ( $this->logger->events() as $item ) {
			echo '<option value="' . esc_attr( $item ) . '" ' . selected( $event, $item, false ) . '>' . esc_html( $item ) . '</option>';
		}
		echo '</select>';
		submit_button( __( 'Filter', 'mw24-eitaa' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'The log is empty.', 'mw24-eitaa' );
	}

	/**
	 * Column output.
	 *
	 * @param array  $item   Row.
	 * @param string $column Column.
	 * @return string
	 */
	public function column_default( $item, $column ) {
		switch ( $column ) {
			case 'created_at':
				return esc_html( wp_date( 'Y-m-d H:i:s', strtotime( $item['created_at'] . ' UTC' ) ) );
			case 'level':
				$type = 'error' === $item['level'] ? 'error' : ( 'warning' === $item['level'] ? 'warn' : ( 'debug' === $item['level'] ? 'muted' : 'info' ) );
				return '<span class="mw24-badge mw24-badge--' . esc_attr( $type ) . '">' . esc_html( $item['level'] ) . '</span>';
			case 'event':
				return '<code>' . esc_html( $item['event'] ) . '</code>';
			case 'message':
				$out = esc_html( $item['message'] );
				if ( ! empty( $item['context'] ) ) {
					$out .= '<details><summary>' . esc_html__( 'Details', 'mw24-eitaa' ) . '</summary><pre dir="ltr">' . esc_html( (string) wp_json_encode( json_decode( $item['context'], true ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ) . '</pre></details>';
				}
				return $out;
			case 'user_id':
				$user_id = (int) $item['user_id'];
				if ( $user_id <= 0 ) {
					return '—';
				}
				$user = get_userdata( $user_id );
				return $user ? '<a href="' . esc_url( get_edit_user_link( $user_id ) ) . '">' . esc_html( $user->user_login ) . '</a>' : '#' . esc_html( (string) $user_id );
			case 'eitaa_id':
			case 'ip':
				return '' !== (string) $item[ $column ] ? '<code dir="ltr">' . esc_html( $item[ $column ] ) . '</code>' : '—';
		}
		return '';
	}
}
