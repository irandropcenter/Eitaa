<?php
/**
 * Settings field renderers.
 *
 * @package MW24_Eitaa
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders form controls from the settings schema. All output is escaped.
 */
class MW24_Eitaa_Admin_Fields {

	/**
	 * Allowed HTML in descriptions.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		return array(
			'code'   => array(),
			'strong' => array(),
			'em'     => array(),
			'br'     => array(),
			'a'      => array(
				'href'   => array(),
				'target' => array(),
				'rel'    => array(),
			),
		);
	}

	/**
	 * Input name.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function name( $key ) {
		return 'mw24[' . $key . ']';
	}

	/**
	 * Input id.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	public static function id( $key ) {
		return 'mw24-' . str_replace( '_', '-', $key );
	}

	/**
	 * Renders a form-table row.
	 *
	 * @param MW24_Eitaa_Settings $settings Settings.
	 * @param string              $key      Setting key.
	 * @param string              $label    Label.
	 * @param string              $desc     Description (limited HTML).
	 * @param array               $args     options, label, rows, placeholder, show_if, disabled, min, max.
	 * @return void
	 */
	public static function row( $settings, $key, $label, $desc = '', array $args = array() ) {
		$schema = MW24_Eitaa_Settings::schema();
		if ( ! isset( $schema[ $key ] ) ) {
			return;
		}
		$show_if = isset( $args['show_if'] ) ? ' data-mw24-show-if="' . esc_attr( $args['show_if'] ) . '"' : '';
		echo '<tr' . $show_if . '><th scope="row"><label for="' . esc_attr( self::id( $key ) ) . '">' . esc_html( $label ) . '</label></th><td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $show_if is escaped above.
		self::control( $key, $schema[ $key ], $settings->get( $key ), $args );
		if ( '' !== $desc ) {
			echo '<p class="description">' . wp_kses( $desc, self::allowed_html() ) . '</p>';
		}
		echo '</td></tr>';
	}

	/**
	 * Renders one control.
	 *
	 * @param string $key   Key.
	 * @param array  $field Schema entry.
	 * @param mixed  $value Current value.
	 * @param array  $args  Arguments.
	 * @return void
	 */
	public static function control( $key, array $field, $value, array $args = array() ) {
		$name     = self::name( $key );
		$id       = self::id( $key );
		$disabled = ! empty( $args['disabled'] ) ? ' disabled' : '';
		$ph       = isset( $args['placeholder'] ) ? ' placeholder="' . esc_attr( $args['placeholder'] ) . '"' : '';

		switch ( $field['type'] ) {
			case 'bool':
				printf(
					'<input type="hidden" name="%1$s" value="0"><label><input type="checkbox" id="%2$s" name="%1$s" value="1" %3$s%4$s> %5$s</label>',
					esc_attr( $name ),
					esc_attr( $id ),
					checked( (bool) $value, true, false ),
					esc_attr( $disabled ),
					esc_html( isset( $args['label'] ) ? $args['label'] : __( 'Enabled', 'mw24-eitaa' ) )
				);
				break;

			case 'int':
				printf(
					'<input type="number" class="small-text" id="%1$s" name="%2$s" value="%3$s" min="%4$s" max="%5$s"%6$s>%7$s',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) (int) $value ),
					esc_attr( isset( $field['min'] ) ? (string) $field['min'] : '' ),
					esc_attr( isset( $field['max'] ) ? (string) $field['max'] : '' ),
					esc_attr( $disabled ),
					isset( $args['suffix'] ) ? ' <span class="mw24-suffix">' . esc_html( $args['suffix'] ) . '</span>' : ''
				);
				break;

			case 'select':
			case 'role':
				$options = isset( $args['options'] ) ? (array) $args['options'] : array();
				if ( 'role' === $field['type'] ) {
					$options = array( '' => __( 'Automatic (customer with WooCommerce, otherwise the site default)', 'mw24-eitaa' ) ) + MW24_Eitaa_Auth::allowed_roles();
				}
				echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . esc_attr( $disabled ) . '>';
				foreach ( $options as $option => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( (string) $option ), selected( (string) $value, (string) $option, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'statuses':
				$options = isset( $args['options'] ) ? (array) $args['options'] : array();
				echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="">';
				echo '<div class="mw24-checkbox-grid">';
				foreach ( $options as $option => $label ) {
					printf(
						'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s> %4$s</label>',
						esc_attr( $name ),
						esc_attr( (string) $option ),
						checked( in_array( (string) $option, (array) $value, true ), true, false ),
						esc_html( $label )
					);
				}
				echo '</div>';
				break;

			case 'color':
				printf(
					'<input type="text" class="mw24-color" id="%1$s" name="%2$s" value="%3$s" data-default-color="" dir="ltr"%4$s>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					esc_attr( $disabled )
				);
				break;

			case 'url':
				printf(
					'<input type="url" class="regular-text code" dir="ltr" id="%1$s" name="%2$s" value="%3$s"%4$s%5$s>',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( (string) $value ),
					$ph, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
					esc_attr( $disabled )
				);
				break;

			case 'textarea':
			case 'template':
			case 'css':
			case 'selectors':
			case 'routes':
			case 'chat_ids':
				$ltr = in_array( $field['type'], array( 'css', 'selectors', 'routes', 'chat_ids' ), true ) ? ' dir="ltr"' : '';
				printf(
					'<textarea class="large-text%1$s" rows="%2$d" id="%3$s" name="%4$s"%5$s%6$s%7$s>%8$s</textarea>',
					$ltr ? ' code' : '',
					isset( $args['rows'] ) ? (int) $args['rows'] : 3,
					esc_attr( $id ),
					esc_attr( $name ),
					$ltr, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute.
					$ph, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
					esc_attr( $disabled ),
					esc_textarea( (string) $value )
				);
				break;

			case 'path':
			case 'meta_key':
			case 'text':
			default:
				$ltr = in_array( $field['type'], array( 'path', 'meta_key' ), true ) || ! empty( $args['ltr'] );
				printf(
					'<input type="text" class="regular-text%1$s" id="%2$s" name="%3$s" value="%4$s"%5$s%6$s%7$s>',
					$ltr ? ' code' : '',
					esc_attr( $id ),
					esc_attr( $name ),
					esc_attr( is_scalar( $value ) ? (string) $value : '' ),
					$ltr ? ' dir="ltr"' : '',
					$ph, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
					esc_attr( $disabled )
				);
		}
	}
}
