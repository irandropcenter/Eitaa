<?php
/**
 * Base class of admin pages.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin page base.
 */
abstract class MW24_Eitaa_Admin_Page {

	/**
	 * Core.
	 *
	 * @var MW24_Eitaa_Core
	 */
	protected $core;

	/**
	 * Settings.
	 *
	 * @var MW24_Eitaa_Settings
	 */
	protected $settings;

	/**
	 * Slug.
	 *
	 * @var string
	 */
	protected $slug;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Core $core Core.
	 * @param string          $slug Page slug.
	 */
	public function __construct( $core, $slug ) {
		$this->core     = $core;
		$this->settings = $core->settings;
		$this->slug     = $slug;
	}

	/**
	 * Page title.
	 *
	 * @return string
	 */
	abstract public function title();

	/**
	 * Page body.
	 *
	 * @return void
	 */
	abstract protected function content();

	/**
	 * Settings sections this page may save.
	 *
	 * @return string[]
	 */
	protected function sections() {
		return array();
	}

	/**
	 * Saves a settings section (generic implementation).
	 *
	 * @param string $section Section.
	 * @param array  $input   Unslashed input.
	 * @return true|WP_Error
	 */
	public function save( $section, array $input ) {
		if ( ! in_array( $section, $this->sections(), true ) ) {
			return new WP_Error( 'mw24_eitaa_bad_section', __( 'Invalid form.', 'mw24-eitaa' ) );
		}
		$this->settings->update( $this->settings->sanitize_section( $section, $input ) );
		$this->core->logger->info( 'settings.saved', 'Settings saved: ' . $section, array( 'user_id' => get_current_user_id() ) );
		return true;
	}

	/**
	 * Renders the page frame.
	 *
	 * @return void
	 */
	public function render() {
		echo '<div class="wrap mw24-wrap">';
		echo '<h1 class="mw24-title">' . esc_html( $this->title() ) . '</h1>';
		$this->nav();
		echo '<hr class="wp-header-end">';
		$this->content();
		echo '<p class="mw24-footer">' . esc_html(
			sprintf(
				/* translators: %s: plugin version. */
				__( 'Eitaa Connect %s — by Mihanweb24', 'mw24-eitaa' ),
				MW24_EITAA_VERSION
			)
		) . '</p>';
		echo '</div>';
	}

	/**
	 * In-page navigation across plugin screens.
	 *
	 * @return void
	 */
	protected function nav() {
		echo '<nav class="mw24-nav" aria-label="' . esc_attr__( 'Eitaa Connect sections', 'mw24-eitaa' ) . '">';
		foreach ( MW24_Eitaa_Admin_Menu::nav_items() as $slug => $title ) {
			printf(
				'<a class="mw24-nav__item%1$s" href="%2$s">%3$s</a>',
				$slug === $this->slug ? ' is-active' : '',
				esc_url( MW24_Eitaa_Admin_Menu::url( $slug ) ),
				esc_html( $title )
			);
		}
		echo '</nav>';
	}

	/**
	 * Current tab from the query string.
	 *
	 * @param string   $fallback Default tab.
	 * @param string[] $allowed Allowed tabs.
	 * @return string
	 */
	protected function current_tab( $fallback, array $allowed ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : $fallback;
		return in_array( $tab, $allowed, true ) ? $tab : $fallback;
	}

	/**
	 * Tab links.
	 *
	 * @param array  $tabs    tab => label.
	 * @param string $current Current tab.
	 * @return void
	 */
	protected function tabs( array $tabs, $current ) {
		echo '<h2 class="nav-tab-wrapper mw24-tabs">';
		foreach ( $tabs as $tab => $label ) {
			printf(
				'<a class="nav-tab%1$s" href="%2$s">%3$s</a>',
				$tab === $current ? ' nav-tab-active' : '',
				esc_url( MW24_Eitaa_Admin_Menu::url( $this->slug, array( 'tab' => $tab ) ) ),
				esc_html( $label )
			);
		}
		echo '</h2>';
	}

