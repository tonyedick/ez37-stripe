<?php
/**
 * Tests for EventProcessor.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use Brain\Monkey\Actions;
use EZ37\Payments\Config;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Transactions\PaymentStatus;
use EZ37\Payments\Transactions\TransactionRepository;
use EZ37\Payments\Webhooks\EventProcessor;
use EZ37\Payments\Webhooks\EventRepository;
use Mockery;
use Mockery\MockInterface;

final class EventProcessorTest extends TestCase {

	/**
	 * Transactions mock.
	 *
	 * @var TransactionRepository&MockInterface
	 */
	private $transactions;

	/**
	 * Events mock.
	 *
	 * @var EventRepository&MockInterface
	 */
	private $events;

	protected function setUp(): void {
		parent::setUp();
		$this->transactions = Mockery::mock( TransactionRepository::class );
		$this->events       = Mockery::mock( EventRepository::class );
		$this->events->shouldReceive( 'finish' )->byDefault();
	}

	private function processor( string $mode = 'test' ): EventProcessor {
		return new EventProcessor(
			new Config( array( 'EZ37_STRIPE_MODE' => $mode ) ),
			$this->transactions,
			$this->events,
			new Logger( 'error', static function (): void {} )
		);
	}

	/**
	 * A stored transaction row.
	 *
	 * @return array<string, mixed>
	 */
	private static function transaction(): array {
		return array(
			'id'          => '42',
			'public_id'   => 'pub42',
			'amount'      => '100',
			'currency'    => 'usd',
			'stripe_mode' => 'test',
		);
	}

	/**
	 * A Checkout Session matching transaction().
	 *
	 * @param array<string, mixed> $overrides Field overrides.
	 * @return array<string, mixed>
	 */
	private static function session( array $overrides = array() ): array {
		return array_replace_recursive(
			array(
				'id'                  => 'cs_test_1',
				'object'              => 'checkout.session',
				'client_reference_id' => 'pub42',
				'metadata'            => array( 'ez37_transaction' => 'pub42' ),
				'mode'                => 'payment',
				'livemode'            => false,
				'payment_status'      => 'paid',
				'currency'            => 'usd',
				'amount_subtotal'     => 100,
				'amount_total'        => 100,
				'total_details'       => array(
					'amount_tax'      => 0,
					'amount_discount' => 0,
					'amount_shipping' => 0,
				),
				'payment_intent'      => 'pi_123',
				'customer'            => 'cus_123',
			),
			$overrides
		);
	}

	/**
	 * A Stripe event wrapping an object.
	 *
	 * @param string               $type     Event type.
	 * @param array<string, mixed> $data_object data.object.
	 * @param bool                 $livemode Livemode.
	 * @return array<string, mixed>
	 */
	private static function event( string $type, array $data_object, bool $livemode = false ): array {
		return array(
			'id'       => 'evt_1',
			'type'     => $type,
			'livemode' => $livemode,
			'data'     => array( 'object' => $data_object ),
		);
	}

	private function first_delivery(): void {
		$this->events->shouldReceive( 'record' )->once()->andReturn( true );
	}

	public function test_completed_paid_session_marks_transaction_paid_and_fires_action_once(): void {
		$this->first_delivery();
		$this->transactions->shouldReceive( 'find_by_session_id' )->with( 'cs_test_1' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'transition' )
			->once()
			->with(
				42,
				PaymentStatus::Paid,
				Mockery::on(
					static fn ( array $set ) => 'pi_123' === $set['stripe_payment_intent_id']
						&& 'cus_123' === $set['stripe_customer_id']
						&& 100 === $set['amount_paid']
						&& 100 === $set['amount_subtotal']
						&& 0 === $set['amount_tax']
						&& 'pending' === $set['fulfilment_status']
						&& isset( $set['paid_at'] )
				)
			)
			->andReturn( true );
		$this->events->shouldReceive( 'finish' )->once()->with( 'evt_1', EventRepository::PROCESSED, 42, null );
		Actions\expectDone( EventProcessor::PAID_ACTION )->once()->with( 42 );

		$this->assertSame( 'processed', $this->processor()->process( self::event( 'checkout.session.completed', self::session() ) ) );
	}

	public function test_duplicate_of_processed_event_does_nothing(): void {
		$this->events->shouldReceive( 'record' )->andReturn( false );
		$this->events->shouldReceive( 'status' )->with( 'evt_1' )->andReturn( EventRepository::PROCESSED );
		$this->transactions->shouldNotReceive( 'find_by_session_id', 'transition' );
		Actions\expectDone( EventProcessor::PAID_ACTION )->never();

		$this->assertSame( 'duplicate', $this->processor()->process( self::event( 'checkout.session.completed', self::session() ) ) );
	}

	public function test_redelivery_after_failed_attempt_is_processed_again(): void {
		$this->events->shouldReceive( 'record' )->andReturn( false );
		$this->events->shouldReceive( 'status' )->andReturn( EventRepository::FAILED );
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'transition' )->once()->andReturn( true );
		Actions\expectDone( EventProcessor::PAID_ACTION )->once();

		$this->assertSame( 'processed', $this->processor()->process( self::event( 'checkout.session.completed', self::session() ) ) );
	}

	public function test_already_paid_transaction_does_not_fire_action_again(): void {
		$this->first_delivery();
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'transition' )->andReturn( false );
		Actions\expectDone( EventProcessor::PAID_ACTION )->never();

		$this->assertSame( 'processed', $this->processor()->process( self::event( 'checkout.session.completed', self::session() ) ) );
	}

	public function test_unknown_event_type_is_ignored(): void {
		$this->first_delivery();
		$this->transactions->shouldNotReceive( 'find_by_session_id', 'transition' );
		$this->events->shouldReceive( 'finish' )->once()->with( 'evt_1', EventRepository::IGNORED, null, Mockery::type( 'string' ) );

		$this->assertSame( 'ignored', $this->processor()->process( self::event( 'customer.created', array( 'id' => 'cus_1' ) ) ) );
	}

	public function test_livemode_mismatch_is_ignored(): void {
		$this->first_delivery();
		$this->transactions->shouldNotReceive( 'find_by_session_id', 'transition' );

		$this->assertSame( 'ignored', $this->processor()->process( self::event( 'checkout.session.completed', self::session(), true ) ) );
	}

	public function test_unknown_session_is_ignored(): void {
		$this->first_delivery();
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( null );
		$this->transactions->shouldNotReceive( 'transition' );

		$this->assertSame( 'ignored', $this->processor()->process( self::event( 'checkout.session.completed', self::session() ) ) );
	}

	/**
	 * Sessions that must never be marked paid.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function mismatches(): array {
		return array(
			'reference'       => array( array( 'client_reference_id' => 'other' ) ),
			'metadata'        => array( array( 'metadata' => array( 'ez37_transaction' => 'other' ) ) ),
			'mode'            => array( array( 'mode' => 'subscription' ) ),
			'currency'        => array( array( 'currency' => 'cad' ) ),
			'cheaper'         => array(
				array(
					'amount_subtotal' => 1,
					'amount_total'    => 1,
				),
			),
			'total mismatch'  => array( array( 'amount_total' => 50 ) ),
			'discount'        => array(
				array(
					'total_details' => array( 'amount_discount' => 50 ),
					'amount_total'  => 50,
				),
			),
			'no_payment_req.' => array( array( 'payment_status' => 'no_payment_required' ) ),
		);
	}

	/**
	 * A mismatching session is flagged for review and not marked paid.
	 *
	 * @dataProvider mismatches
	 *
	 * @param array<string, mixed> $overrides Session changes.
	 */
	public function test_mismatch_is_flagged_for_review_not_paid( array $overrides ): void {
		$this->first_delivery();
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'flag_for_review' )->once()->with( 42, 'webhook_mismatch', Mockery::type( 'string' ) );
		$this->transactions->shouldNotReceive( 'transition' );
		Actions\expectDone( EventProcessor::PAID_ACTION )->never();

		$this->assertSame( 'review', $this->processor()->process( self::event( 'checkout.session.completed', self::session( $overrides ) ) ) );
	}

	public function test_consistent_tax_is_accepted(): void {
		$this->first_delivery();
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'transition' )
			->once()
			->with( 42, PaymentStatus::Paid, Mockery::on( static fn ( array $set ) => 8 === $set['amount_tax'] && 108 === $set['amount_paid'] ) )
			->andReturn( true );
		Actions\expectDone( EventProcessor::PAID_ACTION )->once();

		$session = self::session(
			array(
				'amount_total'  => 108,
				'total_details' => array( 'amount_tax' => 8 ),
			)
		);

		$this->assertSame( 'processed', $this->processor()->process( self::event( 'checkout.session.completed', $session ) ) );
	}

	public function test_completed_but_unpaid_moves_to_processing(): void {
		$this->first_delivery();
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'transition' )->once()->with( 42, PaymentStatus::Processing )->andReturn( true );
		Actions\expectDone( EventProcessor::PAID_ACTION )->never();

		$this->processor()->process( self::event( 'checkout.session.completed', self::session( array( 'payment_status' => 'unpaid' ) ) ) );
		$this->addToAssertionCount( 1 );
	}

	public function test_async_payment_succeeded_marks_paid(): void {
		$this->first_delivery();
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'transition' )->once()->with( 42, PaymentStatus::Paid, Mockery::type( 'array' ) )->andReturn( true );
		Actions\expectDone( EventProcessor::PAID_ACTION )->once();

		$this->processor()->process( self::event( 'checkout.session.async_payment_succeeded', self::session() ) );
		$this->addToAssertionCount( 1 );
	}

	public function test_async_payment_failed_marks_failed(): void {
		$this->first_delivery();
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'transition' )->once()->with( 42, PaymentStatus::Failed, Mockery::type( 'array' ) )->andReturn( true );
		Actions\expectDone( EventProcessor::PAID_ACTION )->never();

		$this->processor()->process( self::event( 'checkout.session.async_payment_failed', self::session( array( 'payment_status' => 'unpaid' ) ) ) );
		$this->addToAssertionCount( 1 );
	}

	public function test_expired_session_marks_expired(): void {
		$this->first_delivery();
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'transition' )->once()->with( 42, PaymentStatus::Expired )->andReturn( true );

		$this->processor()->process( self::event( 'checkout.session.expired', self::session( array( 'payment_status' => 'unpaid' ) ) ) );
		$this->addToAssertionCount( 1 );
	}

	public function test_internal_error_marks_event_failed_and_rethrows(): void {
		$this->first_delivery();
		$this->transactions->shouldReceive( 'find_by_session_id' )->andThrow( new \RuntimeException( 'DB down' ) );
		$this->events->shouldReceive( 'finish' )->once()->with( 'evt_1', EventRepository::FAILED, null, 'DB down' );

		$this->expectException( \RuntimeException::class );
		$this->processor()->process( self::event( 'checkout.session.completed', self::session() ) );
	}

	public function test_event_without_id_is_rejected(): void {
		$this->events->shouldNotReceive( 'record' );

		$this->expectException( \RuntimeException::class );
		$this->processor()->process( array( 'type' => 'checkout.session.completed' ) );
	}

	public function test_reconcile_complete_paid_session_marks_paid_without_event_log(): void {
		$this->events->shouldNotReceive( 'record', 'finish' );
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'transition' )->once()->with( 42, PaymentStatus::Paid, Mockery::type( 'array' ) )->andReturn( true );
		Actions\expectDone( EventProcessor::PAID_ACTION )->once()->with( 42 );

		$this->assertSame( 'processed', $this->processor()->reconcile_session( self::session( array( 'status' => 'complete' ) ) ) );
	}

	public function test_reconcile_expired_session_marks_expired(): void {
		$this->events->shouldNotReceive( 'record', 'finish' );
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'transition' )->once()->with( 42, PaymentStatus::Expired )->andReturn( true );

		$this->assertSame(
			'processed',
			$this->processor()->reconcile_session(
				self::session(
					array(
						'status'         => 'expired',
						'payment_status' => 'unpaid',
					)
				)
			)
		);
	}

	public function test_reconcile_open_session_changes_nothing(): void {
		$this->transactions->shouldNotReceive( 'find_by_session_id', 'transition' );

		$this->assertSame( 'open', $this->processor()->reconcile_session( self::session( array( 'status' => 'open' ) ) ) );
	}

	public function test_reconcile_mismatch_is_flagged_not_paid(): void {
		$this->transactions->shouldReceive( 'find_by_session_id' )->andReturn( self::transaction() );
		$this->transactions->shouldReceive( 'flag_for_review' )->once();
		$this->transactions->shouldNotReceive( 'transition' );

		$this->assertSame(
			'review',
			$this->processor()->reconcile_session(
				self::session(
					array(
						'status'          => 'complete',
						'amount_subtotal' => 1,
						'amount_total'    => 1,
					)
				)
			)
		);
	}
}
