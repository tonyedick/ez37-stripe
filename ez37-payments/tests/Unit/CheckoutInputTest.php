<?php
/**
 * Tests for CheckoutInput.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use Brain\Monkey\Functions;
use EZ37\Payments\Checkout\CheckoutException;
use EZ37\Payments\Checkout\CheckoutInput;

final class CheckoutInputTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		// Simplified stand-ins for the WordPress sanitising functions.
		Functions\when( 'sanitize_text_field' )->alias( static fn ( $s ) => trim( (string) preg_replace( '/<[^>]*>/', '', (string) $s ) ) );
		Functions\when( 'sanitize_email' )->alias( static fn ( $s ) => preg_replace( '/[^A-Za-z0-9@._%+\-]/', '', (string) $s ) );
		Functions\when( 'is_email' )->alias( static fn ( $s ) => false !== filter_var( $s, FILTER_VALIDATE_EMAIL ) ? $s : false );
	}

	/**
	 * Valid request values.
	 *
	 * @return array<string, mixed>
	 */
	private static function valid(): array {
		return array(
			'offering'       => 'leadership-coaching',
			'customer_name'  => 'Jane Doe',
			'customer_email' => 'Jane.Doe@Example.com',
		);
	}

	private function expect_invalid( array $raw ): void {
		try {
			CheckoutInput::from_request( $raw );
			$this->fail( 'Expected CheckoutException.' );
		} catch ( CheckoutException $e ) {
			$this->assertSame( CheckoutException::INVALID_INPUT, $e->reason );
		}
	}

	public function test_valid_input(): void {
		$input = CheckoutInput::from_request( self::valid() );

		$this->assertSame( 'leadership-coaching', $input->offering_key );
		$this->assertSame( 'Jane Doe', $input->name );
		$this->assertSame( 'jane.doe@example.com', $input->email, 'Email is normalised to lowercase.' );
	}

	public function test_price_and_amount_fields_are_ignored(): void {
		$raw             = self::valid();
		$raw['amount']   = '1';
		$raw['price']    = 'price_attacker';
		$raw['currency'] = 'jpy';

		$input = CheckoutInput::from_request( $raw );

		$this->assertFalse( property_exists( $input, 'amount' ) );
		$this->assertFalse( property_exists( $input, 'price' ) );
	}

	public function test_whitespace_in_name_is_collapsed(): void {
		$raw                  = self::valid();
		$raw['customer_name'] = '  Jane    Doe  ';

		$this->assertSame( 'Jane Doe', CheckoutInput::from_request( $raw )->name );
	}

	public function test_missing_offering(): void {
		$raw = self::valid();
		unset( $raw['offering'] );
		$this->expect_invalid( $raw );
	}

	public function test_invalid_names(): void {
		foreach ( array( '', 'J', str_repeat( 'a', 101 ), "Jane\nBcc: x@y.z", array( 'Jane' ) ) as $name ) {
			$raw                  = self::valid();
			$raw['customer_name'] = $name;
			$this->expect_invalid( $raw );
		}
	}

	public function test_invalid_emails(): void {
		foreach ( array( '', 'abc', 'a@', '@example.com', "jane@example.com\r\nBcc: x@y.z", 'jane doe@example.com', str_repeat( 'a', 250 ) . '@example.com', null ) as $email ) {
			$raw                   = self::valid();
			$raw['customer_email'] = $email;
			$this->expect_invalid( $raw );
		}
	}
}
