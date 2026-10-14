<?php
/**
 * Login settings and the three-mode phone sync wizard.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Login & phone sync page.
 */
class MW24_Eitaa_Admin_Page_Login extends MW24_Eitaa_Admin_Page {

	/**
	 * Title.
	 *
	 * @return string
	 */
	public function title() {
		return __( 'Login & phone sync', 'mw24-eitaa' );
	}

	/**
	 * Sections.
	 *
	 * @return string[]
	 */
	protected function sections() {
		return array( 'login', 'mapping' );
	}

	/**
	 * Saves login settings or the phone map.
	 *
	 * @param string $section Section.
	 * @param array  $input   Input.
	 * @return true|WP_Error
	 */
	public function save( $section, array $input ) {
		if ( 'mapping' !== $section ) {
			return parent::save( $section, $input );
		}
		$config = $this->core->mapper->sanitize_config( $input );
		if ( is_wp_error( $config ) ) {
			return $config;
		}
		$this->core->mapper->save_config( $config );
		$this->settings->set_state( 'mapping_confirmed', true );
		$this->core->logger->info( 'settings.saved', 'Phone sync map saved.', array( 'user_id' => get_current_user_id() ) );
		return true;
	}

	/**
	 * Body.
	 *
	 * @return void
	 */
	protected function content() {
		$tab = $this->current_tab( 'login', array( 'login', 'sync' ) );
		$this->tabs(
			array(
				'login' => __( 'Login & registration', 'mw24-eitaa' ),
				'sync'  => __( 'Phone sync', 'mw24-eitaa' ),
			),
			$tab
		);
		if ( 'sync' === $tab ) {
			$this->sync_tab();
		} else {
			$this->login_tab();
		}
	}

