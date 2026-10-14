<?php
/**
 * Plugin core: creates the modules and wires their hooks.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin container (singleton).
 */
final class MW24_Eitaa_Core {

	/**
	 * Instance.
	 *
	 * @var MW24_Eitaa_Core|null
	 */
	private static $instance = null;

	/**
	 * Whether init() already ran.
	 *
	 * @var bool
	 */
	private $initialized = false;

	/**
	 * Settings.
	 *
	 * @var MW24_Eitaa_Settings
	 */
	public $settings;

	/**
	 * Logger.
	 *
	 * @var MW24_Eitaa_Logger
	 */
	public $logger;

	/**
	 * Woo.
	 *
	 * @var MW24_Eitaa_Compat_WooCommerce
	 */
	public $woo;

	/**
	 * Mapper.
	 *
	 * @var MW24_Eitaa_Phone_Mapper
	 */
	public $mapper;

	/**
	 * Messenger.
	 *
	 * @var MW24_Eitaa_Messenger
	 */
	public $messenger;

	/**
	 * Queue.
	 *
	 * @var MW24_Eitaa_Queue
	 */
	public $queue;

	/**
	 * Auth.
	 *
	 * @var MW24_Eitaa_Auth
	 */
	public $auth;

	/**
	 * Notifications.
	 *
	 * @var MW24_Eitaa_Notifications
	 */
	public $notifications;

	/**
	 * Frontend.
	 *
	 * @var MW24_Eitaa_Frontend
	 */
	public $frontend;

	/**
	 * Login button.
	 *
	 * @var MW24_Eitaa_Login_Button
	 */
	public $login_button;

	/**
	 * Rest.
	 *
	 * @var MW24_Eitaa_Rest
	 */
	public $rest;

	/**
	 * Backfill.
	 *
	 * @var MW24_Eitaa_Backfill
	 */
	public $backfill;

	/**
	 * Admin.
	 *
	 * @var MW24_Eitaa_Admin_Menu|null
	 */
	public $admin = null;

	/**
	 * License.
	 *
	 * @var MW24_Eitaa_License_Manager|null
	 */
	public $license = null;

	/**
	 * Returns the singleton.
	 *
	 * @return MW24_Eitaa_Core
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooked to plugins_loaded.
	 *
	 * @return void
	 */
	public static function boot() {
		self::instance()->init();
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Builds all modules and registers hooks.
	 *
	 * @return void
	 */
	public function init() {
		if ( $this->initialized ) {
			return;
		}
		$this->initialized = true;

		$this->settings      = new MW24_Eitaa_Settings();
		$this->logger        = new MW24_Eitaa_Logger( $this->settings );
		$this->woo           = new MW24_Eitaa_Compat_WooCommerce();
		$this->mapper        = new MW24_Eitaa_Phone_Mapper( $this->logger );
		$this->messenger     = new MW24_Eitaa_Messenger( $this->settings, $this->logger );
		$this->queue         = new MW24_Eitaa_Queue( $this->settings, $this->messenger, $this->logger );
		$this->auth          = new MW24_Eitaa_Auth( $this->settings, $this->mapper, $this->messenger, $this->logger );
		$this->notifications = new MW24_Eitaa_Notifications( $this->settings, $this->messenger, $this->queue, $this->logger );
		$this->frontend      = new MW24_Eitaa_Frontend( $this->settings, $this->woo );
		$this->login_button  = new MW24_Eitaa_Login_Button( $this->settings, $this->frontend );
		$this->rest          = new MW24_Eitaa_Rest( $this->settings, $this->auth, $this->frontend );
		$this->backfill      = new MW24_Eitaa_Backfill( $this->mapper, $this->logger );

		MW24_Eitaa_Installer::hooks();
		MW24_Eitaa_Installer::maybe_upgrade();

		add_action( 'init', array( $this, 'load_textdomain' ), 0 );
		add_action( 'mw24_eitaa_daily', array( $this, 'daily_maintenance' ) );

		$this->woo->hooks();
		$this->queue->hooks();
		$this->auth->hooks();
		$this->notifications->hooks();
		$this->frontend->hooks();
		$this->login_button->hooks();
		$this->rest->hooks();
		$this->backfill->hooks();

		( new MW24_Eitaa_Compat_Digits( $this->mapper ) )->hooks();
		( new MW24_Eitaa_Compat_Cache() )->hooks();
		( new MW24_Eitaa_Admin_Rest( $this ) )->hooks();

		if ( is_admin() ) {
			$this->admin = new MW24_Eitaa_Admin_Menu( $this );
			$this->admin->hooks();
		}

		if ( ! defined( 'MW24_EITAA_DISABLE_LICENSE' ) && is_readable( MW24_EITAA_PATH . 'includes/License/Manager.php' ) ) {
			$this->license = new MW24_Eitaa_License_Manager( $this->settings );
			$this->license->hooks();
		}

		/**
		 * Fires after the plugin modules are ready.
		 *
		 * @param MW24_Eitaa_Core $core Plugin container.
		 */
		do_action( 'mw24_eitaa_loaded', $this );
	}

	/**
	 * Loads bundled translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'mw24-eitaa', false, dirname( MW24_EITAA_BASENAME ) . '/languages' );
	}

	/**
	 * Daily cron: log retention and queue cleanup.
	 *
	 * @return void
	 */
	public function daily_maintenance() {
		$days = (int) $this->settings->get( 'log_retention_days' );
		if ( $days > 0 ) {
			$this->logger->cleanup( $days );
			$this->queue->cleanup( max( 7, $days ) );
		}
	}
}
