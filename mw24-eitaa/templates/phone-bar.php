<?php
/**
 * Bottom bar that asks guests to share their Eitaa phone number.
 * Rendered hidden; assets/js/eitaa-app.js decides when to show it.
 *
 * Override: copy to {your-theme}/mw24-eitaa/phone-bar.php
 * Keep the id and the data-mw24-eitaa-* attributes.
 *
 * Available in $args: text, share_label, later_label.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="mw24-eitaa-phone-bar" class="mw24-eitaa-bar" role="dialog" aria-live="polite" aria-label="<?php esc_attr_e( 'Log in with Eitaa', 'mw24-eitaa' ); ?>" hidden>
	<p class="mw24-eitaa-bar__text"><?php echo esc_html( $args['text'] ); ?></p>
	<div class="mw24-eitaa-bar__actions">
		<button type="button" class="mw24-eitaa-bar__share" data-mw24-eitaa-share><?php echo esc_html( $args['share_label'] ); ?></button>
		<button type="button" class="mw24-eitaa-bar__later" data-mw24-eitaa-later><?php echo esc_html( $args['later_label'] ); ?></button>
	</div>
	<p class="mw24-eitaa-bar__status" data-mw24-eitaa-status hidden></p>
</div>
