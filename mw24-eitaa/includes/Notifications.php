<?php
/**
 * Event notifications sent as Eitaa messages.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notifications.
 */
class MW24_Eitaa_Notifications {

	const META_CART_UPDATED  = 'mw24_eitaa_cart_updated_at';
	const META_CART_REMINDED = 'mw24_eitaa_cart_reminded_at';
	const ORDER_TRACKING     = '_mw24_eitaa_tracking_sent';
	const ORDER_ADMIN        = '_mw24_eitaa_admin_notified';

	/**
	 * Settings.
	 *
	 * @var MW24_Eitaa_Settings
	 */
	private $settings;

	/**
	 * Messenger.
	 *
	 * @var MW24_Eitaa_Messenger
	 */
	private $messenger;

	/**
	 * Queue.
	 *
	 * @var MW24_Eitaa_Queue
	 */
	private $queue;

	/**
	 * Logger.
	 *
	 * @var MW24_Eitaa_Logger
	 */
	private $logger;

	/**
	 * Per-request de-duplication.
	 *
	 * @var array
	 */
	private $sent = array();

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Settings  $settings  Settings.
	 * @param MW24_Eitaa_Messenger $messenger Messenger.
	 * @param MW24_Eitaa_Queue     $queue     Queue.
	 * @param MW24_Eitaa_Logger    $logger    Logger.
	 */
	public function __construct( $settings, $messenger, $queue, $logger ) {
		$this->settings  = $settings;
		$this->messenger = $messenger;
		$this->queue     = $queue;
		$this->logger    = $logger;
	}

