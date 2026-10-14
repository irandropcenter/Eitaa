<?php
/**
 * Connection settings: switch, token, entry URL, security options.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Connection page.
 */
class MW24_Eitaa_Admin_Page_Connection extends MW24_Eitaa_Admin_Page {

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Connection settings', 'mw24-eitaa' );
	}

	/**
	 * Sections.
	 *
	 * @return string[]
	 */
	protected function sections() {
		return array( 'connection' );
	}

	/**
	 * Saves settings and the token. HTTPS is required to enable the connection.
	 *
	 * @param string $section Section.
	 * @param array  $input   Input.
	 * @return true|WP_Error
	 */
	public function save( $section, array $input ) {
		if ( 'connection' !== $section ) {
			return new WP_Error( 'mw24_eitaa_bad_section', __( 'Invalid form.', 'mw24-eitaa' ) );
		}
		$values = $this->settings->sanitize_section( 'connection', $input );
		$error  = null;

		$token = isset( $input['app_token'] ) && is_scalar( $input['app_token'] ) ? trim( (string) $input['app_token'] ) : '';
		if ( ! empty( $input['delete_token'] ) ) {
			$this->settings->delete_token();
			$this->core->logger->warning( 'token.deleted', 'App token deleted.', array( 'user_id' => get_current_user_id() ) );
		} elseif ( '' !== $token && ! $this->settings->token_from_constant() ) {
			$this->settings->save_token( $token );
			$this->core->logger->info( 'token.updated', 'App token updated.', array( 'user_id' => get_current_user_id() ) );
			if ( ! MW24_Eitaa_Settings::token_looks_valid( $token ) ) {
				MW24_Eitaa_Admin_Menu::add_notice( 'warning', __( 'The token was saved, but its format looks unusual. Please double-check it in Eitaayar.', 'mw24-eitaa' ) );
			}
		}

		if ( ! empty( $values['enabled'] ) ) {
			if ( ! $this->settings->is_https() ) {
				$values['enabled'] = false;
				$error             = new WP_Error( 'mw24_eitaa_https', __( 'The connection was not enabled: your site address must start with https:// because Eitaa only opens secure addresses.', 'mw24-eitaa' ) );
			} elseif ( ! $this->settings->has_token() ) {
				$values['enabled'] = false;
				$error             = new WP_Error( 'mw24_eitaa_token', __( 'The connection was not enabled: please enter the app token first.', 'mw24-eitaa' ) );
			}
		}
		$this->settings->update( $values );
		$this->core->logger->info( 'settings.saved', 'Settings saved: connection', array( 'user_id' => get_current_user_id() ) );
		return $error ? $error : true;
	}

	/**
	 * Body.
	 *
	 * @return void
	 */
	protected function content() {
		$s        = $this->settings;
		$constant = $s->token_from_constant();

		$this->form_open( 'connection' );

		$this->card_open( __( 'Connection', 'mw24-eitaa' ) );
		if ( ! $s->is_https() ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Your site address does not use HTTPS. Eitaa only opens secure (https://) addresses, so the connection cannot be enabled yet.', 'mw24-eitaa' ) . '</p></div>';
		}
		$this->table_open();
		$this->field( 'enabled', __( 'Enable connection', 'mw24-eitaa' ), __( 'When disabled, the plugin adds nothing to your site and the site behaves exactly as before.', 'mw24-eitaa' ), array( 'label' => __( 'Open my site as an Eitaa mini-app', 'mw24-eitaa' ) ) );

		echo '<tr><th scope="row"><label for="mw24-app-token">' . esc_html__( 'App token', 'mw24-eitaa' ) . '</label></th><td>';
		if ( $constant ) {
			echo '<p>' . wp_kses_post( $this->badge( __( 'Defined in wp-config.php', 'mw24-eitaa' ), 'ok' ) ) . '</p>';
			echo '<p class="description">' . esc_html__( 'The token comes from the MW24_EITAA_APP_TOKEN constant and cannot be changed here.', 'mw24-eitaa' ) . '</p>';
		} else {
			$stored = $s->token_stored();
			printf(
				'<input type="password" class="regular-text code" dir="ltr" id="mw24-app-token" name="mw24[app_token]" value="" autocomplete="new-password" spellcheck="false" placeholder="%s">',
				esc_attr( $stored ? __( 'A token is saved — enter a new one to replace it', 'mw24-eitaa' ) : '1234567890:AAG...' )
			);
			echo ' ' . wp_kses_post( $stored && $s->has_token() ? $this->badge( __( 'Saved', 'mw24-eitaa' ), 'ok' ) : ( $stored ? $this->badge( __( 'Cannot be decrypted', 'mw24-eitaa' ), 'error' ) : $this->badge( __( 'Not set', 'mw24-eitaa' ), 'warn' ) ) );
			echo '<p class="description">' . esc_html__( 'Get the token from eitaayar.ir › Apps › your app. It is stored encrypted, is never shown again and is only sent to Eitaayar.', 'mw24-eitaa' ) . '</p>';
			if ( $stored ) {
				echo '<p><label><input type="checkbox" name="mw24[delete_token]" value="1" data-mw24-confirm-check="' . esc_attr__( 'Delete the saved token? The connection will stop working.', 'mw24-eitaa' ) . '"> ' . esc_html__( 'Delete the saved token', 'mw24-eitaa' ) . '</label></p>';
			}
			echo '<p class="description">' . wp_kses( __( 'Tip: for extra security define the token in wp-config.php: <code>define( \'MW24_EITAA_APP_TOKEN\', \'...\' );</code>', 'mw24-eitaa' ), MW24_Eitaa_Admin_Fields::allowed_html() ) . '</p>';
		}
		echo '</td></tr>';
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'Mini-app address', 'mw24-eitaa' ), __( 'Eitaa opens this URL when users start your app. It must be HTTPS and must not redirect.', 'mw24-eitaa' ) );
		$this->table_open();
		echo '<tr><th scope="row">' . esc_html__( 'Entry URL', 'mw24-eitaa' ) . '</th><td>';
		$this->copy_box( $s->entry_url() );
		echo '</td></tr>';
		$this->field( 'entry_path', __( 'Entry page path', 'mw24-eitaa' ), __( 'Relative path of the page that opens first, e.g. <code>/</code> or <code>/shop/</code>. The parameter <code>?mw24_eitaa=1</code> is added automatically.', 'mw24-eitaa' ) );
		$this->field( 'url_registered', __( 'Registered in Eitaayar', 'mw24-eitaa' ), '', array( 'label' => __( 'I have saved the entry URL as the destination address of my app in Eitaayar', 'mw24-eitaa' ) ) );
		$this->field( 'detect_heuristic', __( 'Automatic detection', 'mw24-eitaa' ), __( 'Also treat visitors as mini-app users when their User-Agent or Referer contains one of the patterns below. <strong>Not reliable</strong> — Eitaa does not guarantee these values; the entry URL is the dependable method.', 'mw24-eitaa' ), array( 'label' => __( 'Use User-Agent / Referer heuristics', 'mw24-eitaa' ) ) );
		$this->field(
			'heuristic_patterns',
			__( 'Heuristic patterns', 'mw24-eitaa' ),
			__( 'Comma separated, case-insensitive.', 'mw24-eitaa' ),
			array(
				'show_if' => 'detect_heuristic=1',
				'ltr'     => true,
			)
		);
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'Security', 'mw24-eitaa' ) );
		$this->table_open();
		$this->field( 'max_age_hours', __( 'Maximum age of Eitaa data', 'mw24-eitaa' ), __( 'Signed initData older than this is rejected (auth_date check).', 'mw24-eitaa' ), array( 'suffix' => __( 'hours', 'mw24-eitaa' ) ) );
		$this->field( 'remote_verify', __( 'Second check with Eitaa server', 'mw24-eitaa' ), __( 'After the local HMAC check, also confirm the hash with eitaayar.ir/api/app/verify. Each hash can be verified once within one minute; successful results are cached. Network failures do not block logins.', 'mw24-eitaa' ), array( 'label' => __( 'Verify hashes with Eitaayar', 'mw24-eitaa' ) ) );
		$this->field(
			'ip_header',
			__( 'Client IP source', 'mw24-eitaa' ),
			__( 'Only change this when your site is behind a CDN or reverse proxy that sets the header (e.g. Cloudflare, ArvanCloud). Wrong values allow IP spoofing.', 'mw24-eitaa' ),
			array(
				'options' => array(
					'REMOTE_ADDR'           => 'REMOTE_ADDR (' . __( 'default', 'mw24-eitaa' ) . ')',
					'HTTP_X_FORWARDED_FOR'  => 'X-Forwarded-For',
					'HTTP_X_REAL_IP'        => 'X-Real-IP',
					'HTTP_CF_CONNECTING_IP' => 'CF-Connecting-IP (Cloudflare)',
					'HTTP_AR_REAL_IP'       => 'AR-Real-IP (ArvanCloud)',
				),
			)
		);
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'Embedding (iframe)', 'mw24-eitaa' ), __( 'Only needed if Eitaa shows your site inside a frame (e.g. a web client). Android and desktop apps use a normal WebView.', 'mw24-eitaa' ) );
		$this->table_open();
		$this->field( 'frame_headers', __( 'Allow framing by Eitaa', 'mw24-eitaa' ), __( 'In mini-app mode remove X-Frame-Options and send a Content-Security-Policy frame-ancestors header for the domains below. Headers added by your web server cannot be removed by WordPress.', 'mw24-eitaa' ), array( 'label' => __( 'Adjust frame headers in mini-app mode', 'mw24-eitaa' ) ) );
		$this->field(
			'frame_ancestors',
			__( 'Allowed frame ancestors', 'mw24-eitaa' ),
			__( 'Space separated sources.', 'mw24-eitaa' ),
			array(
				'show_if' => 'frame_headers=1',
				'ltr'     => true,
			)
		);
		$this->field( 'samesite_none', __( 'Cross-site cookies', 'mw24-eitaa' ), __( 'Send login and cart cookies with SameSite=None; Secure in mini-app mode so they work inside a third-party frame.', 'mw24-eitaa' ), array( 'label' => __( 'Use SameSite=None cookies in mini-app mode', 'mw24-eitaa' ) ) );
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'Advanced', 'mw24-eitaa' ) );
		$this->table_open();
		$this->field( 'sdk_strict_first', __( 'Force SDK as first script', 'mw24-eitaa' ), __( 'The official SDK (developer.eitaa.com/eitaa-web-app.js) is printed at the very top of wp_head. Enable this only if your theme prints scripts before wp_head(): the SDK will be injected right after the opening &lt;head&gt; tag using output buffering.', 'mw24-eitaa' ), array( 'label' => __( 'Inject the SDK right after <head>', 'mw24-eitaa' ) ) );
		$this->table_close();
		$this->card_close();

		$this->form_close();
	}
}