	/**
	 * Login settings.
	 *
	 * @return void
	 */
	private function login_tab() {
		$this->form_open( 'login', 'login' );

		$this->card_open( __( 'Automatic login', 'mw24-eitaa' ), __( 'Users are identified by the mobile number that Eitaa signs and verifies — no password or SMS code is needed.', 'mw24-eitaa' ) );
		$this->table_open();
		$this->field( 'login_enabled', __( 'Login with Eitaa', 'mw24-eitaa' ), '', array( 'label' => __( 'Log users in automatically inside the mini-app', 'mw24-eitaa' ) ) );
		$this->field( 'allow_registration', __( 'Registration', 'mw24-eitaa' ), __( 'Create an account when no user owns the shared number. Username: 98XXXXXXXXXX, e-mail: 98XXXXXXXXXX@eitaa.invalid (placeholder).', 'mw24-eitaa' ), array( 'label' => __( 'Create new accounts automatically', 'mw24-eitaa' ) ) );
		$this->field( 'default_role', __( 'Role of new users', 'mw24-eitaa' ), __( 'Only roles without editing or administration capabilities can be selected.', 'mw24-eitaa' ) );
		$this->field( 'sync_name', __( 'Names', 'mw24-eitaa' ), '', array( 'label' => __( 'Fill empty first/last names from Eitaa', 'mw24-eitaa' ) ) );
		$this->field( 'trusted_device', __( 'Trusted device', 'mw24-eitaa' ), __( 'Keeps the user logged in inside Eitaa with a long “remember me” cookie.', 'mw24-eitaa' ), array( 'label' => __( 'Remember the login', 'mw24-eitaa' ) ) );
		$this->field(
			'trusted_days',
			__( 'Remember for', 'mw24-eitaa' ),
			'',
			array(
				'suffix'  => __( 'days', 'mw24-eitaa' ),
				'show_if' => 'trusted_device=1',
			)
		);
		$this->field( 'after_login_url', __( 'After login go to', 'mw24-eitaa' ), __( 'Empty = stay on the current page.', 'mw24-eitaa' ) );
		$this->field( 'request_write_access', __( 'Message permission', 'mw24-eitaa' ), __( 'After login, ask users who have not allowed messages yet to allow them (needed for notifications).', 'mw24-eitaa' ), array( 'label' => __( 'Ask for permission to send messages', 'mw24-eitaa' ) ) );
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'Asking for the phone number', 'mw24-eitaa' ) );
		$this->table_open();
		$this->field(
			'prompt_mode',
			__( 'When to ask', 'mw24-eitaa' ),
			__( '“Later” hides the bar until the mini-app is opened again.', 'mw24-eitaa' ),
			array(
				'options' => array(
					'immediate'     => __( 'Immediately on any page', 'mw24-eitaa' ),
					'cart_checkout' => __( 'Only on cart and checkout pages', 'mw24-eitaa' ),
					'button'        => __( 'Only when the login button is clicked', 'mw24-eitaa' ),
				),
			)
		);
		$this->field( 'prompt_direct', __( 'Native dialog', 'mw24-eitaa' ), __( 'Skip the bottom bar and open Eitaa\'s own phone sharing dialog directly.', 'mw24-eitaa' ), array( 'label' => __( 'Open the Eitaa dialog without the bar', 'mw24-eitaa' ) ) );
		$this->field( 'bar_text', __( 'Bar text', 'mw24-eitaa' ) );
		$this->field( 'share_label', __( '“Share” button', 'mw24-eitaa' ) );
		$this->field( 'later_label', __( '“Later” button', 'mw24-eitaa' ) );
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'Login button', 'mw24-eitaa' ), __( 'Use the shortcode <code>[mw24_eitaa_login]</code> or the “Eitaa login button” block anywhere. Attributes: <code>label</code>, <code>class</code>, <code>redirect</code>, <code>show_linked="yes"</code>.', 'mw24-eitaa' ) );
		$this->table_open();
		$this->field( 'button_label', __( 'Button text', 'mw24-eitaa' ) );
		$this->field( 'button_wp_login', __( 'WordPress login page', 'mw24-eitaa' ), '', array( 'label' => __( 'Add the button to wp-login.php', 'mw24-eitaa' ) ) );
		if ( $this->core->woo->is_active() ) {
			$this->field( 'button_woo_login', __( 'WooCommerce login form', 'mw24-eitaa' ), '', array( 'label' => __( 'Add the button to the My account login form', 'mw24-eitaa' ) ) );
		}
		$this->field(
			'outside_behavior',
			__( 'Outside Eitaa', 'mw24-eitaa' ),
			__( 'Eitaa login only works inside the mini-app.', 'mw24-eitaa' ),
			array(
				'options' => array(
					'hint' => __( 'Show a hint instead of the button', 'mw24-eitaa' ),
					'hide' => __( 'Hide the button', 'mw24-eitaa' ),
				),
			)
		);
		$this->field( 'outside_hint', __( 'Hint text', 'mw24-eitaa' ), '', array( 'show_if' => 'outside_behavior=hint' ) );
		$this->field( 'miniapp_link', __( 'Mini-app link', 'mw24-eitaa' ), __( 'Optional direct link to your mini-app in Eitaa (from Eitaayar). Shown under the hint.', 'mw24-eitaa' ), array( 'show_if' => 'outside_behavior=hint' ) );
		$this->table_close();
		$this->card_close();

		$this->card_open( __( 'Privileged accounts', 'mw24-eitaa' ), '', 'mw24-card--danger' );
		$this->table_open();
		$this->field( 'allow_privileged', __( 'Administrators and editors', 'mw24-eitaa' ), __( '<strong>Not recommended.</strong> By default accounts that can edit posts or manage options can never log in through Eitaa. Enable only if you understand the risk: anyone with access to the linked Eitaa account could enter your admin area.', 'mw24-eitaa' ), array( 'label' => __( 'Allow privileged accounts to log in with Eitaa', 'mw24-eitaa' ) ) );
		$this->table_close();
		$this->card_close();

		$this->form_close();
	}

	/**
	 * Phone sync wizard.
	 *
	 * @return void
	 */
	private function sync_tab() {
		$mapper   = $this->core->mapper;
		$config   = $mapper->get_config();
		$detected = MW24_Eitaa_Mapping_Profiles::detected();
		$formats  = MW24_Eitaa_Phone_Normalizer::format_labels();

		echo '<div class="mw24-intro">' . esc_html__( 'Tell the plugin where other login/registration plugins keep the mobile number. When a user logs in with Eitaa, every place below is searched (in five number formats) and the verified number is written back to all of them, so Digits, WooCommerce and others recognize the same user.', 'mw24-eitaa' ) . '</div>';

		$this->form_open( 'mapping', 'sync' );

		// Mode 1: profiles.
		$this->card_open( __( '1. Ready-made profiles', 'mw24-eitaa' ), __( 'Detected plugins are enabled automatically; you only need to confirm. Undetected profiles can be enabled manually.', 'mw24-eitaa' ) );
		echo '<table class="widefat striped mw24-profiles"><thead><tr><th>' . esc_html__( 'Active', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Plugin', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Status', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Fields', 'mw24-eitaa' ) . '</th></tr></thead><tbody>';
		foreach ( MW24_Eitaa_Mapping_Profiles::all() as $id => $profile ) {
			$is_detected = in_array( $id, $detected, true );
			$active      = $mapper->profile_active( $id );
			echo '<tr class="' . ( $is_detected ? 'is-detected' : '' ) . '">';
			printf( '<td><input type="checkbox" name="mw24[profiles][%1$s][active]" value="1" %2$s aria-label="%3$s"></td>', esc_attr( $id ), checked( $active, true, false ), esc_attr( $profile['name'] ) );
			echo '<td><strong>' . esc_html( $profile['name'] ) . '</strong><br><span class="mw24-muted">' . esc_html( MW24_Eitaa_Mapping_Profiles::notes( $profile ) ) . '</span></td><td>';
			if ( $is_detected ) {
				/* translators: %s: plugin name. */
				echo wp_kses_post( $this->badge( sprintf( __( 'Auto-detected: %s', 'mw24-eitaa' ), $profile['name'] ), 'ok' ) ) . ' ';
				echo wp_kses_post( $mapper->profile_confirmed( $id ) ? $this->badge( __( 'Confirmed', 'mw24-eitaa' ), 'info' ) : $this->badge( __( 'Needs confirmation', 'mw24-eitaa' ), 'warn' ) );
			} else {
				echo wp_kses_post( $this->badge( __( 'Not detected', 'mw24-eitaa' ), 'muted' ) );
			}
			if ( ! $profile['verified'] ) {
				echo ' ' . wp_kses_post( $this->badge( __( 'Template — verify the field', 'mw24-eitaa' ), 'warn' ) );
			}
			echo '</td><td>';
			if ( $profile['fields'] ) {
				echo '<ul class="mw24-field-list">';
				foreach ( $profile['fields'] as $field ) {
					$key = '' !== $field['meta_key'] ? $field['meta_key'] : $field['meta_key_like'];
					if ( null !== $field['value'] ) {
						/* translators: 1: meta key, 2: fixed value. */
						$text = sprintf( __( '%1$s = %2$s (fixed)', 'mw24-eitaa' ), $key, $field['value'] );
					} else {
						$flags = array();
						if ( $field['lookup'] ) {
							$flags[] = __( 'search', 'mw24-eitaa' );
						}
						if ( $field['write'] ) {
							$flags[] = __( 'write', 'mw24-eitaa' );
						}
						$text = $key . ' → ' . MW24_Eitaa_Phone_Normalizer::format( '09121234567', $field['format'], $field['pattern'] ) . ' (' . implode( ' + ', $flags ) . ')';
					}
					echo '<li><code dir="ltr">' . esc_html( $text ) . '</code></li>';
				}
				echo '</ul>';
			} else {
				echo '<p class="mw24-muted">' . esc_html__( 'No fixed field. Load the existing meta keys below and add the right one to the manual map.', 'mw24-eitaa' ) . '</p>';
			}
			if ( $profile['suggest_like'] ) {
				echo '<p class="mw24-muted">' . esc_html__( 'Suggested patterns:', 'mw24-eitaa' ) . ' <code dir="ltr">' . esc_html( implode( ', ', $profile['suggest_like'] ) ) . '</code></p>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . wp_kses( __( 'Developers can add or update profiles with JSON files in <code>profiles/</code> or the <code>mw24_eitaa_phone_profiles</code> filter.', 'mw24-eitaa' ), MW24_Eitaa_Admin_Fields::allowed_html() ) . '</p>';
		$this->card_close();

		// Mode 2: usermeta.
		$this->card_open( __( '2. Manual map on user meta', 'mw24-eitaa' ), __( 'Pick one or more meta keys that really exist in your database and choose the format in which each one stores the number.', 'mw24-eitaa' ) );
		echo '<div class="mw24-repeater" data-mw24-repeater>';
		echo '<table class="widefat mw24-repeater__table"><thead><tr><th>' . esc_html__( 'Meta key', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Format', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Custom pattern', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Write', 'mw24-eitaa' ) . '</th><th></th></tr></thead><tbody data-mw24-rows>';
		foreach ( $config['meta'] as $index => $row ) {
			$this->meta_row( (string) $index, $row, $formats );
		}
		echo '</tbody></table><template data-mw24-template>';
		$this->meta_row(
			'__i__',
			array(
				'key'     => '',
				'format'  => '0',
				'pattern' => '',
				'write'   => true,
			),
			$formats
		);
		echo '</template><p><button type="button" class="button" data-mw24-add>' . esc_html__( '+ Add meta key', 'mw24-eitaa' ) . '</button></p></div>';
		echo '<p class="description">' . wp_kses( __( 'Custom pattern placeholders: <code>{cc}</code> = 98, <code>{national}</code> = 912XXXXXXX, <code>{local}</code> = 0912XXXXXXX. Example: <code>+{cc}-{national}</code>.', 'mw24-eitaa' ), MW24_Eitaa_Admin_Fields::allowed_html() ) . '</p>';

		echo '<h3>' . esc_html__( 'Helper fields with a fixed value', 'mw24-eitaa' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Written together with the number, e.g. a country code field (+98) or a “verified” flag (1).', 'mw24-eitaa' ) . '</p>';
		echo '<div class="mw24-repeater" data-mw24-repeater>';
		echo '<table class="widefat mw24-repeater__table"><thead><tr><th>' . esc_html__( 'Meta key', 'mw24-eitaa' ) . '</th><th>' . esc_html__( 'Fixed value', 'mw24-eitaa' ) . '</th><th></th></tr></thead><tbody data-mw24-rows>';
		foreach ( $config['extras'] as $index => $row ) {
			$this->extra_row( (string) $index, $row );
		}
		echo '</tbody></table><template data-mw24-template>';
		$this->extra_row(
			'__i__',
			array(
				'key'   => '',
				'value' => '',
			)
		);
		echo '</template><p><button type="button" class="button" data-mw24-add>' . esc_html__( '+ Add helper field', 'mw24-eitaa' ) . '</button></p></div>';

		echo '<h3>' . esc_html__( 'Meta keys found in your database', 'mw24-eitaa' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Read-only list of user meta keys with the number of rows, the share of phone-like values and a masked sample. Keys that look like phone numbers are listed first. The list is cached for 12 hours.', 'mw24-eitaa' ) . '</p>';
		echo '<p><button type="button" class="button" data-mw24-load-keys="0">' . esc_html__( 'Show meta keys', 'mw24-eitaa' ) . '</button> <button type="button" class="button-link" data-mw24-load-keys="1">' . esc_html__( 'Refresh list', 'mw24-eitaa' ) . '</button></p>';
		echo '<div id="mw24-meta-keys" class="mw24-scroll"></div><datalist id="mw24-meta-key-list"></datalist>';
		$this->card_close();

		// Mode 3: custom table.
		$table = $config['table'];
		$this->card_open( __( '3. Map on a custom table', 'mw24-eitaa' ), __( 'For plugins that keep phone numbers in their own table. Only existing tables/columns can be chosen (read with SHOW TABLES / DESCRIBE).', 'mw24-eitaa' ) );
		echo '<div class="mw24-table-map" data-mw24-table-map data-saved="' . esc_attr( (string) wp_json_encode( $table ) ) . '">';
		echo '<table class="form-table mw24-form-table" role="presentation"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Enable', 'mw24-eitaa' ) . '</th><td><label><input type="checkbox" name="mw24[table][enabled]" value="1" ' . checked( ! empty( $table['enabled'] ), true, false ) . '> ' . esc_html__( 'Use a custom table', 'mw24-eitaa' ) . '</label></td></tr>';
		echo '<tr><th scope="row"><label for="mw24-table">' . esc_html__( 'Table', 'mw24-eitaa' ) . '</label></th><td><select id="mw24-table" name="mw24[table][table]" data-mw24-table-select>';
		echo '<option value="">' . esc_html__( '— Select a table —', 'mw24-eitaa' ) . '</option>';
		if ( '' !== $table['table'] ) {
			echo '<option value="' . esc_attr( $table['table'] ) . '" selected>' . esc_html( $table['table'] ) . '</option>';
		}
		echo '</select> <button type="button" class="button" data-mw24-load-tables>' . esc_html__( 'Load tables', 'mw24-eitaa' ) . '</button></td></tr>';
		foreach ( array(
			'phone_col' => __( 'Phone column', 'mw24-eitaa' ),
			'user_col'  => __( 'User ID column (wp_users.ID)', 'mw24-eitaa' ),
		) as $col_key => $col_label ) {
			echo '<tr><th scope="row">' . esc_html( $col_label ) . '</th><td><select name="mw24[table][' . esc_attr( $col_key ) . ']" data-mw24-column="' . esc_attr( $col_key ) . '">';
			echo '<option value="">' . esc_html__( '— Select a column —', 'mw24-eitaa' ) . '</option>';
			if ( '' !== $table[ $col_key ] ) {
				echo '<option value="' . esc_attr( $table[ $col_key ] ) . '" selected>' . esc_html( $table[ $col_key ] ) . '</option>';
			}
			echo '</select></td></tr>';
		}
		echo '<tr><th scope="row">' . esc_html__( 'Format', 'mw24-eitaa' ) . '</th><td><select name="mw24[table][format]">';
		foreach ( $formats as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( $table['format'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select> <input type="text" class="regular-text code" dir="ltr" name="mw24[table][pattern]" value="' . esc_attr( $table['pattern'] ) . '" placeholder="+{cc}{national}"></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Mode', 'mw24-eitaa' ) . '</th><td><label><input type="checkbox" name="mw24[table][read_only]" value="1" ' . checked( ! empty( $table['read_only'] ), true, false ) . '> ' . esc_html__( 'Read only (search users but never insert/update rows)', 'mw24-eitaa' ) . '</label></td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Other columns on insert', 'mw24-eitaa' ) . '</th><td><div data-mw24-table-extras><p class="mw24-muted">' . esc_html__( 'Select a table to see its columns. Required columns (NOT NULL without default) need a value.', 'mw24-eitaa' ) . '</p></div></td></tr>';
		echo '</tbody></table></div>';
		$this->card_close();

		$this->card_open( __( 'Matching options', 'mw24-eitaa' ) );
		printf(
			'<label><input type="checkbox" name="mw24[match_login_email]" value="1" %1$s> %2$s</label><p class="description">%3$s</p>',
			checked( ! empty( $config['match_login_email'] ), true, false ),
			esc_html__( 'Also match the number against usernames and the local part of e-mail addresses', 'mw24-eitaa' ),
			esc_html__( 'Useful when a phone-login plugin used the number as username. Disable it if visitors can freely choose usernames on your site.', 'mw24-eitaa' )
		);
		$this->card_close();

		$this->form_close( __( 'Save and confirm phone sync', 'mw24-eitaa' ) );

		$this->card_open( __( 'Test the map (dry-run)', 'mw24-eitaa' ), __( 'Enter a mobile number to see which user would log in and which values would be written. Nothing is changed.', 'mw24-eitaa' ) );
		echo '<div class="mw24-inline-tool" data-mw24-dry-run><input type="text" class="regular-text code" dir="ltr" placeholder="09121234567" data-mw24-input> <button type="button" class="button button-secondary" data-mw24-run>' . esc_html__( 'Test', 'mw24-eitaa' ) . '</button><div class="mw24-result" data-mw24-result></div></div>';
		$this->card_close();
	}

	/**
	 * One manual meta row.
	 *
	 * @param string $index   Row index.
	 * @param array  $row     Row.
	 * @param array  $formats Format labels.
	 * @return void
	 */
	private function meta_row( $index, array $row, array $formats ) {
		$base = 'mw24[meta][' . $index . ']';
		echo '<tr>';
		printf( '<td><input type="text" class="regular-text code" dir="ltr" list="mw24-meta-key-list" name="%1$s[key]" value="%2$s" placeholder="billing_phone"></td>', esc_attr( $base ), esc_attr( $row['key'] ) );
		echo '<td><select name="' . esc_attr( $base ) . '[format]">';
		foreach ( $formats as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '" ' . selected( (string) $row['format'], (string) $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></td>';
		printf( '<td><input type="text" class="code" dir="ltr" name="%1$s[pattern]" value="%2$s" placeholder="+{cc}{national}"></td>', esc_attr( $base ), esc_attr( $row['pattern'] ) );
		printf( '<td><input type="checkbox" name="%1$s[write]" value="1" %2$s aria-label="%3$s"></td>', esc_attr( $base ), checked( ! empty( $row['write'] ), true, false ), esc_attr__( 'Write', 'mw24-eitaa' ) );
		echo '<td><button type="button" class="button-link mw24-remove" data-mw24-remove aria-label="' . esc_attr__( 'Remove', 'mw24-eitaa' ) . '">&times;</button></td>';
		echo '</tr>';
	}

	/**
	 * One helper field row.
	 *
	 * @param string $index Index.
	 * @param array  $row   Row.
	 * @return void
	 */
	private function extra_row( $index, array $row ) {
		$base = 'mw24[extras][' . $index . ']';
		echo '<tr>';
		printf( '<td><input type="text" class="regular-text code" dir="ltr" list="mw24-meta-key-list" name="%1$s[key]" value="%2$s" placeholder="digt_countrycode"></td>', esc_attr( $base ), esc_attr( $row['key'] ) );
		printf( '<td><input type="text" class="code" dir="ltr" name="%1$s[value]" value="%2$s" placeholder="+98"></td>', esc_attr( $base ), esc_attr( $row['value'] ) );
		echo '<td><button type="button" class="button-link mw24-remove" data-mw24-remove aria-label="' . esc_attr__( 'Remove', 'mw24-eitaa' ) . '">&times;</button></td>';
		echo '</tr>';
	}
}