	/**
	 * Hooks (only when the connection is active, so a disabled plugin changes nothing).
	 *
	 * @return void
	 */
	public function hooks() {
		if ( ! $this->settings->is_active() ) {
			return;
		}
		add_action( 'mw24_eitaa_user_registered', array( $this, 'on_eitaa_register' ), 20, 1 );
		add_action( 'user_register', array( $this, 'on_user_register' ), 30, 1 );
		add_action( 'wp_login', array( $this, 'on_login' ), 30, 2 );
		add_action( 'after_password_reset', array( $this, 'on_password_reset' ), 30, 1 );
		add_action( 'woocommerce_customer_reset_password', array( $this, 'on_password_reset' ), 30, 1 );
		add_action( 'profile_update', array( $this, 'on_profile_update' ), 30, 2 );

		if ( class_exists( 'WooCommerce' ) ) {
			add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status' ), 30, 4 );
			add_action( 'woocommerce_checkout_order_processed', array( $this, 'on_new_order' ), 30, 1 );
			add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'on_new_order' ), 30, 1 );
			add_action( 'woocommerce_new_customer_note', array( $this, 'on_customer_note' ), 30, 1 );
			add_action( 'woocommerce_after_order_object_save', array( $this, 'on_order_saved' ), 30, 1 );
			add_action( 'added_post_meta', array( $this, 'on_post_meta' ), 30, 3 );
			add_action( 'updated_post_meta', array( $this, 'on_post_meta' ), 30, 3 );
			add_action( 'woocommerce_add_to_cart', array( $this, 'touch_cart' ), 30, 0 );
			add_action( 'woocommerce_after_cart_item_quantity_update', array( $this, 'touch_cart' ), 30, 0 );
			add_action( 'woocommerce_cart_emptied', array( $this, 'clear_cart' ), 30, 0 );
			add_action( 'mw24_eitaa_hourly', array( $this, 'abandoned_carts' ) );
		}
	}

	// Sending helpers.

	/**
	 * Replaces {placeholders} and applies the public filter.
	 *
	 * @param string $template Template.
	 * @param array  $vars     Placeholder values.
	 * @param string $event    Event name.
	 * @param array  $context  Extra context.
	 * @return string
	 */
	public function render( $template, array $vars, $event = '', array $context = array() ) {
		$replace = array();
		foreach ( $vars as $key => $value ) {
			if ( is_scalar( $value ) || null === $value ) {
				$replace[ '{' . $key . '}' ] = (string) $value;
			}
		}
		$text = strtr( (string) $template, $replace );

		/**
		 * Filters the text of an Eitaa notification before sending.
		 *
		 * @param string $text    Rendered text.
		 * @param string $event   Event (welcome, login, password, order_status, admin_new_order,
		 *                        customer_note, tracking, abandoned_cart, admin_register, bulk, manual).
		 * @param array  $vars    Placeholder values.
		 * @param array  $context Context (user_id, order_id ...).
		 */
		return trim( (string) apply_filters( 'mw24_eitaa_notification_text', $text, $event, $vars, $context ) );
	}

	/**
	 * Delivers through the queue or immediately.
	 *
	 * @param string $chat_id Recipient.
	 * @param string $text    Text.
	 * @param string $event   Event.
	 * @param int    $user_id User.
	 * @return bool
	 */
	private function deliver( $chat_id, $text, $event, $user_id = 0 ) {
		$signature = md5( $chat_id . '|' . $event . '|' . $text );
		if ( isset( $this->sent[ $signature ] ) || '' === trim( $text ) ) {
			return false;
		}
		$this->sent[ $signature ] = true;
		if ( 'sync' === $this->settings->get( 'send_mode' ) ) {
			return true === $this->messenger->send(
				$chat_id,
				$text,
				array(
					'event'   => $event,
					'user_id' => $user_id,
				)
			);
		}
		return (bool) $this->queue->enqueue(
			$chat_id,
			$text,
			array(
				'event'   => $event,
				'user_id' => $user_id,
			)
		);
	}

	/**
	 * Sends a notification to a linked user.
	 *
	 * @param string $event    Event.
	 * @param int    $user_id  User.
	 * @param string $template Template.
	 * @param array  $vars     Placeholder values.
	 * @param array  $context  Context.
	 * @return bool
	 */
	public function to_user( $event, $user_id, $template, array $vars, array $context = array() ) {
		$user_id = (int) $user_id;
		$chat_id = MW24_Eitaa_Auth::get_chat_id( $user_id );
		if ( '' === $chat_id ) {
			return false;
		}
		if ( $this->settings->get( 'only_allows_pm' ) && '0' === (string) get_user_meta( $user_id, MW24_Eitaa_Auth::META_ALLOWS_PM, true ) ) {
			$this->logger->debug(
				'msg.skipped',
				'User has not allowed messages; notification skipped.',
				array(
					'user_id' => $user_id,
					'event'   => $event,
				)
			);
			return false;
		}
		$context['user_id'] = $user_id;
		return $this->deliver( $chat_id, $this->render( $template, $vars, $event, $context ), $event, $user_id );
	}

	/**
	 * Sends a notification to the admin recipients.
	 *
	 * @param string $event    Event.
	 * @param string $template Template.
	 * @param array  $vars     Placeholder values.
	 * @param array  $context  Context.
	 * @return int Number of recipients.
	 */
	public function to_admins( $event, $template, array $vars, array $context = array() ) {
		$count = 0;
		$text  = $this->render( $template, $vars, $event, $context );
		foreach ( $this->admin_chat_ids() as $chat_id ) {
			if ( $this->deliver( $chat_id, $text, $event ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Chat ids of admin recipients (manual list + linked admins/shop managers).
	 *
	 * @return string[]
	 */
	public function admin_chat_ids() {
		$ids = array_filter( preg_split( '/\s+/', (string) $this->settings->get( 'admin_chat_ids' ) ), 'ctype_digit' );
		if ( $this->settings->get( 'admin_linked_recipients' ) ) {
			$users = get_users(
				array(
					'capability__in' => array( 'manage_options', 'manage_woocommerce' ),
					'meta_key'       => MW24_Eitaa_Auth::META_EITAA_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_compare'   => 'EXISTS',
					'number'         => 50,
					'fields'         => 'ID',
				)
			);
			foreach ( $users as $user_id ) {
				$chat_id = MW24_Eitaa_Auth::get_chat_id( (int) $user_id );
				if ( '' !== $chat_id ) {
					$ids[] = $chat_id;
				}
			}
		}

		/**
		 * Filters admin notification recipients.
		 *
		 * @param string[] $ids Chat ids.
		 */
		return array_values( array_unique( (array) apply_filters( 'mw24_eitaa_admin_chat_ids', $ids ) ) );
	}

	/**
	 * Template of an event (stored or default).
	 *
	 * @param string $key Template setting key (tpl_*).
	 * @return string
	 */
	public function template( $key ) {
		$template = (string) $this->settings->get( $key );
		return '' !== trim( $template ) ? $template : MW24_Eitaa_Settings::translated_default( $key );
	}

	/**
	 * Placeholders shared by all events.
	 *
	 * @param WP_User|null $user User.
	 * @return array
	 */
	public function common_vars( $user = null ) {
		$vars = array(
			'name'         => '',
			'first_name'   => '',
			'last_name'    => '',
			'display_name' => '',
			'user_login'   => '',
			'site'         => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			'site_url'     => home_url( '/' ),
			'link'         => home_url( '/' ),
			'date'         => wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ),
		);
		if ( $user instanceof WP_User && $user->exists() ) {
			$vars['first_name']   = (string) $user->first_name;
			$vars['last_name']    = (string) $user->last_name;
			$vars['display_name'] = (string) $user->display_name;
			$vars['user_login']   = (string) $user->user_login;
			$vars['name']         = '' !== $vars['first_name'] ? $vars['first_name'] : $vars['display_name'];
		}
		return $vars;
	}

	/**
	 * Order placeholders.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public function order_vars( $order ) {
		$user  = $order->get_user();
		$vars  = $this->common_vars( $user ? $user : null );
		$first = (string) $order->get_billing_first_name();
		$full  = trim( $first . ' ' . $order->get_billing_last_name() );
		if ( '' !== $first ) {
			$vars['name'] = $first;
		} elseif ( '' === $vars['name'] ) {
			$vars['name'] = $full;
		}
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = $item->get_name() . ' × ' . $item->get_quantity();
		}
		$tracking_key           = (string) $this->settings->get( 'tracking_meta_key' );
		$created                = $order->get_date_created();
		$vars['customer_name']  = $full;
		$vars['order_id']       = (string) $order->get_id();
		$vars['order_number']   = (string) $order->get_order_number();
		$vars['status']         = wc_get_order_status_name( $order->get_status() );
		$vars['total']          = html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' );
		$vars['items']          = wp_sprintf( '%l', $items );
		$vars['payment_method'] = (string) $order->get_payment_method_title();
		$vars['tracking']       = '' !== $tracking_key ? (string) $order->get_meta( $tracking_key ) : '';
		$vars['link']           = $order->get_view_order_url();
		$vars['date']           = $created ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $created->getTimestamp() ) : $vars['date'];
		return $vars;
	}

	/**
	 * Placeholders available for an event (admin help).
	 *
	 * @param string $group common|order|note|cart.
	 * @return string[]
	 */
	public static function placeholders( $group = 'common' ) {
		$common = array( '{name}', '{first_name}', '{last_name}', '{display_name}', '{user_login}', '{site}', '{site_url}', '{link}', '{date}' );
		$order  = array( '{order_number}', '{order_id}', '{status}', '{total}', '{items}', '{customer_name}', '{payment_method}', '{tracking}' );
		switch ( $group ) {
			case 'order':
				return array_merge( $common, $order );
			case 'note':
				return array_merge( $common, $order, array( '{note}' ) );
			case 'cart':
				return array_merge( $common, array( '{cart_count}' ) );
		}
		return $common;
	}

	// Account events.

	/**
	 * Welcome message after an Eitaa registration.
	 *
	 * @param int $user_id User.
	 * @return void
	 */
	public function on_eitaa_register( $user_id ) {
		if ( ! $this->settings->get( 'notify_welcome' ) ) {
			return;
		}
		$user         = get_userdata( (int) $user_id );
		$vars         = $this->common_vars( $user ? $user : null );
		$vars['link'] = mw24_eitaa()->frontend->account_url();
		$this->to_user( 'welcome', (int) $user_id, $this->template( 'tpl_welcome' ), $vars );
	}

	/**
	 * Admin alert for any new registration.
	 *
	 * @param int $user_id User.
	 * @return void
	 */
	public function on_user_register( $user_id ) {
		if ( ! $this->settings->get( 'notify_admin_register' ) ) {
			return;
		}
		$user         = get_userdata( (int) $user_id );
		$vars         = $this->common_vars( $user ? $user : null );
		$vars['link'] = admin_url( 'user-edit.php?user_id=' . (int) $user_id );
		$this->to_admins( 'admin_register', $this->template( 'tpl_admin_register' ), $vars, array( 'user_id' => (int) $user_id ) );
	}

	/**
	 * Security alert on login.
	 *
	 * @param string  $user_login Login.
	 * @param WP_User $user       User.
	 * @return void
	 */
	public function on_login( $user_login, $user ) {
		if ( ! $this->settings->get( 'notify_login' ) || ! $user instanceof WP_User ) {
			return;
		}
		$vars         = $this->common_vars( $user );
		$vars['link'] = mw24_eitaa()->woo->is_active() ? wc_get_account_endpoint_url( 'edit-account' ) : admin_url( 'profile.php' );
		$this->to_user( 'login', (int) $user->ID, $this->template( 'tpl_login' ), $vars );
	}

	/**
	 * Password reset.
	 *
	 * @param WP_User $user User.
	 * @return void
	 */
	public function on_password_reset( $user ) {
		if ( $user instanceof WP_User ) {
			$this->password_changed( (int) $user->ID );
		}
	}

	/**
	 * Password changed from a profile form.
	 *
	 * @param int     $user_id       User.
	 * @param WP_User $old_user_data Previous data.
	 * @return void
	 */
	public function on_profile_update( $user_id, $old_user_data ) {
		$new = get_userdata( (int) $user_id );
		if ( $new && $old_user_data instanceof WP_User && $old_user_data->user_pass !== $new->user_pass ) {
			$this->password_changed( (int) $user_id );
		}
	}

	/**
	 * Sends the password notification once per request.
	 *
	 * @param int $user_id User.
	 * @return void
	 */
	private function password_changed( $user_id ) {
		if ( ! $this->settings->get( 'notify_password' ) || isset( $this->sent[ 'password_' . $user_id ] ) ) {
			return;
		}
		$this->sent[ 'password_' . $user_id ] = true;
		$user                                 = get_userdata( $user_id );
		$vars                                 = $this->common_vars( $user ? $user : null );
		$vars['link']                         = mw24_eitaa()->frontend->account_url();
		$this->to_user( 'password', $user_id, $this->template( 'tpl_password' ), $vars );
	}

	// WooCommerce events.

	/**
	 * Order status changes.
	 *
	 * @param int      $order_id Order id.
	 * @param string   $from     Old status.
	 * @param string   $to       New status.
	 * @param WC_Order $order    Order.
	 * @return void
	 */
	public function on_order_status( $order_id, $from, $to, $order = null ) {
		if ( ! $this->settings->get( 'notify_order_status' ) ) {
			return;
		}
		$to = sanitize_key( (string) $to );
		if ( ! in_array( $to, (array) $this->settings->get( 'order_statuses' ), true ) ) {
			return;
		}
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order || ! $order->get_customer_id() ) {
			return;
		}
		$templates = (array) $this->settings->get( 'tpl_order_status_map' );
		$template  = ! empty( $templates[ $to ] ) ? (string) $templates[ $to ] : $this->template( 'tpl_order_status' );
		$this->to_user(
			'order_status',
			(int) $order->get_customer_id(),
			$template,
			$this->order_vars( $order ),
			array(
				'order_id' => $order->get_id(),
				'status'   => $to,
			)
		);
	}

	/**
	 * New order: admin alert and abandoned-cart reset.
	 *
	 * @param int|WC_Order $order_or_id Order.
	 * @return void
	 */
	public function on_new_order( $order_or_id ) {
		$order = $order_or_id instanceof WC_Order ? $order_or_id : wc_get_order( $order_or_id );
		if ( ! $order ) {
			return;
		}
		if ( $order->get_customer_id() ) {
			delete_user_meta( (int) $order->get_customer_id(), self::META_CART_UPDATED );
		}
		if ( ! $this->settings->get( 'notify_admin_new_order' ) || $order->get_meta( self::ORDER_ADMIN ) ) {
			return;
		}
		$order->update_meta_data( self::ORDER_ADMIN, time() );
		$order->save_meta_data();
		$vars         = $this->order_vars( $order );
		$vars['link'] = $order->get_edit_order_url();
		$this->to_admins( 'admin_new_order', $this->template( 'tpl_admin_new_order' ), $vars, array( 'order_id' => $order->get_id() ) );
	}

	/**
	 * Customer note added by the shop.
	 *
	 * @param array $args order_id, customer_note.
	 * @return void
	 */
	public function on_customer_note( $args ) {
		if ( ! $this->settings->get( 'notify_customer_note' ) || empty( $args['order_id'] ) ) {
			return;
		}
		$order = wc_get_order( (int) $args['order_id'] );
		if ( ! $order || ! $order->get_customer_id() ) {
			return;
		}
		$vars         = $this->order_vars( $order );
		$vars['note'] = isset( $args['customer_note'] ) ? wp_strip_all_tags( (string) $args['customer_note'] ) : '';
		$this->to_user( 'customer_note', (int) $order->get_customer_id(), $this->template( 'tpl_customer_note' ), $vars, array( 'order_id' => $order->get_id() ) );
	}

	/**
	 * Order saved (HPOS and CRUD): check the tracking code.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	public function on_order_saved( $order ) {
		if ( $order instanceof WC_Order ) {
			$this->maybe_send_tracking( $order );
		}
	}

	/**
	 * Legacy post meta writes of the tracking key.
	 *
	 * @param int    $meta_id   Meta id.
	 * @param int    $object_id Post id.
	 * @param string $meta_key  Meta key.
	 * @return void
	 */
	public function on_post_meta( $meta_id, $object_id, $meta_key ) {
		$key = (string) $this->settings->get( 'tracking_meta_key' );
		if ( '' === $key || $meta_key !== $key || ! $this->settings->get( 'notify_tracking' ) ) {
			return;
		}
		if ( 'shop_order' !== get_post_type( (int) $object_id ) ) {
			return;
		}
		$order = wc_get_order( (int) $object_id );
		if ( $order ) {
			$this->maybe_send_tracking( $order );
		}
	}

	/**
	 * Sends the tracking code once per value.
	 *
	 * @param WC_Order $order Order.
	 * @return void
	 */
	private function maybe_send_tracking( $order ) {
		$key = (string) $this->settings->get( 'tracking_meta_key' );
		if ( '' === $key || ! $this->settings->get( 'notify_tracking' ) || ! $order->get_customer_id() ) {
			return;
		}
		$code = trim( (string) $order->get_meta( $key ) );
		if ( '' === $code || (string) $order->get_meta( self::ORDER_TRACKING ) === $code ) {
			return;
		}
		$order->update_meta_data( self::ORDER_TRACKING, $code );
		$order->save_meta_data();
		$vars             = $this->order_vars( $order );
		$vars['tracking'] = $code;
		$this->to_user( 'tracking', (int) $order->get_customer_id(), $this->template( 'tpl_tracking' ), $vars, array( 'order_id' => $order->get_id() ) );
	}

	/**
	 * Remembers cart activity of linked users.
	 *
	 * @return void
	 */
	public function touch_cart() {
		$user_id = get_current_user_id();
		if ( $user_id && $this->settings->get( 'notify_abandoned_cart' ) && '' !== MW24_Eitaa_Auth::get_chat_id( $user_id ) ) {
			update_user_meta( $user_id, self::META_CART_UPDATED, time() );
		}
	}

	/**
	 * Cart emptied.
	 *
	 * @return void
	 */
	public function clear_cart() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			delete_user_meta( $user_id, self::META_CART_UPDATED );
		}
	}

	/**
	 * Hourly abandoned cart reminders.
	 *
	 * @return void
	 */
	public function abandoned_carts() {
		if ( ! $this->settings->get( 'notify_abandoned_cart' ) ) {
			return;
		}
		$threshold = time() - max( 1, (int) $this->settings->get( 'abandoned_hours' ) ) * HOUR_IN_SECONDS;
		$users     = get_users(
			array(
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => self::META_CART_UPDATED,
						'value'   => array( time() - 7 * DAY_IN_SECONDS, $threshold ),
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					),
				),
				'number'     => 50,
			)
		);
		$cart_key  = '_woocommerce_persistent_cart_' . get_current_blog_id();
		foreach ( $users as $user ) {
			$updated  = (int) get_user_meta( $user->ID, self::META_CART_UPDATED, true );
			$reminded = (int) get_user_meta( $user->ID, self::META_CART_REMINDED, true );
			if ( $reminded >= $updated ) {
				continue;
			}
			$cart  = get_user_meta( $user->ID, $cart_key, true );
			$count = 0;
			if ( is_array( $cart ) && ! empty( $cart['cart'] ) && is_array( $cart['cart'] ) ) {
				foreach ( $cart['cart'] as $item ) {
					$count += isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
				}
			}
			update_user_meta( $user->ID, self::META_CART_REMINDED, time() );
			if ( 0 === $count ) {
				delete_user_meta( $user->ID, self::META_CART_UPDATED );
				continue;
			}
			$vars               = $this->common_vars( $user );
			$vars['cart_count'] = (string) $count;
			$vars['link']       = wc_get_cart_url();
			$this->to_user( 'abandoned_cart', (int) $user->ID, $this->template( 'tpl_abandoned_cart' ), $vars );
		}
	}
}
