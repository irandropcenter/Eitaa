<?php
/**
 * Dashboard page: readiness, next step and statistics.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dashboard.
 */
class MW24_Eitaa_Admin_Page_Dashboard extends MW24_Eitaa_Admin_Page {

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Eitaa Connect — Dashboard', 'mw24-eitaa' );
	}

	/**
	 * Readiness checklist.
	 *
	 * @return array{percent:int,items:array,next:array|null}
	 */
	public static function readiness() {
		$core     = mw24_eitaa();
		$settings = $core->settings;
		$notify   = false;
		foreach ( array( 'notify_welcome', 'notify_login', 'notify_password', 'notify_order_status', 'notify_admin_new_order', 'notify_customer_note', 'notify_tracking', 'notify_abandoned_cart', 'notify_admin_register' ) as $key ) {
			if ( $settings->get( $key ) ) {
				$notify = true;
				break;
			}
		}
		$items = array(
			array(
				'id'    => 'https',
				'done'  => $settings->is_https(),
				'label' => __( 'The site is served over HTTPS', 'mw24-eitaa' ),
				'help'  => __( 'Eitaa only opens HTTPS addresses. Install an SSL certificate and switch the site address to https://.', 'mw24-eitaa' ),
				'url'   => admin_url( 'options-general.php' ),
			),
			array(
				'id'    => 'token',
				'done'  => $settings->has_token(),
				'label' => __( 'App token saved', 'mw24-eitaa' ),
				'help'  => __( 'Copy the token of your app from the Eitaayar panel and paste it in the connection settings.', 'mw24-eitaa' ),
				'url'   => MW24_Eitaa_Admin_Menu::url( 'mw24-eitaa-connection' ),
			),
			array(
				'id'    => 'registered',
				'done'  => (bool) $settings->get( 'url_registered' ),
				'label' => __( 'Mini-app address registered in Eitaayar', 'mw24-eitaa' ),
				'help'  => __( 'Register the entry URL shown in the connection settings as the destination address of your app, then tick the confirmation box.', 'mw24-eitaa' ),
				'url'   => MW24_Eitaa_Admin_Menu::url( 'mw24-eitaa-connection' ),
			),
			array(
				'id'    => 'enabled',
				'done'  => (bool) $settings->get( 'enabled' ),
				'label' => __( 'Connection enabled', 'mw24-eitaa' ),
				'help'  => __( 'Turn on the connection switch in the connection settings.', 'mw24-eitaa' ),
				'url'   => MW24_Eitaa_Admin_Menu::url( 'mw24-eitaa-connection' ),
			),
			array(
				'id'    => 'tested',
				'done'  => (int) $settings->get_state( 'token_tested_at', 0 ) > 0,
				'label' => __( 'Token tested with a test message', 'mw24-eitaa' ),
				'help'  => __( 'Open the mini-app once while logged in as administrator, then send a test message from Tools.', 'mw24-eitaa' ),
				'url'   => MW24_Eitaa_Admin_Menu::url( 'mw24-eitaa-tools' ),
			),
			array(
				'id'    => 'mapping',
				'done'  => (bool) $settings->get_state( 'mapping_confirmed', false ),
				'label' => __( 'Phone sync reviewed', 'mw24-eitaa' ),
				'help'  => __( 'Review the detected login plugins and phone fields, then save the phone sync settings.', 'mw24-eitaa' ),
				'url'   => MW24_Eitaa_Admin_Menu::url( 'mw24-eitaa-login', array( 'tab' => 'sync' ) ),
			),
			array(
				'id'    => 'notifications',
				'done'  => $notify,
				'label' => __( 'At least one notification enabled', 'mw24-eitaa' ),
				'help'  => __( 'Choose which events should send an Eitaa message.', 'mw24-eitaa' ),
				'url'   => MW24_Eitaa_Admin_Menu::url( 'mw24-eitaa-notifications' ),
			),
		);
		$done  = 0;
		$next  = null;
		foreach ( $items as $item ) {
			if ( $item['done'] ) {
				++$done;
			} elseif ( null === $next ) {
				$next = $item;
			}
		}
		return array(
			'percent' => (int) round( 100 * $done / count( $items ) ),
			'items'   => $items,
			'next'    => $next,
		);
	}

	/**
	 * Number of linked users.
	 *
	 * @return int
	 */
	public static function linked_users_count() {
		$query = new WP_User_Query(
			array(
				'meta_key'     => MW24_Eitaa_Auth::META_EITAA_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_compare' => 'EXISTS',
				'number'       => 1,
				'fields'       => 'ID',
				'count_total'  => true,
			)
		);
		return (int) $query->get_total();
	}

	/**
	 * Event counts of the last 30 days from the log.
	 *
	 * @return array event => count
	 */
	private function event_counts() {
		if ( ! MW24_Eitaa_Installer::tables_ready() ) {
			return array();
		}
		global $wpdb;
		$table = MW24_Eitaa_Logger::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT event, COUNT(*) AS n FROM {$table} WHERE created_at >= %s AND event IN ('msg.sent','msg.failed','auth.register','auth.login','auth.link') GROUP BY event", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ), ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$out[ $row['event'] ] = (int) $row['n'];
		}
		return $out;
	}

	/**
	 * Body.
	 *
	 * @return void
	 */
	protected function content() {
		$readiness = self::readiness();
		$counts    = $this->event_counts();
		$queue     = $this->core->queue->stats();
		$last      = $this->core->messenger->last_send();

		echo '<div class="mw24-grid mw24-grid--2">';

		$this->card_open( __( 'Setup progress', 'mw24-eitaa' ) );
		printf(
			'<div class="mw24-progress" role="progressbar" aria-valuenow="%1$d" aria-valuemin="0" aria-valuemax="100"><span style="width:%1$d%%"></span></div><p class="mw24-progress__label">%2$s</p>',
			(int) $readiness['percent'],
			esc_html(
				sprintf(
					/* translators: %d: percentage. */
					__( '%d%% ready', 'mw24-eitaa' ),
					(int) $readiness['percent']
				)
			)
		);
		echo '<ul class="mw24-checklist">';
		foreach ( $readiness['items'] as $item ) {
			printf(
				'<li class="%1$s"><span class="dashicons %2$s" aria-hidden="true"></span> <a href="%3$s">%4$s</a></li>',
				$item['done'] ? 'is-done' : 'is-todo',
				$item['done'] ? 'dashicons-yes-alt' : 'dashicons-marker',
				esc_url( $item['url'] ),
				esc_html( $item['label'] )
			);
		}
		echo '</ul>';
		if ( $readiness['next'] ) {
			echo '<div class="mw24-next"><strong>' . esc_html__( 'Next step:', 'mw24-eitaa' ) . '</strong> ' . esc_html( $readiness['next']['help'] ) . ' <a class="button button-primary" href="' . esc_url( $readiness['next']['url'] ) . '">' . esc_html__( 'Do it now', 'mw24-eitaa' ) . '</a></div>';
		} else {
			echo '<div class="mw24-next mw24-next--done">' . esc_html__( 'Everything is set up. Your site works as an Eitaa mini-app.', 'mw24-eitaa' ) . '</div>';
		}
		$this->card_close();

		$this->card_open( __( 'Mini-app entry URL', 'mw24-eitaa' ), __( 'Register this address as the destination address of your app in Eitaayar (it must not redirect).', 'mw24-eitaa' ) );
		$this->copy_box( $this->settings->entry_url() );
		echo '<p>' . wp_kses_post( $this->badge( $this->settings->is_active() ? __( 'Active', 'mw24-eitaa' ) : __( 'Inactive', 'mw24-eitaa' ), $this->settings->is_active() ? 'ok' : 'warn' ) ) . '</p>';
		echo '<div class="mw24-notice-box">' . esc_html__( 'Eitaa has no in-app payment API. Payments are made through your own site payment gateway, exactly as in a normal browser.', 'mw24-eitaa' ) . '</div>';
		$this->card_close();

		echo '</div>';

		echo '<div class="mw24-stats">';
		$stats = array(
			__( 'Connected users', 'mw24-eitaa' )         => self::linked_users_count(),
			__( 'Registrations (30 days)', 'mw24-eitaa' ) => isset( $counts['auth.register'] ) ? $counts['auth.register'] : 0,
			__( 'Eitaa logins (30 days)', 'mw24-eitaa' )  => isset( $counts['auth.login'] ) ? $counts['auth.login'] : 0,
			__( 'Messages sent (30 days)', 'mw24-eitaa' ) => isset( $counts['msg.sent'] ) ? $counts['msg.sent'] : 0,
			__( 'Failed messages (30 days)', 'mw24-eitaa' ) => isset( $counts['msg.failed'] ) ? $counts['msg.failed'] : 0,
			__( 'Messages waiting in queue', 'mw24-eitaa' ) => $queue['pending'] + $queue['sending'],
		);
		foreach ( $stats as $label => $value ) {
			printf( '<div class="mw24-stat"><span class="mw24-stat__value">%1$s</span><span class="mw24-stat__label">%2$s</span></div>', esc_html( number_format_i18n( (int) $value ) ), esc_html( $label ) );
		}
		echo '</div>';

		echo '<div class="mw24-grid mw24-grid--2">';
		$this->card_open( __( 'Last message', 'mw24-eitaa' ) );
		if ( empty( $last ) ) {
			echo '<p>' . esc_html__( 'No message has been sent yet.', 'mw24-eitaa' ) . '</p>';
		} else {
			echo '<p>' . wp_kses_post( $this->badge( $last['ok'] ? __( 'Delivered', 'mw24-eitaa' ) : __( 'Failed', 'mw24-eitaa' ), $last['ok'] ? 'ok' : 'error' ) ) . ' ' . esc_html( $this->date( $last['time'] ) ) . '</p>';
			echo '<p>' . esc_html__( 'Recipient:', 'mw24-eitaa' ) . ' <code dir="ltr">' . esc_html( $last['chat_id'] ) . '</code> — ' . esc_html( $last['event'] ) . '</p>';
			if ( ! empty( $last['error'] ) ) {
				echo '<p class="mw24-error-text">' . esc_html( $last['error'] ) . '</p>';
			}
		}
		$this->card_close();

		$this->card_open( __( 'Recent events', 'mw24-eitaa' ) );
		$recent = $this->core->logger->recent( 6 );
		if ( empty( $recent ) ) {
			echo '<p>' . esc_html__( 'Nothing logged yet.', 'mw24-eitaa' ) . '</p>';
		} else {
			echo '<ul class="mw24-mini-log">';
			foreach ( $recent as $row ) {
				printf(
					'<li><span class="mw24-badge mw24-badge--%1$s">%2$s</span> <code>%3$s</code> %4$s <span class="mw24-muted">%5$s</span></li>',
					esc_attr( 'error' === $row['level'] ? 'error' : ( 'warning' === $row['level'] ? 'warn' : 'info' ) ),
					esc_html( $row['level'] ),
					esc_html( $row['event'] ),
					esc_html( $row['message'] ),
					esc_html( $this->date( strtotime( $row['created_at'] . ' UTC' ) ) )
				);
			}
			echo '</ul>';
			echo '<p><a href="' . esc_url( MW24_Eitaa_Admin_Menu::url( 'mw24-eitaa-logs' ) ) . '">' . esc_html__( 'View full log', 'mw24-eitaa' ) . '</a></p>';
		}
		$this->card_close();
		echo '</div>';
	}
}
