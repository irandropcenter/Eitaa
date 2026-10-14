<?php
/**
 * Installation, schema upgrades, cron scheduling and data removal.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class MW24_Eitaa_Installer {

	const DB_OPTION = 'mw24_eitaa_db_version';

	/**
	 * Request cache of tables_ready().
	 *
	 * @var bool|null
	 */
	private static $ready = null;

	/**
	 * Multisite hooks.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_action( 'wp_initialize_site', array( __CLASS__, 'on_new_site' ), 200 );
		add_filter( 'wpmu_drop_tables', array( __CLASS__, 'drop_tables_on_site_delete' ), 10, 2 );
	}

	/**
	 * Activation callback.
	 *
	 * @param bool $network_wide Network activation.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites(
				array(
					'fields' => 'ids',
					'number' => 500,
				)
			) as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install();
				restore_current_blog();
			}
			return;
		}
		self::install();
	}

	/**
	 * Deactivation callback: removes scheduled events only (data is kept).
	 *
	 * @param bool $network_wide Network deactivation.
	 * @return void
	 */
	public static function deactivate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites(
				array(
					'fields' => 'ids',
					'number' => 500,
				)
			) as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::clear_events();
				restore_current_blog();
			}
			return;
		}
		self::clear_events();
	}

	/**
	 * Installs/updates the current site.
	 *
	 * @return void
	 */
	public static function install() {
		self::create_tables();
		self::schedule_events();
		$state = get_option( MW24_Eitaa_Settings::STATE_OPTION, array() );
		if ( ! is_array( $state ) ) {
			$state = array();
		}
		if ( empty( $state['installed_at'] ) ) {
			$state['installed_at'] = time();
			update_option( MW24_Eitaa_Settings::STATE_OPTION, $state, false );
		}
		update_option( self::DB_OPTION, MW24_EITAA_DB_VERSION, true );
		self::$ready = null;
	}

	/**
	 * Runs install() when the stored schema version differs (plugin updates).
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_OPTION ) !== MW24_EITAA_DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Creates/updates the custom tables with dbDelta.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$log     = $wpdb->prefix . 'mw24_eitaa_log';
		$queue   = $wpdb->prefix . 'mw24_eitaa_queue';

		$sql = "CREATE TABLE {$log} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  created_at datetime NOT NULL,
  level varchar(10) NOT NULL DEFAULT 'info',
  event varchar(64) NOT NULL DEFAULT '',
  message text NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  eitaa_id varchar(32) NOT NULL DEFAULT '',
  ip varchar(45) NOT NULL DEFAULT '',
  context longtext NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at),
  KEY level (level),
  KEY event (event),
  KEY user_id (user_id)
) {$charset};
CREATE TABLE {$queue} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  batch varchar(40) NOT NULL DEFAULT '',
  event varchar(64) NOT NULL DEFAULT '',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  chat_id varchar(32) NOT NULL DEFAULT '',
  message longtext NOT NULL,
  status varchar(20) NOT NULL DEFAULT 'pending',
  attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  last_error varchar(255) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  scheduled_at datetime NOT NULL,
  sent_at datetime NULL DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY status_scheduled (status,scheduled_at),
  KEY batch (batch),
  KEY user_id (user_id)
) {$charset};";
		dbDelta( $sql );
	}

	/**
	 * Whether the custom tables exist (cached per request).
	 *
	 * @return bool
	 */
	public static function tables_ready() {
		if ( null === self::$ready ) {
			self::$ready = MW24_EITAA_DB_VERSION === get_option( self::DB_OPTION );
		}
		return self::$ready;
	}

	/**
	 * Schedules recurring events.
	 *
	 * @return void
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( 'mw24_eitaa_hourly' ) ) {
			wp_schedule_event( time() + 10 * MINUTE_IN_SECONDS, 'hourly', 'mw24_eitaa_hourly' );
		}
		if ( ! wp_next_scheduled( 'mw24_eitaa_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'mw24_eitaa_daily' );
		}
	}

	/**
	 * Removes all scheduled events of the plugin.
	 *
	 * @return void
	 */
	public static function clear_events() {
		foreach ( array( 'mw24_eitaa_hourly', 'mw24_eitaa_daily', MW24_Eitaa_Queue::HOOK, MW24_Eitaa_Backfill::HOOK ) as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), MW24_Eitaa_Queue::GROUP );
		}
	}

	/**
	 * Creates tables for a site added to a network where the plugin is network-active.
	 *
	 * @param WP_Site $site New site.
	 * @return void
	 */
	public static function on_new_site( $site ) {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( ! is_plugin_active_for_network( MW24_EITAA_BASENAME ) ) {
			return;
		}
		switch_to_blog( (int) $site->blog_id );
		self::install();
		restore_current_blog();
	}

	/**
	 * Drops the plugin tables when a site is deleted.
	 *
	 * @param string[] $tables  Tables to drop.
	 * @param int      $site_id Site id.
	 * @return string[]
	 */
	public static function drop_tables_on_site_delete( $tables, $site_id ) {
		global $wpdb;
		$prefix   = $wpdb->get_blog_prefix( (int) $site_id );
		$tables[] = $prefix . 'mw24_eitaa_log';
		$tables[] = $prefix . 'mw24_eitaa_queue';
		return $tables;
	}

	/**
	 * Removes all data of the current site (used by uninstall.php).
	 *
	 * @param bool $include_user_meta Also delete plugin user meta (network-wide table).
	 * @return void
	 */
	public static function remove_site_data( $include_user_meta = false ) {
		global $wpdb;
		self::clear_events();
		$tables = array( $wpdb->prefix . 'mw24_eitaa_log', $wpdb->prefix . 'mw24_eitaa_queue' );
		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( 'mw24_eitaa_' ) . '%', $wpdb->esc_like( '_transient_mw24_eitaa_' ) . '%', $wpdb->esc_like( '_transient_timeout_mw24_eitaa_' ) . '%' ) );
		if ( $include_user_meta ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $wpdb->esc_like( 'mw24_eitaa_' ) . '%' ) );
		}
		wp_cache_flush();
	}
}
