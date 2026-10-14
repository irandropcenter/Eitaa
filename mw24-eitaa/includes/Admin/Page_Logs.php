<?php
/**
 * Log screen.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Log page.
 */
class MW24_Eitaa_Admin_Page_Logs extends MW24_Eitaa_Admin_Page {

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Event log', 'mw24-eitaa' );
	}

	/**
	 * Body.
	 *
	 * @return void
	 */
	protected function content() {
		echo '<p class="description">' . esc_html__( 'Logins, registrations, messages and errors. Phone numbers are masked and the app token is never logged.', 'mw24-eitaa' ) . ' ';
		$days = (int) $this->settings->get( 'log_retention_days' );
		echo esc_html(
			$days > 0
				/* translators: %d: number of days. */
				? sprintf( __( 'Entries older than %d days are deleted automatically.', 'mw24-eitaa' ), $days )
				: __( 'Entries are kept until you clear the log.', 'mw24-eitaa' )
		);
		echo '</p>';

		$table = new MW24_Eitaa_Admin_Log_Table( $this->core->logger );
		$table->prepare_items();
		echo '<form method="get"><input type="hidden" name="page" value="' . esc_attr( $this->slug ) . '">';
		$table->search_box( __( 'Search log', 'mw24-eitaa' ), 'mw24-log' );
		$table->display();
		echo '</form>';
		$this->action_button( 'clear_logs', __( 'Clear the log', 'mw24-eitaa' ), array(), 'button button-link-delete', __( 'Delete all log entries?', 'mw24-eitaa' ) );
	}
}
