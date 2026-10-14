<?php
/**
 * PHPUnit bootstrap (WordPress test library).
 *
 * Setup: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version]
 * Run:   composer install && vendor/bin/phpunit
 *
 * @package MW24_Eitaa
 */

$mw24_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $mw24_tests_dir ) {
	$mw24_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$mw24_polyfills = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $mw24_polyfills && '' !== $mw24_polyfills ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $mw24_polyfills );
} elseif ( file_exists( dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php' ) ) {
	require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
}

if ( ! file_exists( $mw24_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$mw24_tests_dir}/includes/functions.php. Run bin/install-wp-tests.sh first." . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once $mw24_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__ ) . '/mw24-eitaa.php';
	}
);

// Generous limits so the suite is not throttled (one test checks throttling explicitly).
tests_add_filter(
	'mw24_eitaa_rate_limits',
	static function ( $limits ) {
		if ( empty( $GLOBALS['mw24_eitaa_test_strict_limits'] ) ) {
			foreach ( $limits as $type => $limit ) {
				$limits[ $type ] = array( 100000, 600 );
			}
		}
		return $limits;
	}
);

require $mw24_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/helpers/class-mw24-eitaa-test-signer.php';

// Plugin tables and a real custom table for the table-map tests (created outside test transactions).
MW24_Eitaa_Installer::install();
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mw24_test_phone_book" ); // phpcs:ignore WordPress.DB
$wpdb->query( "CREATE TABLE {$wpdb->prefix}mw24_test_phone_book ( id bigint(20) unsigned NOT NULL AUTO_INCREMENT, uid bigint(20) unsigned NOT NULL, mobile varchar(20) NOT NULL, source varchar(20) NOT NULL, created datetime NOT NULL, PRIMARY KEY  (id) )" ); // phpcs:ignore WordPress.DB
