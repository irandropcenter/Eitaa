<?php
/**
 * Notifications (event templates) and bulk sending.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notifications page.
 */
class MW24_Eitaa_Admin_Page_Notifications extends MW24_Eitaa_Admin_Page {

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Eitaa notifications', 'mw24-eitaa' );
	}

	/**
	 * Sections.
	 *
	 * @return string[]
	 */
	protected function sections() {
		return array( 'notifications' );
	}

	/**
	 * Body.
	 *
	 * @return void
	 */
	protected function content() {
		$tab = $this->current_tab( 'events', array( 'events', 'bulk' ) );
		$this->tabs(
			array(
				'events' => __( 'Event notifications', 'mw24-eitaa' ),
				'bulk'   => __( 'Bulk send', 'mw24-eitaa' ),
			),
			$tab
		);
		echo '<div class="mw24-notice-box">' . esc_html__( 'Eitaa only delivers messages to users who opened your mini-app and allowed messages. The first 50,000 messages are free; more credit can be bought in Eitaayar.', 'mw24-eitaa' ) . '</div>';
		if ( 'bulk' === $tab ) {
			$this->bulk_tab();
		} else {
			$this->events_tab();
		}
	}

	/**
	 * Placeholder help line.
	 *
	 * @param string $group Group.
	 * @return string
	 */
	private function placeholders( $group ) {
		/* translators: %s: list of placeholders. */
		return sprintf( __( 'Placeholders: %s', 'mw24-eitaa' ), '<code>' . implode( '</code> <code>', array_map( 'esc_html', MW24_Eitaa_Notifications::placeholders( $group ) ) ) . '</code>' );
	}

	/**
	 * One event block: switch + template.
	 *
	 * @param string $toggle_key   Switch key.
	 * @param string $template Template key.
	 * @param string $label    Label.
	 * @param string $desc     Description.
	 * @param string $group    Placeholder group.
	 * @return void
	 */
	private function event( $toggle_key, $template, $label, $desc, $group = 'common' ) {
		$this->field( $toggle_key, $label, $desc, array( 'label' => __( 'Send', 'mw24-eitaa' ) ) );
		$this->field(
			$template,
			__( 'Message', 'mw24-eitaa' ),
			$this->placeholders( $group ),
			array(
				'rows'    => 4,
				'show_if' => $toggle_key . '=1',
			)
		);
	}

	/**
	 * Events tab.
	 *
	 * @return void
	 */
	private function events_tab() {
		$woo = $this->core->woo->is_active();
		$this->form_open( 'notifications', 'events' );

		$this->card_open( __( 'Delivery', 'mw24-eitaa' ) );
		$this->table_open();
		$this->field(
			'send_mode',
			__( 'Sending mode', 'mw24-eitaa' ),
			__( 'The queue keeps checkout and admin screens fast; messages are sent in the background by Action Scheduler or WP-Cron.', 'mw24-eitaa' ),
			array(
				'options' => array(
					'queue' => __( 'Background queue (recommended)', 'mw24-eitaa' ),
					'sync'  => __( 'Immediately (slower pages)', 'mw24-eitaa' ),
				),
			)
		);
		$this->field( 'queue_rate', __( 'Queue speed', 'mw24-eitaa' ), '', array( 'suffix' => __( 'messages per minute', 'mw24-eitaa' ) ) );
		$this->field( 'only_allows_pm', __( 'Permission', 'mw24-eitaa' ), __( 'Skip users whose signed Eitaa data says they did not allow messages.', 'mw24-eitaa' ), array( 'label' => __( 'Only message users who allowed messages', 'mw24-eitaa' ) ) );
		$this->field( 'admin_linked_recipients', __( 'Admin recipients', 'mw24-eitaa' ), __( 'Administrators and shop managers receive admin notifications after they open the mini-app once while logged in (this links their account).', 'mw24-eitaa' ), array( 'label' => __( 'All linked administrators and shop managers', 'mw24-eitaa' ) ) );
		$this->field( 'admin_chat_ids', __( 'Extra admin chat IDs', 'mw24-eitaa' ), __( 'Eitaa user IDs, one per line.', 'mw24-eitaa' ), array( 'rows' => 2 ) );
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'Account events', 'mw24-eitaa' ) );
		$this->table_open();
		$this->event( 'notify_welcome', 'tpl_welcome', __( 'Welcome (new Eitaa registration)', 'mw24-eitaa' ), '' );
		$this->event( 'notify_admin_register', 'tpl_admin_register', __( 'New registration (to admins)', 'mw24-eitaa' ), __( 'For every new user, whatever the registration method.', 'mw24-eitaa' ) );
		$this->event( 'notify_login', 'tpl_login', __( 'New login (security alert)', 'mw24-eitaa' ), '' );
		$this->event( 'notify_password', 'tpl_password', __( 'Password changed', 'mw24-eitaa' ), '' );
		$this->table_close();
		$this->card_close();

		if ( $woo ) {
			$statuses  = $this->core->woo->order_statuses();
			$templates = (array) $this->settings->get( 'tpl_order_status_map' );

			$this->card_open( __( 'WooCommerce orders', 'mw24-eitaa' ) );
			$this->table_open();
			$this->field( 'notify_order_status', __( 'Order status changes', 'mw24-eitaa' ), __( 'Sent to the customer when the order moves to one of the selected statuses.', 'mw24-eitaa' ), array( 'label' => __( 'Send', 'mw24-eitaa' ) ) );
			$this->field(
				'order_statuses',
				__( 'Statuses', 'mw24-eitaa' ),
				'',
				array(
					'options' => $statuses,
					'show_if' => 'notify_order_status=1',
				)
			);
			$this->field(
				'tpl_order_status',
				__( 'Default message', 'mw24-eitaa' ),
				$this->placeholders( 'order' ),
				array(
					'rows'    => 4,
					'show_if' => 'notify_order_status=1',
				)
			);
			echo '<tr data-mw24-show-if="notify_order_status=1"><th scope="row">' . esc_html__( 'Per-status messages', 'mw24-eitaa' ) . '</th><td><details><summary>' . esc_html__( 'Optional: a different text for each status', 'mw24-eitaa' ) . '</summary>';
			foreach ( $statuses as $slug => $label ) {
				printf(
					'<p><label for="mw24-tpl-%1$s"><strong>%2$s</strong></label><br><textarea class="large-text" rows="3" id="mw24-tpl-%1$s" name="mw24[tpl_order_status_map][%1$s]" placeholder="%3$s">%4$s</textarea></p>',
					esc_attr( $slug ),
					esc_html( $label ),
					esc_attr__( 'Empty = default message', 'mw24-eitaa' ),
					esc_textarea( isset( $templates[ $slug ] ) ? (string) $templates[ $slug ] : '' )
				);
			}
			echo '</details></td></tr>';
			$this->event( 'notify_admin_new_order', 'tpl_admin_new_order', __( 'New order (to admins)', 'mw24-eitaa' ), '', 'order' );
			$this->event( 'notify_customer_note', 'tpl_customer_note', __( 'Note to customer', 'mw24-eitaa' ), '', 'note' );
			$this->field( 'notify_tracking', __( 'Tracking code', 'mw24-eitaa' ), __( 'Sent once when the tracking code meta of an order is filled or changed.', 'mw24-eitaa' ), array( 'label' => __( 'Send', 'mw24-eitaa' ) ) );
			$this->field( 'tracking_meta_key', __( 'Tracking code meta key', 'mw24-eitaa' ), __( 'Order meta key where your shipping plugin stores the code, e.g. <code>_tracking_code</code>.', 'mw24-eitaa' ), array( 'show_if' => 'notify_tracking=1' ) );
			$this->field(
				'tpl_tracking',
				__( 'Message', 'mw24-eitaa' ),
				$this->placeholders( 'order' ),
				array(
					'rows'    => 4,
					'show_if' => 'notify_tracking=1',
				)
			);
			$this->field( 'notify_abandoned_cart', __( 'Abandoned cart', 'mw24-eitaa' ), __( 'Reminds linked users who left items in their cart (checked hourly, once per cart).', 'mw24-eitaa' ), array( 'label' => __( 'Send', 'mw24-eitaa' ) ) );
			$this->field(
				'abandoned_hours',
				__( 'Remind after', 'mw24-eitaa' ),
				'',
				array(
					'suffix'  => __( 'hours', 'mw24-eitaa' ),
					'show_if' => 'notify_abandoned_cart=1',
				)
			);
			$this->field(
				'tpl_abandoned_cart',
				__( 'Message', 'mw24-eitaa' ),
				$this->placeholders( 'cart' ),
				array(
					'rows'    => 4,
					'show_if' => 'notify_abandoned_cart=1',
				)
			);
			$this->table_close();
			$this->card_close();
		}

		echo '<p class="description">' . wp_kses( __( 'Developers can change any text with the <code>mw24_eitaa_notification_text</code> filter.', 'mw24-eitaa' ), MW24_Eitaa_Admin_Fields::allowed_html() ) . '</p>';
		$this->form_close();
	}

	/**
	 * Bulk send tab.
	 *
	 * @return void
	 */
	private function bulk_tab() {
		$roles = array( '' => __( 'All roles', 'mw24-eitaa' ) ) + wp_list_pluck( wp_roles()->roles, 'name' );

		$this->card_open( __( 'New bulk message', 'mw24-eitaa' ), __( 'Messages are queued and sent in the background at the queue speed set in the Event notifications tab.', 'mw24-eitaa' ) );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mw24-form" data-mw24-bulk>';
		echo '<input type="hidden" name="action" value="mw24_eitaa_action"><input type="hidden" name="do" value="bulk_send">';
		wp_nonce_field( 'mw24_eitaa_action_bulk_send' );
		echo '<table class="form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row"><label for="mw24-bulk-title">' . esc_html__( 'Internal title', 'mw24-eitaa' ) . '</label></th><td><input type="text" class="regular-text" id="mw24-bulk-title" name="bulk_title"></td></tr>';
		echo '<tr><th scope="row"><label for="mw24-bulk-text">' . esc_html__( 'Message', 'mw24-eitaa' ) . '</label></th><td><textarea class="large-text" rows="6" id="mw24-bulk-text" name="bulk_text" required></textarea><p class="description">' . wp_kses( $this->placeholders( 'common' ), MW24_Eitaa_Admin_Fields::allowed_html() ) . '</p></td></tr>';
		echo '<tr><th scope="row"><label for="mw24-bulk-role">' . esc_html__( 'Role', 'mw24-eitaa' ) . '</label></th><td><select id="mw24-bulk-role" name="bulk_role" data-mw24-filter="role">';
		foreach ( $roles as $slug => $name ) {
			echo '<option value="' . esc_attr( $slug ) . '">' . esc_html( translate_user_role( $name ) ) . '</option>';
		}
		echo '</select></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Connected between', 'mw24-eitaa' ) . '</th><td><input type="date" name="bulk_since" data-mw24-filter="since"> — <input type="date" name="bulk_until" data-mw24-filter="until"></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Permission', 'mw24-eitaa' ) . '</th><td><label><input type="checkbox" name="bulk_only_pm" value="1" checked data-mw24-filter="only_allows_pm"> ' . esc_html__( 'Only users who allowed messages', 'mw24-eitaa' ) . '</label></td></tr>';
		echo '</tbody></table>';
		echo '<p><button type="button" class="button" data-mw24-bulk-preview>' . esc_html__( 'Count recipients', 'mw24-eitaa' ) . '</button> <span data-mw24-bulk-count class="mw24-muted"></span></p>';
		printf( '<p><button type="submit" class="button button-primary" data-mw24-confirm="%1$s">%2$s</button></p>', esc_attr__( 'Queue this message for all matching users?', 'mw24-eitaa' ), esc_html__( 'Queue message', 'mw24-eitaa' ) );
		echo '</form>';
		$this->card_close();

		$this->card_open( __( 'Bulk send reports', 'mw24-eitaa' ) );
		$batches = $this->core->queue->batches();
		if ( empty( $batches ) ) {
			echo '<p>' . esc_html__( 'No bulk message has been sent yet.', 'mw24-eitaa' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Title', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Created', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Total', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Sent', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Failed', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Waiting', 'mw24-eitaa' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $batches as $batch ) {
				echo '<tr><td>' . esc_html( $batch['title'] ) . '</td><td>' . esc_html( $this->date( $batch['created'] ) ) . '</td><td>' . esc_html( number_format_i18n( (int) $batch['total'] ) ) . '</td><td>' . esc_html( number_format_i18n( $batch['stats']['sent'] ) ) . '</td><td>' . esc_html( number_format_i18n( $batch['stats']['failed'] ) ) . '</td><td>' . esc_html( number_format_i18n( $batch['stats']['pending'] + $batch['stats']['sending'] ) ) . '</td><td>';
				if ( $batch['stats']['failed'] > 0 ) {
					$this->action_button( 'queue_retry', __( 'Retry failed', 'mw24-eitaa' ), array( 'batch' => $batch['id'] ), 'button button-small' );
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		$this->card_close();
	}
}
