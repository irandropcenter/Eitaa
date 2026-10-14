<?php
/**
 * "Log in with Eitaa" button: shortcode, block, wp-login.php and WooCommerce forms.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Login button renderer.
 */
class MW24_Eitaa_Login_Button {

	/**
	 * Settings.
	 *
	 * @var MW24_Eitaa_Settings
	 */
	private $settings;

	/**
	 * Frontend.
	 *
	 * @var MW24_Eitaa_Frontend
	 */
	private $frontend;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Settings $settings Settings.
	 * @param MW24_Eitaa_Frontend $frontend Frontend.
	 */
	public function __construct( $settings, $frontend ) {
		$this->settings = $settings;
		$this->frontend = $frontend;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_shortcode( 'mw24_eitaa_login', array( $this, 'shortcode' ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_style' ) );
		add_action( 'login_enqueue_scripts', array( $this, 'register_style' ) );
		add_action( 'login_form', array( $this, 'wp_login_form' ) );
		add_action( 'woocommerce_login_form_end', array( $this, 'woo_login_form' ) );
	}

	/**
	 * Registers the button stylesheet (enqueued on demand).
	 *
	 * @return void
	 */
	public function register_style() {
		wp_register_style( 'mw24-eitaa-button', MW24_EITAA_URL . 'assets/css/login-button.css', array(), MW24_EITAA_VERSION );
	}

	/**
	 * [mw24_eitaa_login label="" class="" redirect="" show_linked="no"]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'label'       => '',
				'class'       => '',
				'redirect'    => '',
				'show_linked' => 'no',
			),
			$atts,
			'mw24_eitaa_login'
		);
		return $this->render( $atts );
	}

	/**
	 * Renders the button.
	 *
	 * @param array $atts label, class, redirect, show_linked.
	 * @return string
	 */
	public function render( array $atts ) {
		if ( ! $this->settings->is_active() || ! $this->settings->get( 'login_enabled' ) ) {
			return '';
		}
		$atts = wp_parse_args(
			$atts,
			array(
				'label'       => '',
				'class'       => '',
				'redirect'    => '',
				'show_linked' => 'no',
			)
		);
		if ( ! wp_style_is( 'mw24-eitaa-button', 'registered' ) ) {
			$this->register_style();
		}
		wp_enqueue_style( 'mw24-eitaa-button' );

		if ( is_user_logged_in() ) {
			if ( 'yes' !== $atts['show_linked'] ) {
				return '';
			}
			$linked = '' !== MW24_Eitaa_Auth::get_chat_id( get_current_user_id() );
			return MW24_Eitaa_Template::render(
				'login-button.php',
				array(
					'state'    => $linked ? 'linked' : 'logged_in',
					'in_app'   => $this->frontend->is_mini_app(),
					'label'    => $linked ? __( 'Connected to Eitaa', 'mw24-eitaa' ) : __( 'Connect my Eitaa account', 'mw24-eitaa' ),
					'class'    => sanitize_html_class( $atts['class'] ),
					'redirect' => '',
					'hint'     => '',
					'link'     => '',
				)
			);
		}

		$in_app = $this->frontend->is_mini_app();
		if ( ! $in_app && 'hide' === $this->settings->get( 'outside_behavior' ) ) {
			return '';
		}
		$label = '' !== trim( (string) $atts['label'] ) ? (string) $atts['label'] : (string) $this->settings->get( 'button_label' );
		return MW24_Eitaa_Template::render(
			'login-button.php',
			array(
				'state'    => 'guest',
				'in_app'   => $in_app,
				'label'    => $label,
				'class'    => implode( ' ', array_map( 'sanitize_html_class', preg_split( '/\s+/', (string) $atts['class'] ) ) ),
				'redirect' => $atts['redirect'] ? wp_validate_redirect( esc_url_raw( (string) $atts['redirect'] ), '' ) : '',
				'hint'     => 'hint' === $this->settings->get( 'outside_behavior' ) ? (string) $this->settings->get( 'outside_hint' ) : '',
				'link'     => (string) $this->settings->get( 'miniapp_link' ),
			)
		);
	}

	/**
	 * Registers the Gutenberg block (dynamic, server rendered).
	 *
	 * @return void
	 */
	public function register_block() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		if ( ! wp_style_is( 'mw24-eitaa-button', 'registered' ) ) {
			$this->register_style();
		}
		wp_register_script(
			'mw24-eitaa-block-editor',
			MW24_EITAA_URL . 'blocks/login-button/index.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components' ),
			MW24_EITAA_VERSION,
			true
		);
		wp_add_inline_script(
			'mw24-eitaa-block-editor',
			'window.MW24EitaaBlock = ' . wp_json_encode(
				array(
					'title'        => __( 'Eitaa login button', 'mw24-eitaa' ),
					'labelField'   => __( 'Button text', 'mw24-eitaa' ),
					'labelHelp'    => __( 'Leave empty to use the text from the plugin settings.', 'mw24-eitaa' ),
					'defaultLabel' => (string) $this->settings->get( 'button_label' ),
					'preview'      => __( 'Shown only to guests. Inside the Eitaa mini-app it logs the visitor in with their Eitaa number; outside Eitaa it is hidden or shows a hint (see settings).', 'mw24-eitaa' ),
				)
			) . ';',
			'before'
		);
		register_block_type(
			MW24_EITAA_PATH . 'blocks/login-button',
			array(
				'render_callback' => array( $this, 'render_block' ),
			)
		);
	}

	/**
	 * Block render callback.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public function render_block( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();
		return $this->render(
			array(
				'label' => isset( $attributes['label'] ) ? sanitize_text_field( (string) $attributes['label'] ) : '',
				'class' => isset( $attributes['className'] ) ? (string) $attributes['className'] : '',
			)
		);
	}

	/**
	 * Button inside wp-login.php.
	 *
	 * @return void
	 */
	public function wp_login_form() {
		if ( $this->settings->get( 'button_wp_login' ) ) {
			echo $this->render( array( 'class' => 'mw24-eitaa-login--wp' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes output.
		}
	}

	/**
	 * Button inside the WooCommerce login form.
	 *
	 * @return void
	 */
	public function woo_login_form() {
		if ( $this->settings->get( 'button_woo_login' ) ) {
			echo $this->render( array( 'class' => 'mw24-eitaa-login--woo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes output.
		}
	}
}
