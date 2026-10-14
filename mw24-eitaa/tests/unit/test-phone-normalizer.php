<?php
/**
 * Phone normalization tests.
 *
 * @package MW24_Eitaa
 */

/**
 * @covers MW24_Eitaa_Phone_Normalizer
 */
class Test_MW24_Eitaa_Phone_Normalizer extends WP_UnitTestCase {

	/**
	 * Valid inputs.
	 *
	 * @return array
	 */
	public function valid_numbers() {
		return array(
			'local'           => array( '09121234567' ),
			'national'        => array( '9121234567' ),
			'cc'              => array( '989121234567' ),
			'plus'            => array( '+989121234567' ),
			'double zero'     => array( '00989121234567' ),
			'spaces'          => array( '+98 912 123 4567' ),
			'dashes'          => array( '0912-123-4567' ),
			'parentheses'     => array( '(0912) 123 4567' ),
			'persian digits'  => array( '۰۹۱۲۱۲۳۴۵۶۷' ),
			'arabic digits'   => array( '٠٩١٢١٢٣٤٥٦٧' ),
			'persian plus'    => array( '+۹۸۹۱۲۱۲۳۴۵۶۷' ),
			'mixed digits'    => array( '۰9۱2۱23۴5۶7' ),
			'plus with zero'  => array( '+98 0912 123 4567' ),
			'00 with zero'    => array( '0098 0912 123 4567' ),
			'integer value'   => array( 9121234567 ),
		);
	}

	/**
	 * @dataProvider valid_numbers
	 *
	 * @param mixed $input Input.
	 */
	public function test_valid_numbers_normalize_to_canonical_form( $input ) {
		$this->assertSame( '09121234567', MW24_Eitaa_Phone_Normalizer::normalize( $input ) );
	}

	/**
	 * Invalid inputs.
	 *
	 * @return array
	 */
	public function invalid_numbers() {
		return array(
			'empty'      => array( '' ),
			'short'      => array( '12345' ),
			'landline'   => array( '02112345678' ),
			'foreign'    => array( '+15551234567' ),
			'letters'    => array( 'abc' ),
			'too short'  => array( '0912123456' ),
			'too long'   => array( '091212345678' ),
			'array'      => array( array( '09121234567' ) ),
			'null'       => array( null ),
		);
	}

	/**
	 * @dataProvider invalid_numbers
	 *
	 * @param mixed $input Input.
	 */
	public function test_invalid_numbers_return_empty_string( $input ) {
		$this->assertSame( '', MW24_Eitaa_Phone_Normalizer::normalize( $input ) );
	}

	public function test_variants() {
		$this->assertSame(
			array( '09121234567', '9121234567', '989121234567', '+989121234567', '00989121234567' ),
			MW24_Eitaa_Phone_Normalizer::variants( '09121234567' )
		);
		$this->assertSame( array(), MW24_Eitaa_Phone_Normalizer::variants( '' ) );
	}

	/**
	 * Formats.
	 *
	 * @return array
	 */
	public function formats() {
		return array(
			array( '0', '', '09121234567' ),
			array( '9', '', '9121234567' ),
			array( '98', '', '989121234567' ),
			array( '+98', '', '+989121234567' ),
			array( '0098', '', '00989121234567' ),
			array( 'custom', '+{cc}-{national}', '+98-9121234567' ),
			array( 'custom', '{local}', '09121234567' ),
			array( 'custom', '', '09121234567' ),
			array( 'unknown', '', '09121234567' ),
		);
	}

	/**
	 * Regression: switch() compared "+98" == "98" numerically.
	 *
	 * @dataProvider formats
	 *
	 * @param string $format   Format.
	 * @param string $pattern  Pattern.
	 * @param string $expected Expected.
	 */
	public function test_format( $format, $pattern, $expected ) {
		$this->assertSame( $expected, MW24_Eitaa_Phone_Normalizer::format( '09121234567', $format, $pattern ) );
	}

	public function test_detect_format() {
		$this->assertSame( '+98', MW24_Eitaa_Phone_Normalizer::detect_format( '+989121234567' ) );
		$this->assertSame( '0098', MW24_Eitaa_Phone_Normalizer::detect_format( '00989121234567' ) );
		$this->assertSame( '98', MW24_Eitaa_Phone_Normalizer::detect_format( '989121234567' ) );
		$this->assertSame( '0', MW24_Eitaa_Phone_Normalizer::detect_format( '09121234567' ) );
		$this->assertSame( '9', MW24_Eitaa_Phone_Normalizer::detect_format( '9121234567' ) );
	}

	public function test_mask() {
		$this->assertSame( '0912***4567', MW24_Eitaa_Phone_Normalizer::mask( '+989121234567' ) );
		$this->assertSame( '0912***4567', MW24_Eitaa_Phone_Normalizer::mask( '۰۹۱۲۱۲۳۴۵۶۷' ) );
		$this->assertSame( '***', MW24_Eitaa_Phone_Normalizer::mask( '123' ) );
	}

	public function test_normalize_filter() {
		$callback = static function ( $result, $raw ) {
			return 'override' === $raw ? '09990000000' : $result;
		};
		add_filter( 'mw24_eitaa_normalize_phone', $callback, 10, 2 );
		$this->assertSame( '09990000000', MW24_Eitaa_Phone_Normalizer::normalize( 'override' ) );
		remove_filter( 'mw24_eitaa_normalize_phone', $callback, 10 );
	}
}
