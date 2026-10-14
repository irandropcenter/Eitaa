<?php
/**
 * "Log in with Eitaa" button.
 *
 * Override: copy to {your-theme}/mw24-eitaa/login-button.php
 *
 * Available in $args: state (guest|logged_in|linked), in_app (bool), label, class,
 * redirect, hint, link.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

$mw24_state = isset( $args['state'] ) ? (string) $args['state'] : 'guest';
$mw24_class = trim( 'mw24-eitaa-login ' . ( isset( $args['class'] ) ? (string) $args['class'] : '' ) );
?>
<div class="<?php echo esc_attr( $mw24_class ); ?>">
	<?php if ( 'linked' === $mw24_state ) : ?>
		<span class="mw24-eitaa-login__linked"><span class="mw24-eitaa-login__icon" aria-hidden="true"></span><?php echo esc_html( $args['label'] ); ?></span>
	<?php else : ?>
		<button type="button" class="mw24-eitaa-login__btn" data-mw24-eitaa-login data-redirect="<?php echo esc_url( isset( $args['redirect'] ) ? (string) $args['redirect'] : '' ); ?>">
			<span class="mw24-eitaa-login__icon" aria-hidden="true"></span>
			<span class="mw24-eitaa-login__label"><?php echo esc_html( $args['label'] ); ?></span>
		</button>
		<?php if ( 'guest' === $mw24_state && ! empty( $args['hint'] ) ) : ?>
			<p class="mw24-eitaa-login__hint">
				<?php echo esc_html( $args['hint'] ); ?>
				<?php if ( ! empty( $args['link'] ) ) : ?>
					<a class="mw24-eitaa-login__open" href="<?php echo esc_url( $args['link'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open in Eitaa', 'mw24-eitaa' ); ?></a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</div>
