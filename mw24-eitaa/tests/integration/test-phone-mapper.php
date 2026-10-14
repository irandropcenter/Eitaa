<?php
/**
 * Phone map engine tests (lookup, write, conflicts, dry-run, custom table).
 *
 * @package MW24_Eitaa
 */

/**
 * @covers MW24_Eitaa_Phone_Mapper
 * @covers MW24_Eitaa_Mapping_Meta
 * @covers MW24_Eitaa_Mapping_Table
 * @covers MW24_Eitaa_Mapping_Profiles
 */
class Test_MW24_Eitaa_Phone_Mapper extends WP_UnitTestCase {

	/** @var MW24_Eitaa_Phone_Mapper */
	private $mapper;

	public function set_up() {
		parent::set_up();
		$this->mapper = mw24_eitaa()->mapper;
		$this->set_config( array() );
	}

	public function tear_down() {
		delete_option( MW24_Eitaa_Phone_Mapper::OPTION );
		$this->mapper->flush();
		MW24_Eitaa_Mapping_Profiles::flush();
		parent::tear_down();
	}

	/**
	 * Saves a config merged into the defaults (profiles disabled unless given).
	 *
	 * @param array $config Config.
	 */
	private function set_config( array $config ) {
		$profiles = array();
		foreach ( MW24_Eitaa_Mapping_Profiles::all() as $id => $profile ) {
			$profiles[ $id ] = array(
				'active'    => false,
				'confirmed' => true,
			);
		}
		$this->mapper->save_config( array_merge( MW24_Eitaa_Phone_Mapper::default_config(), array( 'profiles' => $profiles ), $config ) );
	}

	/**
	 * Manual meta row.
	 *
	 * @param string $key    Key.
	 * @param string $format Format.
	 * @param bool   $write  Write.
	 * @return array
	 */
	private function meta( $key, $format = '0', $write = true ) {
		return array(
			'key'     => $key,
			'format'  => $format,
			'pattern' => '',
			'write'   => $write,
		);
	}

