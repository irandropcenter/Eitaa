<?php
/**
 * WooCommerce helpers (optional dependency).
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce compatibility. Every method is safe to call without WooCommerce.
 */
class MW24_Eitaa_Compat_WooCommerce {

	/**
	 * Declares HPOS and Cart/Checkout blocks compatibility.
	 *
	 * @return void
	 */
	public static function declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MW24_EITAA_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', MW24_EITAA_FILE, true );
		}
	}

	/**
	 * Whether WooCommerce is loaded.
	 *
	 * @return bool
	 */
	public function is_active() {
		return class_exists( 'WooCommerce' ) && function_exists( 'WC' );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		if ( $this->is_active() ) {
			add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'cart_fragment' ) );
		}
	}

	/**
	 * Items in the cart.
	 *
	 * @return int
	 */
	public function cart_count() {
		if ( ! $this->is_active() || ! WC()->cart ) {
			return 0;
		}
		return (int) WC()->cart->get_cart_contents_count();
	}

	/**
	 * Hidden element read by the mini-app script (also refreshed as a cart fragment).
	 *
	 * @return string
	 */
	public function cart_count_html() {
		$count = $this->cart_count();
		return '<span class="mw24-eitaa-cart-count" data-count="' . esc_attr( (string) $count ) . '" hidden>' . esc_html( (string) $count ) . '</span>';
	}

	/**
	 * Adds the counter to WooCommerce cart fragments.
	 *
	 * @param array $fragments Fragments.
	 * @return array
	 */
	public function cart_fragment( $fragments ) {
		$fragments                               = is_array( $fragments ) ? $fragments : array();
		$fragments['span.mw24-eitaa-cart-count'] = $this->cart_count_html();
		return $fragments;
	}

	/**
	 * Cart URL.
	 *
	 * @return string
	 */
	public function cart_url() {
		return $this->is_active() ? (string) wc_get_cart_url() : '';
	}

	/**
	 * Checkout URL.
	 *
	 * @return string
	 */
	public function checkout_url() {
		return $this->is_active() ? (string) wc_get_checkout_url() : '';
	}

	/**
	 * My account URL.
	 *
	 * @return string
	 */
	public function account_url() {
		return $this->is_active() ? (string) wc_get_page_permalink( 'myaccount' ) : '';
	}

	/**
	 * Cart page.
	 *
	 * @return bool
	 */
	public function is_cart() {
		return $this->is_active() && function_exists( 'is_cart' ) && is_cart();
	}

	/**
	 * Checkout page (including order-pay / order-received endpoints).
	 *
	 * @return bool
	 */
	public function is_checkout() {
		return $this->is_active() && function_exists( 'is_checkout' ) && is_checkout();
	}

	/**
	 * Order statuses without the "wc-" prefix.
	 *
	 * @return array slug => label
	 */
	public function order_statuses() {
		if ( ! $this->is_active() ) {
			return array();
		}
		$out = array();
		foreach ( wc_get_order_statuses() as $slug => $label ) {
			$out[ preg_replace( '/^wc-/', '', $slug ) ] = $label;
		}
		return $out;
	}
}
