<?php
/**
 * In-plugin step-by-step guide.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Help page.
 */
class MW24_Eitaa_Admin_Page_Help extends MW24_Eitaa_Admin_Page {

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Help & guide', 'mw24-eitaa' );
	}

	/**
	 * Prints a list of translated HTML items.
	 *
	 * @param string[] $items Items (limited HTML).
	 * @param string   $tag   ol|ul.
	 * @return void
	 */
	private function list_items( array $items, $tag = 'ol' ) {
		echo '<' . esc_attr( $tag ) . ' class="mw24-steps">';
		foreach ( $items as $item ) {
			echo '<li>' . wp_kses( $item, MW24_Eitaa_Admin_Fields::allowed_html() ) . '</li>';
		}
		echo '</' . esc_attr( $tag ) . '>';
	}

	/**
	 * Body.
	 *
	 * @return void
	 */
	protected function content() {
		$entry = $this->settings->entry_url();

		$this->card_open( __( 'Quick start (about 10 minutes)', 'mw24-eitaa' ) );
		$this->list_items(
			array(
				__( 'Make sure your site opens with <strong>https://</strong>. Eitaa does not open insecure addresses.', 'mw24-eitaa' ),
				__( 'Log in to <a href="https://eitaayar.ir" target="_blank" rel="noopener noreferrer">eitaayar.ir</a> and open <strong>Apps</strong> (برنامه). Complete the developer identity verification if you are asked to.', 'mw24-eitaa' ),
				__( 'Click <strong>Add app</strong> and fill in the icon, title, username, description and button text.', 'mw24-eitaa' ),
				/* translators: %s: entry URL. */
				sprintf( __( 'In <strong>Destination address</strong> enter your entry URL: <code>%s</code> (it must not redirect, e.g. from http to https or from www to non-www).', 'mw24-eitaa' ), esc_html( $entry ) ),
				__( 'Save the app and copy its <strong>token</strong> from Eitaayar.', 'mw24-eitaa' ),
				__( 'In WordPress go to <strong>Eitaa Connect › Connection</strong>, paste the token, tick “Registered in Eitaayar”, enable the connection and save.', 'mw24-eitaa' ),
				__( 'Open your app in Eitaa (Android or desktop) while logged in to WordPress as administrator — this links your own Eitaa account.', 'mw24-eitaa' ),
				__( 'Go to <strong>Tools › Test the token</strong> and send a test message to yourself. If it arrives, everything works.', 'mw24-eitaa' ),
				__( 'Review <strong>Login & phone sync</strong> (confirm detected plugins such as Digits) and choose your <strong>Notifications</strong>.', 'mw24-eitaa' ),
				__( 'Optional: under your app in Eitaayar add a mini-app (برنامک) with the same address and request publication in the Eitaa mini-app center.', 'mw24-eitaa' ),
			)
		);
		$this->copy_box( $entry );
		$this->card_close();

		echo '<div class="mw24-notice-box mw24-notice-box--strong">' . esc_html__( 'Important: Eitaa has no in-app payment API. Customers pay through your own site payment gateway inside the mini-app, exactly like in a browser.', 'mw24-eitaa' ) . '</div>';

		echo '<div class="mw24-grid mw24-grid--2">';
		$this->card_open( __( 'How login works', 'mw24-eitaa' ) );
		$this->list_items(
			array(
				__( 'When the mini-app opens, Eitaa gives the page a signed data string (initData). The plugin checks its HMAC-SHA256 signature with your token on the server.', 'mw24-eitaa' ),
				__( 'If that Eitaa account is already linked to a user, the user is logged in at once.', 'mw24-eitaa' ),
				__( 'Otherwise the visitor is asked to share the phone number. Eitaa signs the number too, so it is trusted without SMS codes.', 'mw24-eitaa' ),
				__( 'The number is searched in every phone field (Digits, WooCommerce, your own fields…). The matching user is logged in, or a new account is created.', 'mw24-eitaa' ),
				__( 'Administrators and editors are never logged in this way (unless you explicitly allow it).', 'mw24-eitaa' ),
			),
			'ul'
		);
		$this->card_close();

		$this->card_open( __( 'Useful tools for designers', 'mw24-eitaa' ) );
		$this->list_items(
			array(
				__( 'Shortcode <code>[mw24_eitaa_login]</code> or the “Eitaa login button” block shows a login button.', 'mw24-eitaa' ),
				__( 'CSS class <code>mw24-hide-in-eitaa</code> hides an element inside the mini-app; <code>mw24-show-in-eitaa</code> shows it only there.', 'mw24-eitaa' ),
				__( 'Body classes: <code>mw24-eitaa-app</code>, <code>mw24-eitaa-dark</code>, <code>mw24-eitaa-light</code>, <code>mw24-eitaa-fullscreen</code>.', 'mw24-eitaa' ),
				__( 'Eitaa theme colors are available as CSS variables such as <code>var(--tg-theme-bg-color)</code> and <code>var(--tg-theme-button-color)</code>.', 'mw24-eitaa' ),
				__( 'Start parameters: <code>p123</code>, <code>cart</code>, <code>checkout</code>, <code>account</code>, <code>home</code>, <code>u:slug</code>.', 'mw24-eitaa' ),
			),
			'ul'
		);
		$this->card_close();
		echo '</div>';

		echo '<div class="mw24-grid mw24-grid--2">';
		$this->card_open( __( 'Debugging inside Eitaa', 'mw24-eitaa' ) );
		echo '<h3>' . esc_html__( 'Android', 'mw24-eitaa' ) . '</h3>';
		$this->list_items(
			array(
				__( 'Enable USB debugging on the phone.', 'mw24-eitaa' ),
				__( 'In Eitaa settings scroll to the bottom, touch and hold the version number, release, then touch and hold it again to open the debug menu.', 'mw24-eitaa' ),
				__( 'Choose <strong>Enable WebView Debug</strong>.', 'mw24-eitaa' ),
				__( 'Connect the phone to a computer and open <code>chrome://inspect#devices</code> in Chrome or Edge.', 'mw24-eitaa' ),
			)
		);
		echo '<h3>' . esc_html__( 'Desktop', 'mw24-eitaa' ) . '</h3>';
		$this->list_items(
			array(
				__( 'Eitaa settings › Advanced › Experimental settings › enable <strong>Enable webview inspecting</strong>.', 'mw24-eitaa' ),
				__( 'Open the mini-app, right-click and choose Inspect.', 'mw24-eitaa' ),
			)
		);
		echo '<p class="mw24-muted">' . esc_html__( 'Turn on Debug mode in Tools to see the plugin\'s diagnostics in the console.', 'mw24-eitaa' ) . '</p>';
		$this->card_close();

		$this->card_open( __( 'Frequently asked questions', 'mw24-eitaa' ) );
		$faq = array(
			__( 'The page opens in Eitaa but I am not logged in.', 'mw24-eitaa' ) => __( 'Check that the address registered in Eitaayar is exactly the entry URL (with ?mw24_eitaa=1), that the token is correct and look at the Log page for “auth.failed” entries.', 'mw24-eitaa' ),
			__( 'My cache plugin breaks the mini-app.', 'mw24-eitaa' ) => __( 'Mini-app pages are sent as private/no-cache. If your cache still serves them, exclude the cookie mw24_eitaa_app and URLs containing mw24_eitaa in the cache settings.', 'mw24-eitaa' ),
			__( 'Messages are not delivered.', 'mw24-eitaa' ) => __( 'Eitaa only delivers to users who opened your mini-app and allowed messages. Check your message credit in Eitaayar and the Log page.', 'mw24-eitaa' ),
			__( 'Does it work on iPhone?', 'mw24-eitaa' ) => __( 'Eitaa currently runs mini-apps on Android and desktop (Windows). Your site keeps working normally for all other visitors.', 'mw24-eitaa' ),
			__( 'What happens when I disable the plugin?', 'mw24-eitaa' ) => __( 'With the connection switched off nothing is added to your pages and the site behaves exactly as before. Users keep their accounts and phone numbers.', 'mw24-eitaa' ),
		);
		foreach ( $faq as $question => $answer ) {
			echo '<details class="mw24-faq"><summary>' . esc_html( $question ) . '</summary><p>' . esc_html( $answer ) . '</p></details>';
		}
		$this->card_close();
		echo '</div>';

		$this->card_open( __( 'For developers', 'mw24-eitaa' ) );
		echo '<p>' . esc_html__( 'Main hooks and functions (see docs/developer-guide in the package for details):', 'mw24-eitaa' ) . '</p>';
		echo '<pre class="mw24-code" dir="ltr">';
		echo esc_html(
			"mw24_eitaa_send( \$user_id_or_chat_id, \$text, \$args = array() )\n" .
			"mw24_eitaa_get_chat_id( \$user_id )\n" .
			"mw24_eitaa_is_mini_app()\n" .
			"mw24_eitaa_find_user_by_phone( \$phone )\n\n" .
			"do_action( 'mw24_eitaa_user_linked', \$user_id, \$eitaa_id, \$eitaa_user )\n" .
			"do_action( 'mw24_eitaa_user_registered', \$user_id, \$phone, \$eitaa_user )\n" .
			"do_action( 'mw24_eitaa_user_logged_in', \$user_id, \$method )\n" .
			"apply_filters( 'mw24_eitaa_notification_text', \$text, \$event, \$vars, \$context )\n" .
			"apply_filters( 'mw24_eitaa_phone_profiles', \$profiles )\n" .
			"apply_filters( 'mw24_eitaa_can_login', true, \$user )"
		);
		echo '</pre>';
		$this->card_close();
	}
}