	public function test_finds_user_by_any_variant_and_separator() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'billing_phone', '0912 765-4321' );
		$this->set_config( array( 'meta' => array( $this->meta( 'billing_phone' ) ) ) );

		foreach ( array( '09127654321', '+989127654321', '989127654321', '9127654321', '۰۹۱۲۷۶۵۴۳۲۱' ) as $input ) {
			$found = $this->mapper->find_user( $input );
			$this->assertSame( $user_id, $found['user_id'], $input );
		}
	}

	public function test_profile_fields_are_searched_when_active() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'digits_phone', '+989127654321' );
		$this->assertSame( 0, $this->mapper->find_user( '09127654321' )['user_id'] );

		$this->set_config(
			array(
				'profiles' => array(
					'digits' => array(
						'active'    => true,
						'confirmed' => true,
					),
				),
			)
		);
		$this->assertSame( $user_id, $this->mapper->find_user( '09127654321' )['user_id'] );
	}

	public function test_detected_profiles_are_active_by_default() {
		delete_option( MW24_Eitaa_Phone_Mapper::OPTION );
		$this->mapper->flush();
		$callback = static function ( $profiles ) {
			$profiles['test-plugin'] = array(
				'id'     => 'test-plugin',
				'name'   => 'Test plugin',
				'detect' => array( 'functions' => array( 'mw24_eitaa_send' ) ),
				'fields' => array(
					array(
						'meta_key' => 'test_mobile',
						'format'   => '98',
					),
				),
			);
			return $profiles;
		};
		add_filter( 'mw24_eitaa_phone_profiles', $callback );
		MW24_Eitaa_Mapping_Profiles::flush();

		$this->assertContains( 'test-plugin', MW24_Eitaa_Mapping_Profiles::detected() );
		$this->assertTrue( $this->mapper->profile_active( 'test-plugin' ) );
		$this->assertContains( 'test-plugin', $this->mapper->unconfirmed_profiles() );

		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'test_mobile', '989127654321' );
		$this->assertSame( $user_id, $this->mapper->find_user( '09127654321' )['user_id'] );
		remove_filter( 'mw24_eitaa_phone_profiles', $callback );
	}

	public function test_conflict_is_reported_and_priority_respected() {
		$first  = self::factory()->user->create();
		$second = self::factory()->user->create();
		update_user_meta( $second, 'billing_phone', '09127654321' );
		update_user_meta( $first, 'alt_phone', '+989127654321' );
		$this->set_config( array( 'meta' => array( $this->meta( 'billing_phone' ), $this->meta( 'alt_phone', '+98' ) ) ) );

		$found = $this->mapper->find_user( '09127654321' );
		$this->assertTrue( $found['conflict'] );
		$this->assertSame( $second, $found['user_id'], 'The first map in priority order wins.' );
	}

	public function test_internal_meta_has_highest_priority() {
		$a = self::factory()->user->create();
		$b = self::factory()->user->create();
		update_user_meta( $a, 'billing_phone', '09127654321' );
		update_user_meta( $b, MW24_Eitaa_Phone_Mapper::INTERNAL_KEY, '09127654321' );
		$this->set_config( array( 'meta' => array( $this->meta( 'billing_phone' ) ) ) );
		$this->assertSame( $b, $this->mapper->find_user( '09127654321' )['user_id'] );
	}

	public function test_exclude_user() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'billing_phone', '09127654321' );
		$this->set_config( array( 'meta' => array( $this->meta( 'billing_phone' ) ) ) );
		$this->assertSame( 0, $this->mapper->find_user( '09127654321', $user_id )['user_id'] );
	}

	public function test_login_and_email_matching_can_be_disabled() {
		$by_login = self::factory()->user->create( array( 'user_login' => '989127654321' ) );
		$this->assertSame( $by_login, $this->mapper->find_user( '09127654321' )['user_id'] );

		$this->set_config( array( 'match_login_email' => false ) );
		$this->assertSame( 0, $this->mapper->find_user( '09127654321' )['user_id'] );
	}

	public function test_email_local_part_matching() {
		$user_id = self::factory()->user->create( array( 'user_email' => '09127654321@example.org' ) );
		$this->assertSame( $user_id, $this->mapper->find_user( '9127654321' )['user_id'] );
	}

	public function test_write_phone_writes_every_writable_map_in_its_format() {
		$user_id = self::factory()->user->create();
		$this->set_config(
			array(
				'meta'   => array( $this->meta( 'billing_phone' ), $this->meta( 'intl_phone', '+98' ), $this->meta( 'readonly_phone', '9', false ) ),
				'extras' => array(
					array(
						'key'   => 'country_code',
						'value' => '+98',
					),
				),
			)
		);
		$this->mapper->write_phone( $user_id, '۰۹۱۲۷۶۵۴۳۲۱' );
		$this->assertSame( '09127654321', get_user_meta( $user_id, MW24_Eitaa_Phone_Mapper::INTERNAL_KEY, true ) );
		$this->assertSame( '09127654321', get_user_meta( $user_id, 'billing_phone', true ) );
		$this->assertSame( '+989127654321', get_user_meta( $user_id, 'intl_phone', true ) );
		$this->assertSame( '+98', get_user_meta( $user_id, 'country_code', true ) );
		$this->assertSame( '', get_user_meta( $user_id, 'readonly_phone', true ) );
	}

	public function test_write_phone_never_duplicates_a_number_owned_by_another_user() {
		$owner = self::factory()->user->create();
		$other = self::factory()->user->create();
		update_user_meta( $owner, 'billing_phone', '09127654321' );
		$this->set_config( array( 'meta' => array( $this->meta( 'billing_phone' ) ) ) );

		$report  = $this->mapper->write_phone( $other, '09127654321' );
		$actions = wp_list_pluck( $report, 'action', 'map' );
		$this->assertSame( 'duplicate', $actions['manual:0'] );
		$this->assertSame( '', get_user_meta( $other, 'billing_phone', true ) );
	}

	public function test_without_overwrite_a_different_number_is_kept() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'billing_phone', '09350000000' );
		$this->set_config( array( 'meta' => array( $this->meta( 'billing_phone' ) ) ) );

		$report = $this->mapper->write_phone( $user_id, '09127654321', false, false );
		$this->assertSame( 'conflict', wp_list_pluck( $report, 'action', 'map' )['manual:0'] );
		$this->assertSame( '09350000000', get_user_meta( $user_id, 'billing_phone', true ) );
	}

	public function test_dry_run_changes_nothing() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'billing_phone', '09127654321' );
		$this->set_config( array( 'meta' => array( $this->meta( 'billing_phone' ), $this->meta( 'intl_phone', '+98' ) ) ) );

		$result = $this->mapper->dry_run( '+98 912 765 4321' );
		$this->assertSame( '09127654321', $result['normalized'] );
		$this->assertSame( $user_id, $result['user']['id'] );
		$this->assertCount( 5, $result['variants'] );
		$writes = wp_list_pluck( $result['writes'], 'value', 'map' );
		$this->assertSame( '+989127654321', $writes['manual:1'] );
		$this->assertSame( '', get_user_meta( $user_id, 'intl_phone', true ), 'Dry-run must not write.' );
		$this->assertWPError( $this->mapper->dry_run( '12345' ) );
	}

	public function test_custom_table_lookup_and_write() {
		global $wpdb;
		$table   = $wpdb->prefix . 'mw24_test_phone_book';
		$columns = MW24_Eitaa_Mapping_Table::describe( $table );
		$this->assertIsArray( $columns );
		$required = wp_list_pluck( wp_list_filter( $columns, array( 'required' => true ) ), 'name' );
		$this->assertContains( 'source', $required );

		$config = array(
			'enabled'   => true,
			'table'     => $table,
			'phone_col' => 'mobile',
			'user_col'  => 'uid',
			'format'    => '98',
			'pattern'   => '',
			'read_only' => false,
			'extras'    => array(
				array(
					'col'   => 'source',
					'type'  => 'fixed',
					'value' => 'eitaa',
				),
				array(
					'col'   => 'created',
					'type'  => 'now',
					'value' => '',
				),
			),
		);
		$this->assertIsArray( $this->mapper->sanitize_config( array( 'table' => $config ) ) );
		$this->set_config( array( 'table' => $config ) );

		$existing = self::factory()->user->create();
		$wpdb->insert(
			$table,
			array(
				'uid'     => $existing,
				'mobile'  => '0912-111-2233',
				'source'  => 'old',
				'created' => current_time( 'mysql' ),
			)
		);
		$this->assertSame( $existing, $this->mapper->find_user( '+989121112233' )['user_id'] );

		$new_user = self::factory()->user->create();
		$this->mapper->write_phone( $new_user, '09124445566' );
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE uid = %d", $new_user ), ARRAY_A ); // phpcs:ignore WordPress.DB
		$this->assertSame( '989124445566', $row['mobile'] );
		$this->assertSame( 'eitaa', $row['source'] );
	}

	public function test_custom_table_read_only_is_respected() {
		global $wpdb;
		$table = $wpdb->prefix . 'mw24_test_phone_book';
		$this->set_config(
			array(
				'table' => array(
					'enabled'   => true,
					'table'     => $table,
					'phone_col' => 'mobile',
					'user_col'  => 'uid',
					'format'    => '0',
					'pattern'   => '',
					'read_only' => true,
					'extras'    => array(),
				),
			)
		);
		$user_id = self::factory()->user->create();
		$report  = $this->mapper->write_phone( $user_id, '09127778899' );
		$this->assertSame( 'read_only', wp_list_pluck( $report, 'action', 'map' )['table'] );
		$this->assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE uid = %d", $user_id ) ) ); // phpcs:ignore WordPress.DB
	}

	public function test_invalid_table_configuration_is_rejected() {
		$result = $this->mapper->sanitize_config(
			array(
				'table' => array(
					'enabled'   => '1',
					'table'     => 'wp_users; DROP TABLE wp_users',
					'phone_col' => 'x',
					'user_col'  => 'y',
				),
			)
		);
		$this->assertWPError( $result );
	}

	public function test_meta_key_discovery_lists_real_keys_with_masked_samples() {
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'my_unique_mobile_key', '09127654321' );
		$keys = MW24_Eitaa_Mapping_Meta::discover_keys( true );
		$row  = wp_list_filter( $keys, array( 'key' => 'my_unique_mobile_key' ) );
		$this->assertCount( 1, $row );
		$row = array_shift( $row );
		$this->assertSame( '0912***4321', $row['sample'] );
		$this->assertSame( '0', $row['format'] );
		$this->assertEmpty( wp_list_filter( $keys, array( 'key' => 'session_tokens' ) ) );
	}

	public function test_backfill_copies_numbers_without_overwriting() {
		$this->set_config( array( 'meta' => array( $this->meta( 'billing_phone' ), $this->meta( 'intl_phone', '+98' ) ) ) );
		$user_id = self::factory()->user->create();
		update_user_meta( $user_id, 'billing_phone', '09127654321' );
		$other = self::factory()->user->create();
		update_user_meta( $other, 'billing_phone', '09120000001' );
		update_user_meta( $other, 'intl_phone', '+989350000000' );

		$backfill = mw24_eitaa()->backfill;
		$preview  = $backfill->preview( 100 );
		$this->assertGreaterThanOrEqual( 1, $preview['to_update'] );
		$this->assertSame( '', get_user_meta( $user_id, 'intl_phone', true ), 'Preview must not write.' );

		$backfill->start();
		$state = $backfill->run_batch();
		while ( 'running' === $state['status'] ) {
			$state = $backfill->run_batch();
		}
		$this->assertSame( 'done', $state['status'] );
		$this->assertSame( '+989127654321', get_user_meta( $user_id, 'intl_phone', true ) );
		$this->assertSame( '+989350000000', get_user_meta( $other, 'intl_phone', true ), 'Different numbers are never overwritten.' );
	}
}
