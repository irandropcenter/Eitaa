<?php
/**
 * Appearance & behavior of the mini-app.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Appearance page.
 */
class MW24_Eitaa_Admin_Page_Appearance extends MW24_Eitaa_Admin_Page {

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Appearance & behavior', 'mw24-eitaa' );
	}

	/**
	 * Sections.
	 *
	 * @return string[]
	 */
	protected function sections() {
		return array( 'appearance' );
	}

	/**
	 * Body.
	 *
	 * @return void
	 */
	protected function content() {
		$woo = $this->core->woo->is_active();
		$this->form_open( 'appearance' );

		$this->card_open( __( 'Window', 'mw24-eitaa' ) );
		$this->table_open();
		$this->field( 'expand', __( 'Expand', 'mw24-eitaa' ), __( 'Open the mini-app at full height (Android).', 'mw24-eitaa' ), array( 'label' => __( 'Expand automatically', 'mw24-eitaa' ) ) );
		$this->field( 'fullscreen', __( 'Full screen', 'mw24-eitaa' ), __( 'Request full-screen mode where supported (SDK 1.3+).', 'mw24-eitaa' ), array( 'label' => __( 'Request full screen', 'mw24-eitaa' ) ) );
		$this->field( 'safe_area', __( 'Safe area padding', 'mw24-eitaa' ), __( 'In full-screen mode add padding from the --tg-safe-area-inset-* and --tg-content-safe-area-inset-* variables so content is not hidden behind system bars.', 'mw24-eitaa' ), array( 'label' => __( 'Add safe-area padding', 'mw24-eitaa' ) ) );
		$this->field( 'disable_vertical_swipes', __( 'Vertical swipes', 'mw24-eitaa' ), __( 'Useful when your pages use swipe gestures (sliders); users can still close the app from its header.', 'mw24-eitaa' ), array( 'label' => __( 'Disable closing with a vertical swipe', 'mw24-eitaa' ) ) );
		$this->field(
			'closing_confirmation',
			__( 'Confirm before closing', 'mw24-eitaa' ),
			'',
			array(
				'options' => array(
					'off'    => __( 'Never', 'mw24-eitaa' ),
					'cart'   => __( 'Only when the cart is not empty (WooCommerce)', 'mw24-eitaa' ),
					'always' => __( 'Always', 'mw24-eitaa' ),
				),
			)
		);
		$this->field( 'haptic', __( 'Haptic feedback', 'mw24-eitaa' ), __( 'Light vibration on button taps, success and errors.', 'mw24-eitaa' ), array( 'label' => __( 'Enable haptic feedback', 'mw24-eitaa' ) ) );
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'Eitaa buttons', 'mw24-eitaa' ) );
		$this->table_open();
		$this->field( 'back_button', __( 'Back button', 'mw24-eitaa' ), __( 'Shows Eitaa\'s back button on inner pages (goes back in history); hidden on the entry page.', 'mw24-eitaa' ), array( 'label' => __( 'Show back button', 'mw24-eitaa' ) ) );
		$this->field( 'main_button', __( 'Main button', 'mw24-eitaa' ), '', array( 'label' => __( 'Show the main bottom button', 'mw24-eitaa' ) ) );
		if ( $woo ) {
			$this->field(
				'main_button_woo',
				__( 'Smart shop button', 'mw24-eitaa' ),
				__( 'Shows “Cart (n)” when the cart has items, “Continue to checkout” on the cart page and hides itself on checkout. Updated live after add-to-cart.', 'mw24-eitaa' ),
				array(
					'label'   => __( 'Use WooCommerce cart behavior', 'mw24-eitaa' ),
					'show_if' => 'main_button=1',
				)
			);
		}
		$this->field( 'main_button_text', __( 'Main button text', 'mw24-eitaa' ), $woo ? __( 'Used when the smart shop button has nothing to show (e.g. empty cart). Leave empty to hide the button in that case.', 'mw24-eitaa' ) : '', array( 'show_if' => 'main_button=1' ) );
		$this->field(
			'main_button_url',
			__( 'Main button link', 'mw24-eitaa' ),
			'',
			array(
				'show_if'     => 'main_button=1',
				'placeholder' => home_url( '/shop/' ),
			)
		);
		$this->field( 'main_button_color', __( 'Main button color', 'mw24-eitaa' ), __( 'Empty = Eitaa theme color.', 'mw24-eitaa' ), array( 'show_if' => 'main_button=1' ) );
		$this->field( 'main_button_text_color', __( 'Main button text color', 'mw24-eitaa' ), __( 'Empty = Eitaa theme color.', 'mw24-eitaa' ), array( 'show_if' => 'main_button=1' ) );
		$this->field( 'secondary_button', __( 'Secondary button', 'mw24-eitaa' ), __( 'Requires Eitaa SDK 1.3+.', 'mw24-eitaa' ), array( 'label' => __( 'Show a secondary bottom button', 'mw24-eitaa' ) ) );
		$this->field( 'secondary_button_text', __( 'Secondary button text', 'mw24-eitaa' ), '', array( 'show_if' => 'secondary_button=1' ) );
		$this->field( 'secondary_button_url', __( 'Secondary button link', 'mw24-eitaa' ), '', array( 'show_if' => 'secondary_button=1' ) );
		$this->field(
			'secondary_button_position',
			__( 'Secondary button position', 'mw24-eitaa' ),
			'',
			array(
				'show_if' => 'secondary_button=1',
				'options' => array(
					'left'   => __( 'Left of the main button', 'mw24-eitaa' ),
					'right'  => __( 'Right of the main button', 'mw24-eitaa' ),
					'top'    => __( 'Above the main button', 'mw24-eitaa' ),
					'bottom' => __( 'Below the main button', 'mw24-eitaa' ),
				),
			)
		);
		$this->field( 'settings_button', __( 'Settings menu item', 'mw24-eitaa' ), '', array( 'label' => __( 'Show “Settings” in the mini-app menu', 'mw24-eitaa' ) ) );
		$this->field( 'settings_button_url', __( 'Settings item opens', 'mw24-eitaa' ), __( 'Empty = the user account page.', 'mw24-eitaa' ), array( 'show_if' => 'settings_button=1' ) );
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'Colors', 'mw24-eitaa' ), __( 'Leave empty to follow the user\'s Eitaa theme (themeParams).', 'mw24-eitaa' ) );
		$this->table_open();
		$this->field( 'header_color', __( 'Header color', 'mw24-eitaa' ) );
		$this->field( 'bg_color', __( 'Background color', 'mw24-eitaa' ) );
		$this->field( 'bottom_bar_color', __( 'Bottom bar color', 'mw24-eitaa' ) );
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'App-like layout', 'mw24-eitaa' ) );
		echo '<p>' . wp_kses( __( 'Add the CSS class <code>mw24-hide-in-eitaa</code> to any block, menu or widget to hide it inside the mini-app, or <code>mw24-show-in-eitaa</code> to show it only inside the mini-app. The body gets the classes <code>mw24-eitaa-app</code> and <code>mw24-eitaa-dark</code> / <code>mw24-eitaa-light</code>.', 'mw24-eitaa' ), MW24_Eitaa_Admin_Fields::allowed_html() ) . '</p>';
		$this->table_open();
		$this->field( 'hide_selectors', __( 'Also hide these elements', 'mw24-eitaa' ), __( 'CSS selectors separated by commas or new lines, e.g. <code>#masthead, .site-footer, .whatsapp-button</code>.', 'mw24-eitaa' ), array( 'rows' => 3 ) );
		$this->field( 'custom_css', __( 'Custom CSS in mini-app mode', 'mw24-eitaa' ), __( 'Loaded only inside the mini-app.', 'mw24-eitaa' ), array( 'rows' => 6 ) );
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'Start parameter routes', 'mw24-eitaa' ) );
		echo '<p>' . wp_kses( __( 'Links to your mini-app can carry a start parameter. Supported values: <code>p123</code> (post or product with ID 123), <code>cart</code>, <code>checkout</code>, <code>account</code>, <code>home</code> and <code>u:slug</code> (or <code>u_slug</code>) for the routes below. Routing happens only once per session.', 'mw24-eitaa' ), MW24_Eitaa_Admin_Fields::allowed_html() ) . '</p>';
		$this->table_open();
		$this->field( 'start_routes', __( 'Custom routes', 'mw24-eitaa' ), __( 'One per line: <code>slug = /relative/path/</code>, e.g. <code>offers = /shop/offers/</code>. Only relative paths of this site are accepted.', 'mw24-eitaa' ), array( 'rows' => 4 ) );
		$this->table_close();
		$this->card_close();

		$this->form_close();
	}
}
