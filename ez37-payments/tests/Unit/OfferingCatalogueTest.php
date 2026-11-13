<?php
/**
 * Tests for OfferingCatalogue and Offering.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use EZ37\Payments\Offerings\OfferingCatalogue;

final class OfferingCatalogueTest extends TestCase {

	/**
	 * A valid definition.
	 *
	 * @return array<string, mixed>
	 */
	private static function valid(): array {
		return array(
			'name'       => 'Leadership Coaching',
			'active'     => true,
			'currency'   => 'usd',
			'amount'     => 100,
			'price_ids'  => array(
				'test' => 'price_TEST123',
				'live' => '',
			),
			'tax'        => 'none',
			'fulfilment' => array( 'handler' => 'resource_access' ),
		);
	}

	public function test_valid_offering_is_loaded(): void {
		$catalogue = new OfferingCatalogue( array( 'leadership-coaching' => self::valid() ) );
		$offering  = $catalogue->find( 'leadership-coaching' );

		$this->assertNotNull( $offering );
		$this->assertSame( array(), $catalogue->errors() );
		$this->assertSame( 'Leadership Coaching', $offering->name );
		$this->assertSame( 100, $offering->amount );
		$this->assertSame( 'usd', $offering->currency );
		$this->assertSame( 'price_TEST123', $offering->price_id( 'test' ) );
		$this->assertNull( $offering->price_id( 'live' ) );
		$this->assertTrue( $offering->is_purchasable( 'test' ) );
		$this->assertFalse( $offering->is_purchasable( 'live' ) );
		$this->assertSame( '$1.00 USD', $offering->formatted_price() );
	}

	public function test_inactive_offering_is_not_purchasable(): void {
		$definition           = self::valid();
		$definition['active'] = false;

		$offering = ( new OfferingCatalogue( array( 'leadership-coaching' => $definition ) ) )->find( 'leadership-coaching' );

		$this->assertNotNull( $offering );
		$this->assertFalse( $offering->is_purchasable( 'test' ) );
	}

	public function test_unknown_and_malformed_keys_return_null(): void {
		$catalogue = new OfferingCatalogue( array( 'leadership-coaching' => self::valid() ) );

		foreach ( array( 'does-not-exist', '', '../etc/passwd', 'Leadership-Coaching', '<script>', str_repeat( 'a', 65 ), '-leading' ) as $key ) {
			$this->assertNull( $catalogue->find( $key ), $key );
		}
	}

	/**
	 * Invalid definitions are skipped and reported, never half-loaded.
	 *
	 * @return array<string, array{0: string, 1: mixed}>
	 */
	public static function invalid_fields(): array {
		return array(
			'empty name'         => array( 'name', '' ),
			'active as string'   => array( 'active', 'yes' ),
			'unsupported curr'   => array( 'currency', 'eur' ),
			'uppercase currency' => array( 'currency', 'USD' ),
			'float amount'       => array( 'amount', 1.0 ),
			'zero amount'        => array( 'amount', 0 ),
			'negative amount'    => array( 'amount', -100 ),
			'string amount'      => array( 'amount', '100' ),
			'bad price id'       => array(
				'price_ids',
				array(
					'test' => 'prod_123',
					'live' => '',
				),
			),
			'price ids string'   => array( 'price_ids', 'price_123' ),
			'tax not confirmed'  => array( 'tax', 'vat' ),
			'fulfilment string'  => array( 'fulfilment', 'send pdf' ),
		);
	}

	/**
	 * Invalid fields disable the offering.
	 *
	 * @dataProvider invalid_fields
	 *
	 * @param string $field Field to break.
	 * @param mixed  $value Invalid value.
	 */
	public function test_invalid_definition_is_rejected( string $field, mixed $value ): void {
		$definition           = self::valid();
		$definition[ $field ] = $value;

		$catalogue = new OfferingCatalogue( array( 'leadership-coaching' => $definition ) );

		$this->assertNull( $catalogue->find( 'leadership-coaching' ) );
		$this->assertCount( 1, $catalogue->errors() );
		$this->assertStringContainsString( 'leadership-coaching', $catalogue->errors()[0] );
	}

	public function test_invalid_key_is_rejected(): void {
		$catalogue = new OfferingCatalogue( array( 'Bad Key' => self::valid() ) );

		$this->assertSame( array(), $catalogue->all() );
		$this->assertCount( 1, $catalogue->errors() );
	}

	public function test_one_bad_offering_does_not_affect_others(): void {
		$bad           = self::valid();
		$bad['amount'] = 'free';

		$catalogue = new OfferingCatalogue(
			array(
				'leadership-coaching' => self::valid(),
				'mentor-coaching'     => $bad,
			)
		);

		$this->assertNotNull( $catalogue->find( 'leadership-coaching' ) );
		$this->assertNull( $catalogue->find( 'mentor-coaching' ) );
	}

	public function test_shipped_config_file_is_valid(): void {
		$catalogue = OfferingCatalogue::from_file( dirname( __DIR__, 2 ) . '/config/offerings.php' );

		$this->assertSame( array(), $catalogue->errors() );
		$this->assertNotNull( $catalogue->find( 'leadership-coaching' ) );
	}

	public function test_missing_config_file_gives_empty_catalogue(): void {
		$this->assertSame( array(), OfferingCatalogue::from_file( '/nonexistent/offerings.php' )->all() );
	}
}
