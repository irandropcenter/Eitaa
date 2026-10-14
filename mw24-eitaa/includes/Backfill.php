<?php
/**
 * "Sync the past" tool: copies existing phone numbers between maps in batches.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Backfill engine. Existing different numbers are never overwritten and numbers
 * already owned by another user in a target field are skipped.
 */
class MW24_Eitaa_Backfill {

	const OPTION = 'mw24_eitaa_backfill';
	const HOOK   = 'mw24_eitaa_backfill_batch';
	const BATCH  = 100;

	/**
	 * Mapper.
	 *
	 * @var MW24_Eitaa_Phone_Mapper
	 */
	private $mapper;

	/**
	 * Logger.
	 *
	 * @var MW24_Eitaa_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param MW24_Eitaa_Phone_Mapper $mapper Mapper.
	 * @param MW24_Eitaa_Logger       $logger Logger.
	 */
	public function __construct( $mapper, $logger ) {
		$this->mapper = $mapper;
		$this->logger = $logger;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action( self::HOOK, array( $this, 'run_batch' ) );
	}

	/**
	 * Current state.
	 *
	 * @return array
	 */
	public function status() {
		$state = get_option( self::OPTION, array() );
		$state = is_array( $state ) ? $state : array();
		return wp_parse_args(
			$state,
			array(
				'status'    => 'idle',
				'cursor'    => 0,
				'total'     => 0,
				'processed' => 0,
				'updated'   => 0,
				'writes'    => 0,
				'conflicts' => 0,
				'started'   => 0,
				'finished'  => 0,
			)
		);
	}

	/**
	 * Saves the state.
	 *
	 * @param array $state State.
	 * @return void
	 */
	private function save( array $state ) {
		update_option( self::OPTION, $state, false );
	}

	/**
	 * Total number of users.
	 *
	 * @return int
	 */
	private function total_users() {
		$counts = count_users();
		return isset( $counts['total_users'] ) ? (int) $counts['total_users'] : 0;
	}

	/**
	 * Next user IDs after a cursor.
	 *
	 * @param int $cursor Last processed ID.
	 * @param int $limit  Limit.
	 * @return int[]
	 */
	private function next_ids( $cursor, $limit ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID > %d ORDER BY ID ASC LIMIT %d", (int) $cursor, (int) $limit ) ) );
	}

	/**
	 * Plans (or performs) the sync for one user.
	 *
	 * @param int  $user_id User.
	 * @param bool $dry     Dry-run.
	 * @return array{phone:string,writes:array,conflicts:int}
	 */
	public function process_user( $user_id, $dry ) {
		$phone = $this->mapper->read_phone( $user_id );
		$out   = array(
			'phone'     => $phone,
			'writes'    => array(),
			'conflicts' => 0,
		);
		if ( '' === $phone ) {
			return $out;
		}
		foreach ( $this->mapper->write_phone( $user_id, $phone, $dry, false ) as $row ) {
			if ( 'write' === $row['action'] ) {
				$out['writes'][] = $row;
			} elseif ( in_array( $row['action'], array( 'conflict', 'duplicate' ), true ) ) {
				++$out['conflicts'];
			}
		}
		return $out;
	}

	/**
	 * Preview on the first N users.
	 *
	 * @param int $limit Users to scan.
	 * @return array
	 */
	public function preview( $limit = 200 ) {
		$ids    = $this->next_ids( 0, max( 1, min( 1000, (int) $limit ) ) );
		$report = array(
			'scanned'    => count( $ids ),
			'total'      => $this->total_users(),
			'with_phone' => 0,
			'to_update'  => 0,
			'writes'     => 0,
			'conflicts'  => 0,
			'sample'     => array(),
		);
		foreach ( $ids as $user_id ) {
			$plan = $this->process_user( $user_id, true );
			if ( '' === $plan['phone'] ) {
				continue;
			}
			++$report['with_phone'];
			$report['conflicts'] += $plan['conflicts'];
			if ( $plan['writes'] ) {
				++$report['to_update'];
				$report['writes'] += count( $plan['writes'] );
				if ( count( $report['sample'] ) < 20 ) {
					$user               = get_userdata( $user_id );
					$report['sample'][] = array(
						'user_id' => $user_id,
						'login'   => $user ? $user->user_login : '',
						'phone'   => MW24_Eitaa_Phone_Normalizer::mask( $plan['phone'] ),
						'writes'  => array_map(
							static function ( $row ) {
								return $row['label'] . ' = ' . $row['value'];
							},
							$plan['writes']
						),
					);
				}
			}
		}
		return $report;
	}

	/**
	 * Starts a background run.
	 *
	 * @return array
	 */
	public function start() {
		$state = array(
			'status'    => 'running',
			'cursor'    => 0,
			'total'     => $this->total_users(),
			'processed' => 0,
			'updated'   => 0,
			'writes'    => 0,
			'conflicts' => 0,
			'started'   => time(),
			'finished'  => 0,
		);
		$this->save( $state );
		$this->schedule();
		$this->logger->info( 'backfill.start', 'Phone sync of existing users started.' );
		return $state;
	}

	/**
	 * Cancels a run.
	 *
	 * @return array
	 */
	public function cancel() {
		$state           = $this->status();
		$state['status'] = 'cancelled';
		$this->save( $state );
		wp_clear_scheduled_hook( self::HOOK );
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::HOOK, array(), MW24_Eitaa_Queue::GROUP );
		}
		return $state;
	}

	/**
	 * Schedules the next batch.
	 *
	 * @return void
	 */
	private function schedule() {
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array(), MW24_Eitaa_Queue::GROUP );
			return;
		}
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::HOOK );
		}
	}

	/**
	 * Processes one batch (cron/Action Scheduler or "run now" button).
	 *
	 * @return array State.
	 */
	public function run_batch() {
		$state = $this->status();
		if ( 'running' !== $state['status'] ) {
			return $state;
		}
		$ids = $this->next_ids( (int) $state['cursor'], self::BATCH );
		foreach ( $ids as $user_id ) {
			$result = $this->process_user( $user_id, false );
			++$state['processed'];
			$state['cursor']     = $user_id;
			$state['conflicts'] += $result['conflicts'];
			if ( $result['writes'] ) {
				++$state['updated'];
				$state['writes'] += count( $result['writes'] );
			}
		}
		if ( count( $ids ) < self::BATCH ) {
			$state['status']   = 'done';
			$state['finished'] = time();
			$this->logger->info( 'backfill.done', sprintf( 'Phone sync finished: %d users updated, %d values written, %d conflicts.', $state['updated'], $state['writes'], $state['conflicts'] ) );
		}
		$this->save( $state );
		if ( 'running' === $state['status'] ) {
			$this->schedule();
		}
		return $state;
	}
}
