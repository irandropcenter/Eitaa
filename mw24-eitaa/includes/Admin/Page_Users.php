<?php
/**
 * Connected users screen.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Users page.
 */
class MW24_Eitaa_Admin_Page_Users extends MW24_Eitaa_Admin_Page {

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Users connected to Eitaa', 'mw24-eitaa' );
	}

	/**
	 * Body.
	 *
	 * @return void
	 */
	protected function content() {
		$table = new MW24_Eitaa_Admin_Users_Table();
		$table->prepare_items();

		echo '<div class="mw24-card mw24-send-panel" data-mw24-send-panel hidden>';
		echo '<h2 class="mw24-card__title">' . esc_html__( 'Send an Eitaa message', 'mw24-eitaa' ) . ' — <span data-mw24-send-name></span></h2>';
		echo '<textarea class="large-text" rows="4" data-mw24-send-text placeholder="' . esc_attr__( 'Message text…', 'mw24-eitaa' ) . '"></textarea>';
		echo '<p><button type="button" class="button button-primary" data-mw24-send-submit>' . esc_html__( 'Send now', 'mw24-eitaa' ) . '</button> <button type="button" class="button" data-mw24-send-cancel>' . esc_html__( 'Cancel', 'mw24-eitaa' ) . '</button> <span class="mw24-result" data-mw24-send-result></span></p>';
		echo '</div>';

		echo '<form method="get">';
		echo '<input type="hidden" name="page" value="' . esc_attr( $this->slug ) . '">';
		$table->search_box( __( 'Search name, mobile or Eitaa ID', 'mw24-eitaa' ), 'mw24-users' );
		$table->display();
		echo '</form>';
	}
}
