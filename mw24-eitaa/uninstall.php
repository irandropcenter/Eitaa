<?php
/**
 * Uninstall: removes data only when "Delete all plugin data on uninstall" is enabled.
 *
 * @package MW24_Eitaa
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'MW24_EITAA_PATH' ) ) {
	define( 'MW24_EITAA_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'MW24_EITAA_DB_VERSION' ) ) {
	define( 'MW24_EITAA_DB_VERSION', '1.0.0' );
}
if ( ! defined( 'MW24_EITAA_BASENAME' ) ) {
	define( 'MW24_EITAA_BASENAME', plugin_basename( MW24_EITAA_PATH . 'mw24-eitaa.php' ) );
}

require_once MW24_EITAA_PATH . 'includes/Settings.php';
require_once MW24_EITAA_PATH . 'includes/Queue.php';
require_once MW24_EITAA_PATH . 'includes/Backfill.php';
require_once MW24_EITAA_PATH . 'includes/Installer.php';

/**
 * Whether the current site asked for data removal.
 *
 * @return bool
 */
function mw24_eitaa_uninstall_wanted() {
	$settings = get_option( 'mw24_eitaa_settings', array() );
	return is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] );
}

if ( is_multisite() ) {
	$mw24_remove_meta = false;
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $mw24_site_id ) {
		switch_to_blog( (int) $mw24_site_id );
		if ( mw24_eitaa_uninstall_wanted() ) {
			MW24_Eitaa_Installer::remove_site_data( false );
			$mw24_remove_meta = true;
		}
		restore_current_blog();
	}
	if ( $mw24_remove_meta ) {
		global $wpdb;
		// User meta is shared by the network.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( 'mw24_eitaa_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
} elseif ( mw24_eitaa_uninstall_wanted() ) {
	MW24_Eitaa_Installer::remove_site_data( true );
}
