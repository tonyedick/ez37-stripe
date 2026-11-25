<?php
/**
 * Tests for EnrolmentForm and the enrolment/agreement parts of the catalogues.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use Brain\Monkey\Functions;
use EZ37\Payments\Agreements\AgreementCatalogue;
use EZ37\Payments\Checkout\CheckoutException;
use EZ37\Payments\Checkout\EnrolmentForm;
use EZ37\Payments\Offerings\OfferingCatalogue;

final class EnrolmentFormTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'sanitize_text_field' )->alias( static fn ( $s ) => trim( (string) preg_replace( '/<[^>]*>/', '', (string) $s ) ) );
		Functions\when( 'sanitize_textarea_field' )->alias( static fn ( $s ) => trim( (string) preg_replace( '/<[^>]*>/', '', (string) $s ) ) );
		Functions\when( 'wp_json_encode' )->alias( static fn ( $v ) => json_encode( $v ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private static function fields(): array {
		return array(
			array(
				'key'      => 'first_name',
				'label'    => 'First name',
				'type'     => 'text',
				'required' => true,
				'options'  => array(),
				'max'      => 120,
			),
			array(
				'key'      => 'phone',
				'label'    => 'Phone',
				'type'     => 'tel',
				'required' => true,
				'options'  => array(),
				'max'      => 120,
			),
			array(
				'key'      => 'gender',
				'label'    => 'Gender',
				'type'     => 'select',
				'required' => false,
				'options'  => array( 'Female', 'Male' ),
				'max'      => 120,
			),
		);
	}

	public function test_valid_values_are_returned_and_unknown_keys_dropped(): void {
		$clean = EnrolmentForm::validate(
			self::fields(),
			array(
				'first_name' => '  Jane ',
				'phone'      => '+234 813 089 8773',
				'gender'     => 'Female',
				'extra'      => 'dropped',
			)
		);

		$this->assertSame(
			array(
				'first_name' => 'Jane',
				'phone'      => '+234 813 089 8773',
				'gender'     => 'Female',
			),
			$clean
		);
	}

	public function test_optional_field_may_be_empty(): void {
		$clean = EnrolmentForm::validate( self::fields(), array( 'first_name' => 'Jane', 'phone' => '+2348130898773' ) );

		$this->assertSame( '', $clean['gender'] );
	}

	/**
	 * @dataProvider invalid_provider
	 *
	 * @param array<string, mixed> $raw Submitted values.
	 */
	public function test_invalid_values_are_refused( array $raw ): void {
		$this->expectException( CheckoutException::class );
		EnrolmentForm::validate( self::fields(), $raw );
	}

	/**
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function invalid_provider(): array {
		$ok = array(
			'first_name' => 'Jane',
			'phone'      => '+2348130898773',
		);

		return array(
			'missing required'    => array( array( 'phone' => '+2348130898773' ) ),
			'blank required'      => array( array_merge( $ok, array( 'first_name' => '   ' ) ) ),
			'option not in list'  => array( array_merge( $ok, array( 'gender' => 'Other' ) ) ),
			'bad phone'           => array( array_merge( $ok, array( 'phone' => 'call me' ) ) ),
			'too long'            => array( array_merge( $ok, array( 'first_name' => str_repeat( 'a', 121 ) ) ) ),
			'line break'          => array( array_merge( $ok, array( 'first_name' => "Jane\r\nBcc: x@y.z" ) ) ),
			'array instead of string' => array( array_merge( $ok, array( 'first_name' => array( 'x' ) ) ) ),
		);
	}

	public function test_catalogue_accepts_enrolment_fields_and_agreement_key(): void {
		$catalogue = new OfferingCatalogue(
			array(
				'pccp' => array(
					'name'      => 'PCCP',
					'active'    => true,
					'currency'  => 'usd',
					'amount'    => 269900,
					'price_ids' => array(
						'test' => 'price_X1',
						'live' => '',
					),
					'agreement' => 'pccp-enrolment',
					'enrolment' => array(
						array(
							'key'   => 'country',
							'label' => 'Country',
						),
						array(
							'key'     => 'gender',
							'label'   => 'Gender',
							'type'    => 'select',
							'options' => array( 'Female' ),
						),
					),
				),
			)
		);

		$offering = $catalogue->find( 'pccp' );
		$this->assertNotNull( $offering, implode( ' ', $catalogue->errors() ) );
		$this->assertSame( 'pccp-enrolment', $offering->agreement_key );
		$this->assertSame( array( 'country', 'gender' ), array_column( $offering->enrolment_fields, 'key' ) );
		$this->assertSame( '$2,699.00 USD', $offering->formatted_price() );
	}

	public function test_catalogue_rejects_bad_enrolment_definitions(): void {
		$base = array(
			'name'      => 'PCCP',
			'active'    => true,
			'currency'  => 'usd',
			'amount'    => 100,
			'price_ids' => array(
				'test' => 'price_X1',
				'live' => '',
			),
		);

		foreach ( array(
			'duplicate key'        => array(
				array(
					'key'   => 'a',
					'label' => 'A',
				),
				array(
					'key'   => 'a',
					'label' => 'B',
				),
			),
			'bad key'              => array(
				array(
					'key'   => 'Bad Key',
					'label' => 'A',
				),
			),
			'select without options' => array(
				array(
					'key'   => 'a',
					'label' => 'A',
					'type'  => 'select',
				),
			),
			'unknown type'         => array(
				array(
					'key'   => 'a',
					'label' => 'A',
					'type'  => 'file',
				),
			),
		) as $label => $enrolment ) {
			$catalogue = new OfferingCatalogue( array( 'pccp' => array_merge( $base, array( 'enrolment' => $enrolment ) ) ) );
			$this->assertNull( $catalogue->find( 'pccp' ), $label );
			$this->assertNotSame( array(), $catalogue->errors(), $label );
		}
	}

	public function test_agreement_hash_changes_with_the_text_and_version(): void {
		$def = array(
			'version'  => 'v1',
			'title'    => 'Agreement',
			'label'    => 'I agree',
			'sections' => array(
				array(
					'heading'    => 'Terms',
					'paragraphs' => array( 'One.' ),
				),
			),
		);

		$one = ( new AgreementCatalogue( array( 'a' => $def ) ) )->find( 'a' );
		$two = ( new AgreementCatalogue( array( 'a' => array_merge( $def, array( 'version' => 'v2' ) ) ) ) )->find( 'a' );

		$this->assertNotNull( $one );
		$this->assertNotNull( $two );
		$this->assertSame( $one->text_hash(), $one->text_hash(), 'Stable.' );
		$this->assertNotSame( $one->text_hash(), $two->text_hash() );
	}

	public function test_invalid_agreement_is_skipped_with_an_error(): void {
		$catalogue = new AgreementCatalogue(
			array(
				'bad' => array(
					'version' => 'v1',
					'title'   => 'No sections',
					'label'   => 'I agree',
				),
			)
		);

		$this->assertNull( $catalogue->find( 'bad' ) );
		$this->assertCount( 1, $catalogue->errors() );
	}

	public function test_shipped_agreements_config_is_valid(): void {
		Functions\when( 'wp_json_encode' )->alias( static fn ( $v ) => json_encode( $v ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', '/' );
		}

		$catalogue = AgreementCatalogue::from_file( dirname( __DIR__, 2 ) . '/config/agreements.php' );

		$this->assertSame( array(), $catalogue->errors() );
		$this->assertNotNull( $catalogue->find( 'pccp-enrolment' ) );
	}
}
