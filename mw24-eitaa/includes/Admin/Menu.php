<?php
/**
 * Admin menu, assets, form handlers and notices.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
class MW24_Eitaa_Admin_Menu {

	/**
	 * Core.
	 *
	 * @var MW24_Eitaa_Core
	 */
	private $core;

	/**
	 * Pages.
	 *
	 * @var MW24_Eitaa_Admin_Page[]
	 */
	private $pages = array();

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Core $core Core.
	 */
	public function __construct( $core ) {
		$this->core = $core;
	}

	/**
	 * Capability required for every admin screen and action.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability needed to manage the plugin.
		 *
		 * @param string $capability Capability.
		 */
		return (string) apply_filters( 'mw24_eitaa_capability', 'manage_options' );
	}

	/**
	 * Page slugs => classes.
	 *
	 * @return array
	 */
	public static function page_classes() {
		return array(
			'mw24-eitaa'               => 'MW24_Eitaa_Admin_Page_Dashboard',
			'mw24-eitaa-connection'    => 'MW24_Eitaa_Admin_Page_Connection',
			'mw24-eitaa-appearance'    => 'MW24_Eitaa_Admin_Page_Appearance',
			'mw24-eitaa-login'         => 'MW24_Eitaa_Admin_Page_Login',
			'mw24-eitaa-notifications' => 'MW24_Eitaa_Admin_Page_Notifications',
			'mw24-eitaa-users'         => 'MW24_Eitaa_Admin_Page_Users',
			'mw24-eitaa-logs'          => 'MW24_Eitaa_Admin_Page_Logs',
			'mw24-eitaa-tools'         => 'MW24_Eitaa_Admin_Page_Tools',
			'mw24-eitaa-help'          => 'MW24_Eitaa_Admin_Page_Help',
		);
	}

	/**
	 * URL of an admin page.
	 *
	 * @param string $slug Page slug.
	 * @param array  $args Extra query args.
	 * @return string
	 */
	public static function url( $slug, array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => $slug ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'admin_post_mw24_eitaa_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_mw24_eitaa_action', array( $this, 'handle_action' ) );
		add_action( 'admin_notices', array( $this, 'global_notices' ) );
		add_filter( 'plugin_action_links_' . MW24_EITAA_BASENAME, array( $this, 'action_links' ) );
		( new MW24_Eitaa_Admin_User_Profile( $this->core ) )->hooks();
	}

	/**
	 * Lazily creates a page object.
	 *
	 * @param string $slug Slug.
	 * @return MW24_Eitaa_Admin_Page|null
	 */
	public function page( $slug ) {
		$classes = self::page_classes();
		if ( ! isset( $classes[ $slug ] ) ) {
			return null;
		}
		if ( ! isset( $this->pages[ $slug ] ) ) {
			$class                = $classes[ $slug ];
			$this->pages[ $slug ] = new $class( $this->core, $slug );
		}
		return $this->pages[ $slug ];
	}

	/**
	 * Registers the menu.
	 *
	 * @return void
	 */
	public function menu() {
		$cap  = self::capability();
		$icon = 'dashicons-format-chat';

		add_menu_page(
			__( 'Eitaa Connect', 'mw24-eitaa' ),
			__( 'Eitaa Connect', 'mw24-eitaa' ),
			$cap,
			'mw24-eitaa',
			array( $this, 'render' ),
			$icon,
			58
		);
		$titles = array(
			'mw24-eitaa'               => __( 'Dashboard', 'mw24-eitaa' ),
			'mw24-eitaa-connection'    => __( 'Connection', 'mw24-eitaa' ),
			'mw24-eitaa-appearance'    => __( 'Appearance & behavior', 'mw24-eitaa' ),
			'mw24-eitaa-login'         => __( 'Login & phone sync', 'mw24-eitaa' ),
			'mw24-eitaa-notifications' => __( 'Notifications', 'mw24-eitaa' ),
			'mw24-eitaa-users'         => __( 'Connected users', 'mw24-eitaa' ),
			'mw24-eitaa-logs'          => __( 'Log', 'mw24-eitaa' ),
			'mw24-eitaa-tools'         => __( 'Tools', 'mw24-eitaa' ),
			'mw24-eitaa-help'          => __( 'Help', 'mw24-eitaa' ),
		);
		foreach ( $titles as $slug => $title ) {
			add_submenu_page( 'mw24-eitaa', $title, $title, $cap, $slug, array( $this, 'render' ) );
		}
	}

	/**
	 * Menu titles (used by the in-page navigation).
	 *
	 * @return array
	 */
	public static function nav_items() {
		return array(
			'mw24-eitaa'               => __( 'Dashboard', 'mw24-eitaa' ),
			'mw24-eitaa-connection'    => __( 'Connection', 'mw24-eitaa' ),
			'mw24-eitaa-appearance'    => __( 'Appearance & behavior', 'mw24-eitaa' ),
			'mw24-eitaa-login'         => __( 'Login & phone sync', 'mw24-eitaa' ),
			'mw24-eitaa-notifications' => __( 'Notifications', 'mw24-eitaa' ),
			'mw24-eitaa-users'         => __( 'Connected users', 'mw24-eitaa' ),
			'mw24-eitaa-logs'          => __( 'Log', 'mw24-eitaa' ),
			'mw24-eitaa-tools'         => __( 'Tools', 'mw24-eitaa' ),
			'mw24-eitaa-help'          => __( 'Help', 'mw24-eitaa' ),
		);
	}

	/**
	 * Renders the current page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'mw24-eitaa' ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'mw24-eitaa';
		$page = $this->page( $slug );
		if ( $page ) {
			$page->render();
		}
	}

	/**
	 * Enqueues admin assets on plugin screens and user profile screens.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function assets( $hook ) {
		$ours    = false !== strpos( (string) $hook, 'mw24-eitaa' );
		$profile = in_array( $hook, array( 'user-edit.php', 'profile.php', 'users.php' ), true );
		if ( ! $ours && ! $profile ) {
			return;
		}
		wp_enqueue_style( 'mw24-eitaa-admin', MW24_EITAA_URL . 'assets/css/admin.css', array(), MW24_EITAA_VERSION );
		if ( $ours ) {
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_script( 'wp-color-picker' );
		}
		wp_enqueue_script( 'mw24-eitaa-admin', MW24_EITAA_URL . 'assets/js/admin.js', array(), MW24_EITAA_VERSION, true );
		wp_add_inline_script(
			'mw24-eitaa-admin',
			'window.MW24EitaaAdmin = ' . wp_json_encode(
				array(
					'restUrl' => esc_url_raw( rest_url( MW24_Eitaa_Rest::NS . '/admin/' ) ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'formats' => MW24_Eitaa_Phone_Normalizer::format_labels(),
					'i18n'    => array(
						'loading'      => __( 'Loading…', 'mw24-eitaa' ),
						'error'        => __( 'Request failed.', 'mw24-eitaa' ),
						'sent'         => __( 'Message sent.', 'mw24-eitaa' ),
						'copied'       => __( 'Copied!', 'mw24-eitaa' ),
						'confirm'      => __( 'Are you sure?', 'mw24-eitaa' ),
						'use'          => __( 'Use', 'mw24-eitaa' ),
						'key'          => __( 'Meta key', 'mw24-eitaa' ),
						'rows'         => __( 'Rows', 'mw24-eitaa' ),
						'phoneLike'    => __( 'Phone-like', 'mw24-eitaa' ),
						'sample'       => __( 'Masked sample', 'mw24-eitaa' ),
						'noKeys'       => __( 'No meta keys found.', 'mw24-eitaa' ),
						'chooseTable'  => __( '— Select a table —', 'mw24-eitaa' ),
						'chooseColumn' => __( '— Select a column —', 'mw24-eitaa' ),
						'required'     => __( 'required', 'mw24-eitaa' ),
						'column'       => __( 'Column', 'mw24-eitaa' ),
						'type'         => __( 'Type', 'mw24-eitaa' ),
						'valueSource'  => __( 'Value', 'mw24-eitaa' ),
						'notSet'       => __( 'Not set (skip)', 'mw24-eitaa' ),
						'extraTypes'   => array(
							'fixed'     => __( 'Fixed value', 'mw24-eitaa' ),
							'now'       => __( 'Current time (site)', 'mw24-eitaa' ),
							'now_gmt'   => __( 'Current time (UTC)', 'mw24-eitaa' ),
							'timestamp' => __( 'Unix timestamp', 'mw24-eitaa' ),
							'user_id'   => __( 'User ID', 'mw24-eitaa' ),
							'phone'     => __( 'Phone (formatted)', 'mw24-eitaa' ),
							'empty'     => __( 'Empty string', 'mw24-eitaa' ),
						),
						'normalized'   => __( 'Normalized', 'mw24-eitaa' ),
						'variants'     => __( 'Searched variants', 'mw24-eitaa' ),
						'foundUser'    => __( 'User that would log in', 'mw24-eitaa' ),
						'newUser'      => __( 'No user found — a new account would be created.', 'mw24-eitaa' ),
						'matches'      => __( 'Matches', 'mw24-eitaa' ),
						'conflict'     => __( 'Warning: this number matches more than one user. The first match (by priority) is used.', 'mw24-eitaa' ),
						'writes'       => __( 'Values that would be written', 'mw24-eitaa' ),
						'field'        => __( 'Field', 'mw24-eitaa' ),
						'value'        => __( 'Value', 'mw24-eitaa' ),
						'current'      => __( 'Current', 'mw24-eitaa' ),
						'action'       => __( 'Action', 'mw24-eitaa' ),
						'actions'      => array(
							'write'     => __( 'write', 'mw24-eitaa' ),
							'unchanged' => __( 'unchanged', 'mw24-eitaa' ),
							'conflict'  => __( 'skip: different number already stored', 'mw24-eitaa' ),
							'duplicate' => __( 'skip: number belongs to another user', 'mw24-eitaa' ),
							'read_only' => __( 'read-only', 'mw24-eitaa' ),
							'invalid'   => __( 'invalid table map', 'mw24-eitaa' ),
							'error'     => __( 'error', 'mw24-eitaa' ),
						),
						'backfill'     => array(
							'idle'      => __( 'Not started', 'mw24-eitaa' ),
							'running'   => __( 'Running…', 'mw24-eitaa' ),
							'done'      => __( 'Finished', 'mw24-eitaa' ),
							'cancelled' => __( 'Cancelled', 'mw24-eitaa' ),
						),
						/* translators: 1: processed users, 2: total users, 3: updated users, 4: values written, 5: conflicts. */
						'progress'     => __( '%1$s of %2$s users processed — %3$s users updated, %4$s values written, %5$s skipped.', 'mw24-eitaa' ),
						/* translators: 1: scanned users, 2: users with phone, 3: users to update, 4: values, 5: conflicts. */
						'preview'      => __( 'Scanned %1$s users: %2$s have a phone, %3$s would be updated (%4$s values), %5$s skipped because of conflicts.', 'mw24-eitaa' ),
						/* translators: %s: number of users. */
						'recipients'   => __( 'This message would be queued for %s users.', 'mw24-eitaa' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/**
	 * Adds a notice for the current user (shown after redirect).
	 *
	 * @param string $type    success|error|warning|info.
	 * @param string $message Message.
	 * @return void
	 */
	public static function add_notice( $type, $message ) {
		$key       = 'mw24_eitaa_notices_' . get_current_user_id();
		$notices   = get_transient( $key );
		$notices   = is_array( $notices ) ? $notices : array();
		$notices[] = array(
			'type'    => $type,
			'message' => $message,
		);
		set_transient( $key, $notices, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Prints and clears pending notices.
	 *
	 * @return void
	 */
	public static function print_notices() {
		$key     = 'mw24_eitaa_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		if ( ! is_array( $notices ) ) {
			return;
		}
		delete_transient( $key );
		foreach ( $notices as $notice ) {
			$type = in_array( $notice['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $notice['type'] : 'info';
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $notice['message'] ) );
		}
	}

	/**
	 * Redirects back to a plugin page.
	 *
	 * @param string $slug Page slug.
	 * @param array  $args Query args.
	 * @return void
	 */
	private function back( $slug, array $args = array() ) {
		wp_safe_redirect( self::url( $slug, $args ) );
		exit;
	}

	/**
	 * Settings form handler (admin-post.php?action=mw24_eitaa_save).
	 *
	 * @return void
	 */
	public function handle_save() {
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mw24-eitaa' ), 403 );
		}
		check_admin_referer( 'mw24_eitaa_save' );
		$slug    = isset( $_POST['mw24_page'] ) ? sanitize_key( wp_unslash( $_POST['mw24_page'] ) ) : 'mw24-eitaa';
		$section = isset( $_POST['mw24_section'] ) ? sanitize_key( wp_unslash( $_POST['mw24_section'] ) ) : '';
		$tab     = isset( $_POST['mw24_tab'] ) ? sanitize_key( wp_unslash( $_POST['mw24_tab'] ) ) : '';
		// Each page sanitizes its own fields field-by-field.
		$input = isset( $_POST['mw24'] ) && is_array( $_POST['mw24'] ) ? wp_unslash( $_POST['mw24'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$page  = $this->page( $slug );
		if ( $page ) {
			$result = $page->save( $section, $input );
			if ( is_wp_error( $result ) ) {
				self::add_notice( 'error', $result->get_error_message() );
			} else {
				self::add_notice( 'success', __( 'Settings saved.', 'mw24-eitaa' ) );
			}
		}
		$this->back( $slug, $tab ? array( 'tab' => $tab ) : array() );
	}

	/**
	 * Action handler (admin-post.php?action=mw24_eitaa_action&do=...).
	 *
	 * @return void
	 */
	public function handle_action() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified right below with an action specific nonce.
		$do = isset( $_REQUEST['do'] ) ? sanitize_key( wp_unslash( $_REQUEST['do'] ) ) : '';
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'mw24-eitaa' ), 403 );
		}
		check_admin_referer( 'mw24_eitaa_action_' . $do );
		$core = $this->core;
		$slug = 'mw24-eitaa-tools';
		$args = array();

		switch ( $do ) {
			case 'unlink_user':
				$user_id = isset( $_REQUEST['user_id'] ) ? absint( $_REQUEST['user_id'] ) : 0;
				if ( $user_id && $core->auth->unlink( $user_id, 'admin' ) ) {
					self::add_notice( 'success', __( 'The Eitaa account was disconnected.', 'mw24-eitaa' ) );
				}
				$slug = 'mw24-eitaa-users';
				break;

			case 'clear_logs':
				$core->logger->clear();
				self::add_notice( 'success', __( 'The log was cleared.', 'mw24-eitaa' ) );
				$slug = 'mw24-eitaa-logs';
				break;

			case 'queue_process':
				$result = $core->queue->process( max( 1, (int) $core->settings->get( 'queue_rate' ) ) );
				/* translators: 1: sent count, 2: failed count. */
				self::add_notice( 'success', sprintf( __( 'Queue processed: %1$d sent, %2$d failed.', 'mw24-eitaa' ), $result['sent'], $result['failed'] ) );
				break;

			case 'queue_retry':
				$batch = isset( $_REQUEST['batch'] ) ? sanitize_key( wp_unslash( $_REQUEST['batch'] ) ) : '';
				/* translators: %d: number of messages. */
				self::add_notice( 'success', sprintf( __( '%d failed messages were put back in the queue.', 'mw24-eitaa' ), $core->queue->retry_failed( $batch ) ) );
				if ( '' !== $batch ) {
					$slug = 'mw24-eitaa-notifications';
					$args = array( 'tab' => 'bulk' );
				}
				break;

			case 'queue_clear_done':
				$core->queue->clear( 'sent' );
				$core->queue->clear( 'failed' );
				self::add_notice( 'success', __( 'Sent and failed messages were removed from the queue.', 'mw24-eitaa' ) );
				break;

			case 'queue_clear_all':
				$core->queue->clear();
				self::add_notice( 'success', __( 'The message queue was emptied.', 'mw24-eitaa' ) );
				break;

			case 'clear_cache':
				delete_transient( 'mw24_eitaa_meta_keys' );
				MW24_Eitaa_Mapping_Profiles::flush();
				self::add_notice( 'success', __( 'Cached data was cleared.', 'mw24-eitaa' ) );
				break;

			case 'reset_settings':
				$core->settings->reset();
				$core->logger->warning( 'settings.reset', 'All settings were reset to defaults.', array( 'user_id' => get_current_user_id() ) );
				self::add_notice( 'success', __( 'All settings were reset to their defaults. The token and phone maps were kept.', 'mw24-eitaa' ) );
				break;

			case 'delete_token':
				$core->settings->delete_token();
				$core->logger->warning( 'token.deleted', 'App token deleted.', array( 'user_id' => get_current_user_id() ) );
				self::add_notice( 'success', __( 'The app token was deleted.', 'mw24-eitaa' ) );
				$slug = 'mw24-eitaa-connection';
				break;

			case 'bulk_send':
				$text    = isset( $_POST['bulk_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['bulk_text'] ) ) : '';
				$filters = array(
					'role'           => isset( $_POST['bulk_role'] ) ? sanitize_key( wp_unslash( $_POST['bulk_role'] ) ) : '',
					'since'          => isset( $_POST['bulk_since'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_since'] ) ) : '',
					'until'          => isset( $_POST['bulk_until'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_until'] ) ) : '',
					'only_allows_pm' => ! empty( $_POST['bulk_only_pm'] ),
				);
				$title   = isset( $_POST['bulk_title'] ) ? sanitize_text_field( wp_unslash( $_POST['bulk_title'] ) ) : '';
				$result  = $core->queue->create_batch( $text, $filters, $title );
				if ( is_wp_error( $result ) ) {
					self::add_notice( 'error', $result->get_error_message() );
				} else {
					/* translators: %d: number of messages. */
					self::add_notice( 'success', sprintf( __( '%d messages were added to the queue.', 'mw24-eitaa' ), $result['total'] ) );
				}
				$slug = 'mw24-eitaa-notifications';
				$args = array( 'tab' => 'bulk' );
				break;

			case 'license':
				if ( $core->license ) {
					$core->license->handle_form();
				}
				break;

			default:
				self::add_notice( 'error', __( 'Unknown action.', 'mw24-eitaa' ) );
		}
		$this->back( $slug, $args );
	}

	/**
	 * Builds a nonce-protected action URL.
	 *
	 * @param string $action   Action.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function action_url( $action, array $args = array() ) {
		$url = add_query_arg(
			array_merge(
				array(
					'action' => 'mw24_eitaa_action',
					'do'     => $action,
				),
				$args
			),
			admin_url( 'admin-post.php' )
		);
		return wp_nonce_url( $url, 'mw24_eitaa_action_' . $action );
	}

	/**
	 * Notices shown on all admin screens (only to managers, only when needed).
	 *
	 * @return void
	 */
	public function global_notices() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$id     = $screen ? (string) $screen->id : '';
		if ( false !== strpos( $id, 'mw24-eitaa' ) ) {
			self::print_notices();
		}
		if ( false === strpos( $id, 'mw24-eitaa' ) && 'plugins' !== $id && 'dashboard' !== $id ) {
			return;
		}
		$settings = $this->core->settings;
		if ( $settings->token_stored() && ! $settings->token_from_constant() && ! $settings->has_token() ) {
			printf(
				'<div class="notice notice-error"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Eitaa Connect: the saved app token can no longer be decrypted (the site security keys changed). Please enter the token again.', 'mw24-eitaa' ),
				esc_url( self::url( 'mw24-eitaa-connection' ) ),
				esc_html__( 'Connection settings', 'mw24-eitaa' )
			);
		}
		if ( 'plugins' === $id && ! $settings->is_active() ) {
			printf(
				'<div class="notice notice-info is-dismissible"><p>%s <a href="%s">%s</a></p></div>',
				esc_html__( 'Eitaa Connect is installed but not connected yet.', 'mw24-eitaa' ),
				esc_url( self::url( 'mw24-eitaa' ) ),
				esc_html__( 'Start setup', 'mw24-eitaa' )
			);
		}
		$unconfirmed = $this->core->mapper->unconfirmed_profiles();
		if ( $unconfirmed && false !== strpos( $id, 'mw24-eitaa' ) ) {
			$names = array();
			foreach ( $unconfirmed as $profile_id ) {
				$profile = MW24_Eitaa_Mapping_Profiles::get( $profile_id );
				$names[] = $profile ? $profile['name'] : $profile_id;
			}
			printf(
				'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
				esc_html(
					sprintf(
						/* translators: %s: list of plugin names. */
						__( 'Auto-detected login plugins: %s. Please review and confirm the phone sync profile.', 'mw24-eitaa' ),
						wp_sprintf( '%l', $names )
					)
				),
				esc_url( self::url( 'mw24-eitaa-login', array( 'tab' => 'sync' ) ) ),
				esc_html__( 'Review', 'mw24-eitaa' )
			);
		}
	}

	/**
	 * Plugin row links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::url( 'mw24-eitaa' ) ), esc_html__( 'Settings', 'mw24-eitaa' ) ),
			sprintf( '<a href="%s">%s</a>', esc_url( self::url( 'mw24-eitaa-help' ) ), esc_html__( 'Help', 'mw24-eitaa' ) )
		);
		return $links;
	}
}
