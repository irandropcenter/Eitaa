<?php
/**
 * Plugin Name:       Eitaa Connect — Mihanweb24
 * Description:       Opens your WordPress / WooCommerce site as an Eitaa mini-app, logs visitors in with their Eitaa-verified mobile number, syncs that number with any login/registration plugin and sends Eitaa messages.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Mihanweb24
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mw24-eitaa
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   11.2
 *
 * @package MW24_Eitaa
 */

/*
 * Copyright (C) 2026 Mihanweb24
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 */

defined( 'ABSPATH' ) || exit;

define( 'MW24_EITAA_VERSION', '1.0.0' );
define( 'MW24_EITAA_DB_VERSION', '1.0.0' );
define( 'MW24_EITAA_FILE', __FILE__ );
define( 'MW24_EITAA_PATH', plugin_dir_path( __FILE__ ) );
define( 'MW24_EITAA_URL', plugin_dir_url( __FILE__ ) );
define( 'MW24_EITAA_BASENAME', plugin_basename( __FILE__ ) );
define( 'MW24_EITAA_SDK_URL', 'https://developer.eitaa.com/eitaa-web-app.js' );
define( 'MW24_EITAA_API_BASE', 'https://eitaayar.ir/api/app/' );
define( 'MW24_EITAA_MIN_PHP', '7.4' );
define( 'MW24_EITAA_MIN_WP', '6.0' );

/**
 * Admin notice shown when the server does not meet the minimum requirements.
 *
 * @return void
 */
function mw24_eitaa_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: 1: required PHP version, 2: required WordPress version. */
				__( 'Eitaa Connect needs PHP %1$s+ and WordPress %2$s+. The plugin is inactive until the server is upgraded.', 'mw24-eitaa' ),
				MW24_EITAA_MIN_PHP,
				MW24_EITAA_MIN_WP
			)
		)
	);
}

if ( version_compare( PHP_VERSION, MW24_EITAA_MIN_PHP, '<' ) || version_compare( get_bloginfo( 'version' ), MW24_EITAA_MIN_WP, '<' ) ) {
	add_action( 'admin_notices', 'mw24_eitaa_requirements_notice' );
	return;
}

/*
 * Simple PSR-4 style autoloader.
 * MW24_Eitaa_Phone_Mapper      => includes/Phone_Mapper.php
 * MW24_Eitaa_Admin_Users_Table => includes/Admin/Users_Table.php
 * MW24_Eitaa_Mapping_Meta      => includes/Mapping/Meta.php
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'MW24_Eitaa_';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = substr( $class_name, strlen( $prefix ) );
		foreach ( array( 'Admin', 'Compat', 'Mapping', 'License' ) as $dir ) {
			if ( 0 === strpos( $relative, $dir . '_' ) ) {
				$relative = $dir . '/' . substr( $relative, strlen( $dir ) + 1 );
				break;
			}
		}
		$file = MW24_EITAA_PATH . 'includes/' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

require_once MW24_EITAA_PATH . 'includes/functions.php';

register_activation_hook( __FILE__, array( 'MW24_Eitaa_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MW24_Eitaa_Installer', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'MW24_Eitaa_Core', 'boot' ), 5 );
add_action( 'before_woocommerce_init', array( 'MW24_Eitaa_Compat_WooCommerce', 'declare_compatibility' ) );
