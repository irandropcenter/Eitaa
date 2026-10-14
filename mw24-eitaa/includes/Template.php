<?php
/**
 * Template loader with theme overrides.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Themes can override templates by copying them to {theme}/mw24-eitaa/{name}.
 */
class MW24_Eitaa_Template {

	/**
	 * Locates a template.
	 *
	 * @param string $name File name, e.g. login-button.php.
	 * @return string
	 */
	public static function locate( $name ) {
		$name  = basename( (string) $name );
		$theme = locate_template( array( 'mw24-eitaa/' . $name ) );
		return $theme ? $theme : MW24_EITAA_PATH . 'templates/' . $name;
	}

	/**
	 * Renders a template to a string. Templates receive their data in $args.
	 *
	 * @param string $name Template name.
	 * @param array  $args Data.
	 * @return string
	 */
	public static function render( $name, array $args = array() ) {
		/**
		 * Filters the template file path.
		 *
		 * @param string $file Path.
		 * @param string $name Template name.
		 * @param array  $args Data.
		 */
		$file = (string) apply_filters( 'mw24_eitaa_template', self::locate( $name ), $name, $args );
		if ( ! is_readable( $file ) ) {
			return '';
		}
		ob_start();
		self::load( $file, $args );
		return (string) ob_get_clean();
	}

	/**
	 * Includes a template in an isolated scope.
	 *
	 * @param string $mw24_file Template path.
	 * @param array  $args      Data available to the template.
	 * @return void
	 */
	private static function load( $mw24_file, array $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args is used by the included template.
		include $mw24_file;
	}
}