	/**
	 * Opens a settings form.
	 *
	 * @param string $section Section.
	 * @param string $tab     Tab to come back to.
	 * @return void
	 */
	protected function form_open( $section, $tab = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mw24-form" autocomplete="off">';
		echo '<input type="hidden" name="action" value="mw24_eitaa_save">';
		echo '<input type="hidden" name="mw24_page" value="' . esc_attr( $this->slug ) . '">';
		echo '<input type="hidden" name="mw24_section" value="' . esc_attr( $section ) . '">';
		echo '<input type="hidden" name="mw24_tab" value="' . esc_attr( $tab ) . '">';
		wp_nonce_field( 'mw24_eitaa_save' );
	}

	/**
	 * Closes a settings form.
	 *
	 * @param string $label Button label.
	 * @return void
	 */
	protected function form_close( $label = '' ) {
		submit_button( '' !== $label ? $label : __( 'Save changes', 'mw24-eitaa' ) );
		echo '</form>';
	}

	/**
	 * Opens a card.
	 *
	 * @param string $title Title.
	 * @param string $desc  Description (limited HTML).
	 * @param string $css_class Extra class.
	 * @return void
	 */
	protected function card_open( $title, $desc = '', $css_class = '' ) {
		echo '<div class="mw24-card ' . esc_attr( $css_class ) . '">';
		if ( '' !== $title ) {
			echo '<h2 class="mw24-card__title">' . esc_html( $title ) . '</h2>';
		}
		if ( '' !== $desc ) {
			echo '<p class="mw24-card__desc">' . wp_kses( $desc, MW24_Eitaa_Admin_Fields::allowed_html() ) . '</p>';
		}
	}

	/**
	 * Closes a card.
	 *
	 * @return void
	 */
	protected function card_close() {
		echo '</div>';
	}

	/**
	 * Opens a form table.
	 *
	 * @return void
	 */
	protected function table_open() {
		echo '<table class="form-table mw24-form-table" role="presentation"><tbody>';
	}

	/**
	 * Closes a form table.
	 *
	 * @return void
	 */
	protected function table_close() {
		echo '</tbody></table>';
	}

	/**
	 * Renders a settings field row.
	 *
	 * @param string $key   Key.
	 * @param string $label Label.
	 * @param string $desc  Description.
	 * @param array  $args  Args.
	 * @return void
	 */
	protected function field( $key, $label, $desc = '', array $args = array() ) {
		MW24_Eitaa_Admin_Fields::row( $this->settings, $key, $label, $desc, $args );
	}

	/**
	 * Small status badge.
	 *
	 * @param string $text Text.
	 * @param string $type ok|warn|error|info.
	 * @return string
	 */
	protected function badge( $text, $type = 'info' ) {
		return '<span class="mw24-badge mw24-badge--' . esc_attr( $type ) . '">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Button that triggers a nonce-protected admin action (POST form).
	 *
	 * @param string $action      Action.
	 * @param string $label   Label.
	 * @param array  $fields  Hidden fields.
	 * @param string $css_class   Button class.
	 * @param string $confirm Confirmation text.
	 * @return void
	 */
	protected function action_button( $action, $label, array $fields = array(), $css_class = 'button', $confirm = '' ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="mw24-inline-form">';
		echo '<input type="hidden" name="action" value="mw24_eitaa_action">';
		echo '<input type="hidden" name="do" value="' . esc_attr( $action ) . '">';
		foreach ( $fields as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		wp_nonce_field( 'mw24_eitaa_action_' . $action );
		printf(
			'<button type="submit" class="%1$s"%2$s>%3$s</button>',
			esc_attr( $css_class ),
			'' !== $confirm ? ' data-mw24-confirm="' . esc_attr( $confirm ) . '"' : '',
			esc_html( $label )
		);
		echo '</form>';
	}

	/**
	 * Formats a timestamp in the site timezone.
	 *
	 * @param int $timestamp Timestamp.
	 * @return string
	 */
	protected function date( $timestamp ) {
		$timestamp = (int) $timestamp;
		if ( $timestamp <= 0 ) {
			return '—';
		}
		return wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
	}

	/**
	 * Copyable code box.
	 *
	 * @param string $text Text.
	 * @return void
	 */
	protected function copy_box( $text ) {
		printf(
			'<div class="mw24-copy"><code dir="ltr">%1$s</code> <button type="button" class="button button-small" data-mw24-copy="%2$s">%3$s</button></div>',
			esc_html( $text ),
			esc_attr( $text ),
			esc_html__( 'Copy', 'mw24-eitaa' )
		);
	}
}
