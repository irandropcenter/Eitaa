<?php
/**
 * Message queue ({$prefix}mw24_eitaa_queue) processed by Action Scheduler or WP-Cron.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Queue.
 */
class MW24_Eitaa_Queue {

	const HOOK           = 'mw24_eitaa_process_queue';
	const GROUP          = 'mw24-eitaa';
	const BATCHES_OPTION = 'mw24_eitaa_batches';
	const MAX_ATTEMPTS   = 3;

	/**
	 * Settings.
	 *
	 * @var MW24_Eitaa_Settings
	 */
	private $settings;

	/**
	 * Messenger.
	 *
	 * @var MW24_Eitaa_Messenger
	 */
	private $messenger;

	/**
	 * Logger.
	 *
	 * @var MW24_Eitaa_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Settings  $settings  Settings.
	 * @param MW24_Eitaa_Messenger $messenger Messenger.
	 * @param MW24_Eitaa_Logger    $logger    Logger.
	 */
	public function __construct( $settings, $messenger, $logger ) {
		$this->settings  = $settings;
		$this->messenger = $messenger;
		$this->logger    = $logger;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( self::HOOK, array( $this, 'run' ) );
		add_action( 'mw24_eitaa_hourly', array( $this, 'watchdog' ) );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'mw24_eitaa_queue';
	}

