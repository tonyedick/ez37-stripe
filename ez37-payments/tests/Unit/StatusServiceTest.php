<?php
/**
 * Tests for StatusService (success page lookup).
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use EZ37\Payments\Frontend\StatusService;
use EZ37\Payments\Transactions\TransactionRepository;
use Mockery;
use Mockery\MockInterface;

final class StatusServiceTest extends TestCase {

	private const SESSION = 'cs_test_a0RaQu2fAsQLYXqFGNUSC7lTzGMcp9FFRkCHsj';

	/**
	 * Repository mock.
	 *
	 * @var TransactionRepository&MockInterface
	 */
	private $transactions;

	protected function setUp(): void {
		parent::setUp();
		$this->transactions = Mockery::mock( TransactionRepository::class );
	}

	/**
	 * A stored transaction row.
	 *
	 * @param array<string, mixed> $overrides Column overrides.
	 * @return array<string, mixed>
	 */
	private static function row( array $overrides = array() ): array {
		return array_merge(
			array(
				'public_id'       => 'pub1',
				'offering_name'   => 'Leadership Coaching',
				'amount'          => '100',
				'currency'        => 'usd',
				'customer_email'  => 'jane.doe@example.com',
				'customer_name'   => 'Jane Doe',
				'payment_status'  => 'paid',
				'review_required' => '0',
			),
			$overrides
		);
	}

	public function test_paid_transaction(): void {
		$this->transactions->shouldReceive( 'find_by_session_id' )->with( self::SESSION )->andReturn( self::row() );

		$result = ( new StatusService( $this->transactions ) )->lookup( self::SESSION );

		$this->assertSame( 'paid', $result['state'] );
		$this->assertSame( 'Leadership Coaching', $result['offering'] );
		$this->assertSame( '$1.00 USD', $result['amount'] );
		$this->assertSame( 'j***@example.com', $result['email'] );
	}

	public function test_only_minimal_data_is_disclosed(): void {
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::row() );

		$result = ( new StatusService( $this->transactions ) )->lookup( self::SESSION );

		$this->assertSame( array( 'state', 'offering', 'amount', 'email' ), array_keys( $result ) );
		$this->assertStringNotContainsString( 'jane.doe', implode( ' ', $result ) );
		$this->assertStringNotContainsString( 'Jane Doe', implode( ' ', $result ) );
	}

	/**
	 * Payment status → customer-facing state.
	 *
	 * @return array<string, array{0: array<string, mixed>, 1: string}>
	 */
	public static function states(): array {
		return array(
			'pending'     => array( array( 'payment_status' => 'pending' ), 'pending' ),
			'processing'  => array( array( 'payment_status' => 'processing' ), 'pending' ),
			'failed'      => array( array( 'payment_status' => 'failed' ), 'failed' ),
			'expired'     => array( array( 'payment_status' => 'expired' ), 'expired' ),
			'review'      => array( array( 'review_required' => '1' ), 'review' ),
			'review+paid' => array(
				array(
					'payment_status'  => 'paid',
					'review_required' => '1',
				),
				'review',
			),
		);
	}

	/**
	 * State mapping.
	 *
	 * @dataProvider states
	 *
	 * @param array<string, mixed> $overrides Row changes.
	 * @param string               $expected  Expected state.
	 */
	public function test_state_mapping( array $overrides, string $expected ): void {
		$this->assertSame( $expected, StatusService::state_for( self::row( $overrides ) ) );
	}

	public function test_malformed_session_ids_never_reach_the_database(): void {
		$this->transactions->shouldNotReceive( 'find_by_session_id' );
		$service = new StatusService( $this->transactions );

		foreach ( array( '', 'cs_test_', "cs_test_abc' OR 1=1", 'pi_123', 'cs_prod_abcdefghijk', str_repeat( 'a', 300 ), 'cs_test_abc/../x' ) as $id ) {
			$this->assertSame( 'not_found', $service->lookup( $id )['state'], $id );
		}
	}

	public function test_unknown_session(): void {
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( null );

		$result = ( new StatusService( $this->transactions ) )->lookup( self::SESSION );

		$this->assertSame( 'not_found', $result['state'] );
		$this->assertSame( '', $result['email'] );
	}

	public function test_mask_email(): void {
		$this->assertSame( 'j***@example.com', StatusService::mask_email( 'jane.doe@example.com' ) );
		$this->assertSame( 'a***@b.co', StatusService::mask_email( 'a@b.co' ) );
		$this->assertSame( '', StatusService::mask_email( 'not-an-email' ) );
		$this->assertSame( '', StatusService::mask_email( '@example.com' ) );
	}
}
