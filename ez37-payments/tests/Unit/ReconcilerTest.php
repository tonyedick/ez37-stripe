<?php
/**
 * Tests for Reconciler (missed-webhook backstop).
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use Brain\Monkey\Functions;
use EZ37\Payments\Config;
use EZ37\Payments\Stripe\GatewayException;
use EZ37\Payments\Stripe\StripeGateway;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Transactions\Reconciler;
use EZ37\Payments\Transactions\TransactionRepository;
use EZ37\Payments\Webhooks\EventProcessor;
use Mockery;
use Mockery\MockInterface;

final class ReconcilerTest extends TestCase {

	private const FAKE = 'FAKE0000000000000000';

	/**
	 * Mocks.
	 *
	 * @var TransactionRepository&MockInterface
	 */
	private $transactions;

	/**
	 * Mocks.
	 *
	 * @var StripeGateway&MockInterface
	 */
	private $gateway;

	/**
	 * Mocks.
	 *
	 * @var EventProcessor&MockInterface
	 */
	private $processor;

	protected function setUp(): void {
		parent::setUp();
		$this->transactions = Mockery::mock( TransactionRepository::class );
		$this->gateway      = Mockery::mock( StripeGateway::class );
		$this->processor    = Mockery::mock( EventProcessor::class );

		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
	}

	private function reconciler( bool $with_key = true ): Reconciler {
		return new Reconciler(
			new Config( $with_key ? array( 'EZ37_STRIPE_TEST_SECRET_KEY' => 'sk_test_' . self::FAKE ) : array() ),
			$this->transactions,
			$this->gateway,
			$this->processor,
			new Logger( 'error', static function (): void {} )
		);
	}

	private static function row( string $session ): array {
		return array(
			'public_id'                  => 'p_' . $session,
			'stripe_checkout_session_id' => $session,
		);
	}

	public function test_fetches_each_unconfirmed_session_and_applies_it(): void {
		Functions\when( 'delete_transient' )->justReturn( true );
		$this->transactions->shouldReceive( 'find_unconfirmed' )
			->once()
			->with( 'test', Reconciler::MIN_AGE_MINUTES, Reconciler::MAX_AGE_HOURS, Reconciler::BATCH_SIZE )
			->andReturn( array( self::row( 'cs_test_1' ), self::row( 'cs_test_2' ) ) );

		$this->gateway->shouldReceive( 'retrieve_checkout_session' )->with( 'cs_test_1' )->andReturn(
			array(
				'id'     => 'cs_test_1',
				'status' => 'complete',
			)
		);
		$this->gateway->shouldReceive( 'retrieve_checkout_session' )->with( 'cs_test_2' )->andReturn(
			array(
				'id'     => 'cs_test_2',
				'status' => 'open',
			)
		);
		$this->processor->shouldReceive( 'reconcile_session' )->with( Mockery::on( static fn ( $s ) => 'cs_test_1' === $s['id'] ) )->andReturn( 'processed' );
		$this->processor->shouldReceive( 'reconcile_session' )->with( Mockery::on( static fn ( $s ) => 'cs_test_2' === $s['id'] ) )->andReturn( 'open' );

		$this->assertSame(
			array(
				'processed' => 1,
				'open'      => 1,
			),
			$this->reconciler()->run()
		);
	}

	public function test_stripe_error_on_one_session_does_not_stop_the_batch(): void {
		Functions\when( 'delete_transient' )->justReturn( true );
		$this->transactions->shouldReceive( 'find_unconfirmed' )->andReturn( array( self::row( 'cs_test_1' ), self::row( 'cs_test_2' ) ) );
		$this->gateway->shouldReceive( 'retrieve_checkout_session' )->with( 'cs_test_1' )->andThrow( new GatewayException( 'network' ) );
		$this->gateway->shouldReceive( 'retrieve_checkout_session' )->with( 'cs_test_2' )->andReturn(
			array(
				'id'     => 'cs_test_2',
				'status' => 'expired',
			)
		);
		$this->processor->shouldReceive( 'reconcile_session' )->once()->andReturn( 'processed' );

		$this->assertSame(
			array(
				'error'     => 1,
				'processed' => 1,
			),
			$this->reconciler()->run()
		);
	}

	public function test_does_nothing_without_a_valid_key(): void {
		$this->transactions->shouldNotReceive( 'find_unconfirmed' );

		$this->assertSame( array(), $this->reconciler( false )->run() );
	}

	public function test_overlapping_runs_are_prevented(): void {
		Functions\when( 'get_transient' )->justReturn( 1 );
		$this->transactions->shouldNotReceive( 'find_unconfirmed' );

		$this->assertSame( array(), $this->reconciler()->run() );
	}

	public function test_lock_is_released_even_on_error(): void {
		$this->transactions->shouldReceive( 'find_unconfirmed' )->andThrow( new \RuntimeException( 'DB down' ) );
		Functions\expect( 'delete_transient' )->once();

		$this->expectException( \RuntimeException::class );
		$this->reconciler()->run();
	}
}