	/**
	 * Adds one message.
	 *
	 * @param string $chat_id Recipient.
	 * @param string $text    Text.
	 * @param array  $args    user_id, event, batch, delay (seconds).
	 * @return int|false Row id.
	 */
	public function enqueue( $chat_id, $text, array $args = array() ) {
		if ( ! MW24_Eitaa_Installer::tables_ready() ) {
			return false;
		}
		$chat_id = preg_replace( '/\D/', '', (string) $chat_id );
		if ( '' === $chat_id || '' === trim( (string) $text ) ) {
			return false;
		}
		global $wpdb;
		$now = time();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$ok = $wpdb->insert(
			self::table(),
			array(
				'batch'        => isset( $args['batch'] ) ? substr( sanitize_key( $args['batch'] ), 0, 40 ) : '',
				'event'        => isset( $args['event'] ) ? substr( sanitize_key( $args['event'] ), 0, 64 ) : '',
				'user_id'      => isset( $args['user_id'] ) ? (int) $args['user_id'] : 0,
				'chat_id'      => $chat_id,
				'message'      => (string) $text,
				'status'       => 'pending',
				'attempts'     => 0,
				'created_at'   => gmdate( 'Y-m-d H:i:s', $now ),
				'scheduled_at' => gmdate( 'Y-m-d H:i:s', $now + ( isset( $args['delay'] ) ? max( 0, (int) $args['delay'] ) : 0 ) ),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		if ( ! $ok ) {
			return false;
		}
		if ( empty( $args['no_schedule'] ) ) {
			$this->schedule();
		}
		return (int) $wpdb->insert_id;
	}

	/**
	 * Schedules a queue run (Action Scheduler when available, otherwise WP-Cron).
	 *
	 * @param int $delay Seconds.
	 * @return void
	 */
	public function schedule( $delay = 0 ) {
		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( self::HOOK, array(), self::GROUP ) ) {
				as_schedule_single_action( time() + max( 0, (int) $delay ), self::HOOK, array(), self::GROUP );
			}
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + max( 0, (int) $delay ), self::HOOK );
		}
	}

	/**
	 * Scheduled run: sends up to "messages per minute", then reschedules if needed.
	 *
	 * @return void
	 */
	public function run() {
		$this->process( max( 1, (int) $this->settings->get( 'queue_rate' ) ) );
		if ( $this->count_due() > 0 ) {
			$this->schedule( MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Hourly safety net in case a scheduled run was lost.
	 *
	 * @return void
	 */
	public function watchdog() {
		if ( $this->count_due() > 0 ) {
			$this->schedule();
		}
	}

	/**
	 * Number of pending messages that are due.
	 *
	 * @return int
	 */
	public function count_due() {
		if ( ! MW24_Eitaa_Installer::tables_ready() ) {
			return 0;
		}
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s", gmdate( 'Y-m-d H:i:s' ) ) );
	}

	/**
	 * Sends due messages.
	 *
	 * @param int $limit       Max messages.
	 * @param int $time_budget Max seconds.
	 * @return array{sent:int,failed:int}
	 */
	public function process( $limit, $time_budget = 25 ) {
		$result = array(
			'sent'   => 0,
			'failed' => 0,
		);
		if ( ! MW24_Eitaa_Installer::tables_ready() || ! $this->settings->has_token() ) {
			return $result;
		}
		global $wpdb;
		$table = self::table();
		$start = time();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = 'pending' AND scheduled_at <= %s ORDER BY id ASC LIMIT %d", gmdate( 'Y-m-d H:i:s' ), max( 1, (int) $limit ) ), ARRAY_A );
		foreach ( (array) $rows as $row ) {
			if ( time() - $start > $time_budget ) {
				break;
			}
			// Claim the row so parallel runs never send twice.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$claimed = $wpdb->update(
				$table,
				array(
					'status'       => 'sending',
					'scheduled_at' => gmdate( 'Y-m-d H:i:s' ),
				),
				array(
					'id'     => (int) $row['id'],
					'status' => 'pending',
				),
				array( '%s', '%s' ),
				array( '%d', '%s' )
			);
			if ( 1 !== (int) $claimed ) {
				continue;
			}
			$sent     = $this->messenger->send(
				$row['chat_id'],
				$row['message'],
				array(
					'event'   => '' !== $row['event'] ? $row['event'] : 'queue',
					'user_id' => (int) $row['user_id'],
				)
			);
			$attempts = (int) $row['attempts'] + 1;
			if ( true === $sent ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array(
						'status'     => 'sent',
						'attempts'   => $attempts,
						'sent_at'    => gmdate( 'Y-m-d H:i:s' ),
						'last_error' => '',
					),
					array( 'id' => (int) $row['id'] ),
					array( '%s', '%d', '%s', '%s' ),
					array( '%d' )
				);
				++$result['sent'];
			} else {
				$retry = $attempts < self::MAX_ATTEMPTS && 'mw24_eitaa_http_error' === $sent->get_error_code();
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->update(
					$table,
					array(
						'status'       => $retry ? 'pending' : 'failed',
						'attempts'     => $attempts,
						'scheduled_at' => gmdate( 'Y-m-d H:i:s', time() + 5 * MINUTE_IN_SECONDS * $attempts ),
						'last_error'   => substr( $sent->get_error_message(), 0, 250 ),
					),
					array( 'id' => (int) $row['id'] ),
					array( '%s', '%d', '%s', '%s' ),
					array( '%d' )
				);
				if ( ! $retry ) {
					++$result['failed'];
				}
			}
		}
		// Rows stuck in "sending" (crashed run) go back to pending after 15 minutes.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'pending' WHERE status = 'sending' AND scheduled_at < %s", gmdate( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS ) ) );
		return $result;
	}

	/**
	 * Counts by status.
	 *
	 * @param string $batch Optional batch id.
	 * @return array{pending:int,sending:int,sent:int,failed:int,total:int}
	 */
	public function stats( $batch = '' ) {
		$stats = array(
			'pending' => 0,
			'sending' => 0,
			'sent'    => 0,
			'failed'  => 0,
			'total'   => 0,
		);
		if ( ! MW24_Eitaa_Installer::tables_ready() ) {
			return $stats;
		}
		global $wpdb;
		$table = self::table();
		if ( '' !== $batch ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT status, COUNT(*) AS n FROM {$table} WHERE batch = %s GROUP BY status", $batch ), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS n FROM {$table} GROUP BY status", ARRAY_A );
		}
		foreach ( (array) $rows as $row ) {
			if ( isset( $stats[ $row['status'] ] ) ) {
				$stats[ $row['status'] ] = (int) $row['n'];
			}
			$stats['total'] += (int) $row['n'];
		}
		return $stats;
	}

	/**
	 * Messages sent in the last N days.
	 *
	 * @param int $days Days.
	 * @return int
	 */
	public function sent_since( $days ) {
		if ( ! MW24_Eitaa_Installer::tables_ready() ) {
			return 0;
		}
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = 'sent' AND sent_at >= %s", gmdate( 'Y-m-d H:i:s', time() - (int) $days * DAY_IN_SECONDS ) ) );
	}

	/**
	 * Puts failed messages back in the queue.
	 *
	 * @param string $batch Optional batch.
	 * @return int Rows.
	 */
	public function retry_failed( $batch = '' ) {
		global $wpdb;
		$table = self::table();
		$now   = gmdate( 'Y-m-d H:i:s' );
		if ( '' !== $batch ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'pending', attempts = 0, scheduled_at = %s WHERE status = 'failed' AND batch = %s", $now, $batch ) );
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = 'pending', attempts = 0, scheduled_at = %s WHERE status = 'failed'", $now ) );
		}
		if ( $rows ) {
			$this->schedule();
		}
		return (int) $rows;
	}

	/**
	 * Deletes messages by status ('' = all).
	 *
	 * @param string $status Status.
	 * @return int Rows.
	 */
	public function clear( $status = '' ) {
		global $wpdb;
		$table = self::table();
		if ( '' === $status ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->query( "DELETE FROM {$table}" );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status = %s", $status ) );
	}

	/**
	 * Deletes finished messages older than N days.
	 *
	 * @param int $days Days.
	 * @return void
	 */
	public function cleanup( $days ) {
		if ( ! MW24_Eitaa_Installer::tables_ready() ) {
			return;
		}
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE status IN ('sent','failed') AND created_at < %s", gmdate( 'Y-m-d H:i:s', time() - max( 1, (int) $days ) * DAY_IN_SECONDS ) ) );
	}

	/**
	 * Creates a bulk-send batch for linked users matching the filters.
	 *
	 * @param string $text    Template (placeholders {name} {first_name} {site} {link} {date}).
	 * @param array  $filters role, since (Y-m-d), until (Y-m-d), only_allows_pm (bool).
	 * @param string $title   Batch title.
	 * @return array|WP_Error array( batch, total )
	 */
	public function create_batch( $text, array $filters, $title = '' ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return new WP_Error( 'mw24_eitaa_empty_text', __( 'The message text is empty.', 'mw24-eitaa' ) );
		}
		$batch   = 'b' . gmdate( 'ymdHis' ) . strtolower( wp_generate_password( 6, false, false ) );
		$total   = 0;
		$page    = 1;
		$notify  = mw24_eitaa()->notifications;
		$filters = wp_parse_args(
			$filters,
			array(
				'role'           => '',
				'since'          => '',
				'until'          => '',
				'only_allows_pm' => true,
			)
		);
		do {
			$users = self::linked_users( $filters, 500, $page );
			foreach ( $users as $user ) {
				$chat_id = MW24_Eitaa_Auth::get_chat_id( $user->ID );
				if ( '' === $chat_id ) {
					continue;
				}
				$message = $notify->render( $text, $notify->common_vars( $user ), 'bulk', array( 'user_id' => $user->ID ) );
				if ( $this->enqueue(
					$chat_id,
					$message,
					array(
						'user_id'     => $user->ID,
						'event'       => 'bulk',
						'batch'       => $batch,
						'no_schedule' => true,
					)
				) ) {
					++$total;
				}
			}
			++$page;
			$fetched = count( $users );
		} while ( 500 === $fetched );

		$batches = get_option( self::BATCHES_OPTION, array() );
		$batches = is_array( $batches ) ? $batches : array();
		array_unshift(
			$batches,
			array(
				'id'      => $batch,
				'title'   => sanitize_text_field( '' !== $title ? $title : wp_trim_words( $text, 8, '…' ) ),
				'created' => time(),
				'total'   => $total,
				'author'  => get_current_user_id(),
				'filters' => $filters,
			)
		);
		update_option( self::BATCHES_OPTION, array_slice( $batches, 0, 30 ), false );
		if ( $total > 0 ) {
			$this->schedule();
		}
		$this->logger->info( 'queue.batch_created', sprintf( 'Bulk batch %s created with %d messages.', $batch, $total ) );
		return array(
			'batch' => $batch,
			'total' => $total,
		);
	}

	/**
	 * Linked users for bulk sending.
	 *
	 * @param array $filters  Filters.
	 * @param int   $number   Page size (-1 = count only query).
	 * @param int   $page     Page.
	 * @return WP_User[]
	 */
	public static function linked_users( array $filters, $number = 500, $page = 1 ) {
		$meta = array(
			'relation' => 'AND',
			array(
				'key'     => MW24_Eitaa_Auth::META_EITAA_ID,
				'compare' => 'EXISTS',
			),
		);
		if ( ! empty( $filters['only_allows_pm'] ) ) {
			$meta[] = array(
				'key'   => MW24_Eitaa_Auth::META_ALLOWS_PM,
				'value' => '1',
			);
		}
		$since = isset( $filters['since'] ) ? strtotime( (string) $filters['since'] ) : false;
		$until = isset( $filters['until'] ) ? strtotime( (string) $filters['until'] . ' 23:59:59' ) : false;
		if ( $since ) {
			$meta[] = array(
				'key'     => MW24_Eitaa_Auth::META_LINKED_AT,
				'value'   => $since,
				'compare' => '>=',
				'type'    => 'NUMERIC',
			);
		}
		if ( $until ) {
			$meta[] = array(
				'key'     => MW24_Eitaa_Auth::META_LINKED_AT,
				'value'   => $until,
				'compare' => '<=',
				'type'    => 'NUMERIC',
			);
		}
		$args = array(
			'meta_query' => $meta, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'number'     => (int) $number,
			'paged'      => max( 1, (int) $page ),
			'orderby'    => 'ID',
			'order'      => 'ASC',
		);
		if ( ! empty( $filters['role'] ) ) {
			$args['role'] = sanitize_key( $filters['role'] );
		}
		return get_users( $args );
	}

	/**
	 * Number of users a bulk send would reach.
	 *
	 * @param array $filters Filters.
	 * @return int
	 */
	public static function count_linked_users( array $filters ) {
		$query = new WP_User_Query(
			array(
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => MW24_Eitaa_Auth::META_EITAA_ID,
						'compare' => 'EXISTS',
					),
				),
				'number'      => 1,
				'count_total' => true,
				'fields'      => 'ID',
			)
		);
		if ( empty( $filters['only_allows_pm'] ) && empty( $filters['role'] ) && empty( $filters['since'] ) && empty( $filters['until'] ) ) {
			return (int) $query->get_total();
		}
		$total = 0;
		$page  = 1;
		do {
			$users   = self::linked_users( $filters, 1000, $page );
			$fetched = count( $users );
			$total  += $fetched;
			++$page;
		} while ( 1000 === $fetched );
		return $total;
	}

	/**
	 * Recent batches with live stats.
	 *
	 * @return array
	 */
	public function batches() {
		$batches = get_option( self::BATCHES_OPTION, array() );
		$out     = array();
		foreach ( is_array( $batches ) ? $batches : array() as $batch ) {
			$batch['stats'] = $this->stats( (string) $batch['id'] );
			$out[]          = $batch;
		}
		return $out;
	}
}
