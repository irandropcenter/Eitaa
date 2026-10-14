<?php
/**
 * Tools: token test, dry-run, past sync, queue, maintenance and system status.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Tools page.
 */
class MW24_Eitaa_Admin_Page_Tools extends MW24_Eitaa_Admin_Page {

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Tools', 'mw24-eitaa' );
	}

	/**
	 * Sections.
	 *
	 * @return string[]
	 */
	protected function sections() {
		return array( 'tools' );
	}

	/**
	 * Body.
	 *
	 * @return void
	 */
	protected function content() {
		$core      = $this->core;
		$own_chat  = MW24_Eitaa_Auth::get_chat_id( get_current_user_id() );
		$tested_at = (int) $this->settings->get_state( 'token_tested_at', 0 );

		echo '<div class="mw24-grid mw24-grid--2">';

		$this->card_open( __( 'Test the token', 'mw24-eitaa' ), __( 'Eitaa has no “check token” API, so the token is tested by sending a real test message.', 'mw24-eitaa' ) );
		if ( '' === $own_chat ) {
			echo '<p class="mw24-muted">' . esc_html__( 'Your admin account is not linked yet: open the mini-app in Eitaa while logged in to this site (this links your account), or enter an Eitaa user ID below.', 'mw24-eitaa' ) . '</p>';
		}
		echo '<div class="mw24-inline-tool" data-mw24-test-message>';
		printf( '<p><label>%1$s<br><input type="text" class="regular-text code" dir="ltr" data-mw24-chat value="%2$s" placeholder="279058397"></label></p>', esc_html__( 'Recipient Eitaa ID', 'mw24-eitaa' ), esc_attr( $own_chat ) );
		printf(
			'<p><label>%1$s<br><textarea class="large-text" rows="2" data-mw24-text>%2$s</textarea></label></p>',
			esc_html__( 'Text', 'mw24-eitaa' ),
			esc_textarea(
				sprintf(
					/* translators: %s: site name. */
					__( 'Test message from %s — the Eitaa connection works.', 'mw24-eitaa' ),
					wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES )
				)
			)
		);
		echo '<p><button type="button" class="button button-primary" data-mw24-run' . ( $this->settings->has_token() ? '' : ' disabled' ) . '>' . esc_html__( 'Send test message', 'mw24-eitaa' ) . '</button> <span class="mw24-result" data-mw24-result></span></p>';
		echo '</div>';
		if ( $tested_at ) {
			/* translators: %s: date. */
			echo '<p>' . wp_kses_post( $this->badge( sprintf( __( 'Last successful test: %s', 'mw24-eitaa' ), $this->date( $tested_at ) ), 'ok' ) ) . '</p>';
		}
		$this->card_close();

		$this->card_open( __( 'Test the phone map (dry-run)', 'mw24-eitaa' ), __( 'Shows which user a number resolves to and what would be written. Nothing is changed.', 'mw24-eitaa' ) );
		echo '<div class="mw24-inline-tool" data-mw24-dry-run><input type="text" class="regular-text code" dir="ltr" placeholder="09121234567" data-mw24-input> <button type="button" class="button" data-mw24-run>' . esc_html__( 'Test', 'mw24-eitaa' ) . '</button><div class="mw24-result" data-mw24-result></div></div>';
		$this->card_close();

		echo '</div>';

		$this->card_open( __( 'Sync existing users', 'mw24-eitaa' ), __( 'For users that already have a number in one phone field but not in the others, copy it to the missing fields. Different existing numbers are never overwritten and numbers owned by another user are skipped. Runs in the background in batches of 100 users.', 'mw24-eitaa' ) );
		echo '<div class="mw24-backfill" data-mw24-backfill data-status="' . esc_attr( (string) wp_json_encode( $core->backfill->status() ) ) . '">';
		echo '<p><button type="button" class="button" data-mw24-op="preview">' . esc_html__( 'Preview (first 200 users)', 'mw24-eitaa' ) . '</button> ';
		printf( '<button type="button" class="button button-primary" data-mw24-op="start" data-mw24-confirm="%1$s">%2$s</button> ', esc_attr__( 'Start syncing phone numbers of all users?', 'mw24-eitaa' ), esc_html__( 'Start sync', 'mw24-eitaa' ) );
		echo '<button type="button" class="button" data-mw24-op="batch">' . esc_html__( 'Run one batch now', 'mw24-eitaa' ) . '</button> ';
		echo '<button type="button" class="button button-link-delete" data-mw24-op="cancel">' . esc_html__( 'Cancel', 'mw24-eitaa' ) . '</button></p>';
		echo '<div class="mw24-progress"><span data-mw24-bar style="width:0"></span></div><p data-mw24-state class="mw24-muted"></p><div class="mw24-result" data-mw24-result></div>';
		echo '</div>';
		$this->card_close();

		$stats = $core->queue->stats();
		$this->card_open( __( 'Message queue', 'mw24-eitaa' ) );
		printf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: pending, 2: sent, 3: failed. */
					__( 'Waiting: %1$s — Sent: %2$s — Failed: %3$s', 'mw24-eitaa' ),
					number_format_i18n( $stats['pending'] + $stats['sending'] ),
					number_format_i18n( $stats['sent'] ),
					number_format_i18n( $stats['failed'] )
				)
			)
		);
		echo '<div class="mw24-button-row">';
		$this->action_button( 'queue_process', __( 'Process queue now', 'mw24-eitaa' ) );
		$this->action_button( 'queue_retry', __( 'Retry failed messages', 'mw24-eitaa' ) );
		$this->action_button( 'queue_clear_done', __( 'Remove sent/failed', 'mw24-eitaa' ) );
		$this->action_button( 'queue_clear_all', __( 'Empty queue', 'mw24-eitaa' ), array(), 'button button-link-delete', __( 'Delete all queued messages, including those not sent yet?', 'mw24-eitaa' ) );
		echo '</div>';
		$this->card_close();

		$this->card_open( __( 'Maintenance', 'mw24-eitaa' ) );
		$this->form_open( 'tools' );
		$this->table_open();
		$this->field( 'log_retention_days', __( 'Keep log entries for', 'mw24-eitaa' ), __( '0 = keep forever.', 'mw24-eitaa' ), array( 'suffix' => __( 'days', 'mw24-eitaa' ) ) );
		$this->field( 'debug', __( 'Debug mode', 'mw24-eitaa' ), __( 'Logs extra details and prints diagnostics in the browser console of the mini-app. Turn off on production sites.', 'mw24-eitaa' ), array( 'label' => __( 'Enable debug mode', 'mw24-eitaa' ) ) );
		$this->field( 'delete_data_on_uninstall', __( 'Uninstall', 'mw24-eitaa' ), __( 'When the plugin is deleted, also remove its tables, settings, token and the Eitaa data of users. Phone numbers written to other plugins\' fields are kept.', 'mw24-eitaa' ), array( 'label' => __( 'Delete all plugin data on uninstall', 'mw24-eitaa' ) ) );
		$this->table_close();
		$this->form_close();
		echo '<div class="mw24-button-row">';
		$this->action_button( 'clear_cache', __( 'Clear cached data', 'mw24-eitaa' ) );
		$this->action_button( 'reset_settings', __( 'Reset settings to defaults', 'mw24-eitaa' ), array(), 'button button-link-delete', __( 'Reset all settings? The token and phone maps are kept.', 'mw24-eitaa' ) );
		echo '</div>';
		$this->card_close();

		if ( $core->license ) {
			$core->license->render_box();
		}

		$this->system_status();
	}

	/**
	 * System status table.
	 *
	 * @return void
	 */
	private function system_status() {
		global $wp_version;
		$core   = $this->core;
		$cron   = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		$caches = MW24_Eitaa_Compat_Cache::detected();
		$rows   = array(
			__( 'Plugin version', 'mw24-eitaa' )         => MW24_EITAA_VERSION . ' (DB ' . get_option( MW24_Eitaa_Installer::DB_OPTION, '—' ) . ')',
			__( 'WordPress', 'mw24-eitaa' )              => $wp_version . ( is_multisite() ? ' — Multisite' : '' ),
			__( 'PHP', 'mw24-eitaa' )                    => PHP_VERSION,
			__( 'WooCommerce', 'mw24-eitaa' )            => $core->woo->is_active() && defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Not active', 'mw24-eitaa' ),
			__( 'HTTPS', 'mw24-eitaa' )                  => $this->settings->is_https() ? __( 'Yes', 'mw24-eitaa' ) : __( 'No', 'mw24-eitaa' ),
			__( 'Token encryption', 'mw24-eitaa' )       => $this->settings->token_from_constant() ? __( 'Token defined in wp-config.php', 'mw24-eitaa' ) : ( MW24_Eitaa_Crypto::available() ? 'AES-256-GCM' : __( 'OpenSSL missing — stored without encryption', 'mw24-eitaa' ) ),
			__( 'Background jobs', 'mw24-eitaa' )        => function_exists( 'as_schedule_single_action' ) ? 'Action Scheduler' : ( $cron ? __( 'WP-Cron disabled — make sure a server cron calls wp-cron.php', 'mw24-eitaa' ) : 'WP-Cron' ),
			__( 'Detected login plugins', 'mw24-eitaa' ) => implode( ', ', array_map( array( $this, 'profile_name' ), MW24_Eitaa_Mapping_Profiles::detected() ) ),
			__( 'Cache plugins', 'mw24-eitaa' )          => $caches ? implode( ', ', $caches ) . ' — ' . __( 'exclude the cookie mw24_eitaa_app from page cache', 'mw24-eitaa' ) : '—',
			__( 'REST endpoint', 'mw24-eitaa' )          => rest_url( MW24_Eitaa_Rest::NS . '/' ),
		);
		$this->card_open( __( 'System status', 'mw24-eitaa' ) );
		echo '<table class="widefat striped mw24-status"><tbody>';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th><td dir="auto">' . esc_html( '' !== (string) $value ? (string) $value : '—' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		$this->card_close();
	}

	/**
	 * Profile name by id.
	 *
	 * @param string $id Id.
	 * @return string
	 */
	public function profile_name( $id ) {
		$profile = MW24_Eitaa_Mapping_Profiles::get( $id );
		return $profile ? $profile['name'] : $id;
	}
}
