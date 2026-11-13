<?php
/**
 * Tests for CheckoutService.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use Brain\Monkey\Functions;
use EZ37\Payments\Checkout\CheckoutException;
use EZ37\Payments\Checkout\CheckoutInput;
use EZ37\Payments\Checkout\CheckoutService;
use EZ37\Payments\Config;
use EZ37\Payments\Offerings\Offering;
use EZ37\Payments\Offerings\OfferingCatalogue;
use EZ37\Payments\Stripe\GatewayException;
use EZ37\Payments\Stripe\StripeGateway;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Transactions\TransactionRepository;
use Mockery;
use Mockery\MockInterface;

final class CheckoutServiceTest extends TestCase {

	/**
	 * Appended to key prefixes so no secret-looking literal exists in the repository.
	 */
	private const FAKE = 'FAKE0000000000000000';

	/**
	 * Stripe gateway mock.
	 *
	 * @var StripeGateway&MockInterface
	 */
	private $gateway;

	/**
	 * Repository mock.
	 *
	 * @var TransactionRepository&MockInterface
	 */
	private $transactions;

	/**
	 * Captured log lines.
	 *
	 * @var list<string>
	 */
	private array $log = array();

	protected function setUp(): void {
		parent::setUp();

		$this->gateway      = Mockery::mock( StripeGateway::class );
		$this->transactions = Mockery::mock( TransactionRepository::class );
		$this->log          = array();

		Functions\when( 'home_url' )->alias( static fn ( $path = '' ) => 'https://ez37.test' . $path );
		Functions\when( 'add_query_arg' )->alias(
			static fn ( $key, $value, $url ) => $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . $key . '=' . $value
		);
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->alias( static fn ( $s ) => trim( (string) $s ) );
		Functions\when( 'sanitize_email' )->returnArg();
		Functions\when( 'is_email' )->returnArg();
	}

	private static function config( array $overrides = array() ): Config {
		return new Config(
			array_merge(
				array(
					'EZ37_STRIPE_MODE'                => 'test',
					'EZ37_STRIPE_TEST_SECRET_KEY'     => 'sk_test_' . self::FAKE,
					'EZ37_STRIPE_TEST_WEBHOOK_SECRET' => 'whsec_' . self::FAKE,
					'EZ37_PAYMENTS_CHECKOUT_ENABLED'  => '1',
				),
				$overrides
			)
		);
	}

	private static function catalogue( array $overrides = array() ): OfferingCatalogue {
		return new OfferingCatalogue(
			array(
				'leadership-coaching' => array_merge(
					array(
						'name'      => 'Leadership Coaching',
						'active'    => true,
						'currency'  => 'usd',
						'amount'    => 100,
						'price_ids' => array(
							'test' => 'price_TEST123',
							'live' => '',
						),
					),
					$overrides
				),
			)
		);
	}

	private function service( ?Config $config = null, ?OfferingCatalogue $catalogue = null ): CheckoutService {
		return new CheckoutService(
			$config ?? self::config(),
			$catalogue ?? self::catalogue(),
			$this->gateway,
			$this->transactions,
			new Logger(
				'debug',
				function ( string $line ): void {
					$this->log[] = $line;
				}
			)
		);
	}

	private static function input( string $offering = 'leadership-coaching' ): CheckoutInput {
		return CheckoutInput::from_request(
			array(
				'offering'       => $offering,
				'customer_name'  => 'Jane Doe',
				'customer_email' => 'jane@example.com',
			)
		);
	}

	/**
	 * Price as Stripe would return it.
	 *
	 * @return array<string, mixed>
	 */
	private static function stripe_price( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'          => 'price_TEST123',
				'active'      => true,
				'currency'    => 'usd',
				'unit_amount' => 100,
				'type'        => 'one_time',
				'livemode'    => false,
			),
			$overrides
		);
	}

	private function expect_reason( string $reason, callable $callback ): void {
		try {
			$callback();
			$this->fail( 'Expected CheckoutException ' . $reason );
		} catch ( CheckoutException $e ) {
			$this->assertSame( $reason, $e->reason, $e->getMessage() );
		}
	}

	public function test_successful_start_creates_pending_transaction_and_session(): void {
		$this->gateway->shouldReceive( 'retrieve_price' )->once()->with( 'price_TEST123' )->andReturn( self::stripe_price() );

		$this->transactions->shouldReceive( 'create_pending' )
			->once()
			->with( Mockery::type( Offering::class ), 'price_TEST123', 'test', 'Jane Doe', 'jane@example.com' )
			->andReturn(
				array(
					'id'        => 42,
					'public_id' => 'abc123',
				)
			);

		$captured = null;
		$this->gateway->shouldReceive( 'create_checkout_session' )
			->once()
			->withArgs(
				function ( array $params, string $key ) use ( &$captured ): bool {
					$captured = $params;
					return 'ez37-checkout-abc123' === $key;
				}
			)
			->andReturn(
				array(
					'id'         => 'cs_test_1',
					'url'        => 'https://checkout.stripe.com/c/pay/cs_test_1',
					'expires_at' => time() + 3600,
				)
			);

		$this->transactions->shouldReceive( 'attach_checkout_session' )->once()->with( 42, 'cs_test_1' );

		$url = $this->service()->start( self::input() );

		$this->assertSame( 'https://checkout.stripe.com/c/pay/cs_test_1', $url );

		// Server-side price only; the browser never supplied it.
		$this->assertSame(
			array(
				array(
					'price'    => 'price_TEST123',
					'quantity' => 1,
				),
			),
			$captured['line_items']
		);
		$this->assertSame( 'payment', $captured['mode'] );
		$this->assertSame( 'abc123', $captured['client_reference_id'] );
		$this->assertSame( 'abc123', $captured['metadata']['ez37_transaction'] );
		$this->assertSame( 'abc123', $captured['payment_intent_data']['metadata']['ez37_transaction'] );
		$this->assertSame( 'jane@example.com', $captured['customer_email'] );
		$this->assertSame( 'always', $captured['customer_creation'] );
		$this->assertSame( array( 'card' ), $captured['allowed_payment_method_types'] );
		$this->assertArrayNotHasKey( 'payment_method_types', $captured, 'Rejected by Stripe API 2026-10-28.' );
		$this->assertSame( array( 'enabled' => false ), $captured['adaptive_pricing'] );
		$this->assertArrayNotHasKey( 'automatic_tax', $captured, 'Tax is not confirmed (D7).' );
		$this->assertArrayNotHasKey( 'invoice_creation', $captured, 'Receipts only (D5).' );
		$this->assertSame( 'https://ez37.test/payment-complete/?session_id={CHECKOUT_SESSION_ID}', $captured['success_url'] );
		$this->assertSame( 'https://ez37.test/payment-cancelled/?offering=leadership-coaching', $captured['cancel_url'] );
		$this->assertGreaterThanOrEqual( time() + 1800, $captured['expires_at'], 'Stripe minimum is 30 minutes.' );
		$this->assertLessThanOrEqual( time() + 86400, $captured['expires_at'], 'Stripe maximum is 24 hours.' );

		$this->assertStringNotContainsString( 'jane@example.com', implode( "\n", $this->log ), 'Emails are not logged in clear.' );
	}

	public function test_checkout_disabled_by_switch(): void {
		$this->gateway->shouldNotReceive( 'retrieve_price', 'create_checkout_session' );
		$this->transactions->shouldNotReceive( 'create_pending' );

		$this->expect_reason(
			CheckoutException::DISABLED,
			fn () => $this->service( self::config( array( 'EZ37_PAYMENTS_CHECKOUT_ENABLED' => '0' ) ) )->start( self::input() )
		);
	}

	public function test_live_mode_refuses_checkout_on_a_non_https_site(): void {
		Functions\when( 'home_url' )->alias( static fn ( $path = '' ) => 'http://ez37.test' . $path );
		Functions\when( 'wp_parse_url' )->alias( static fn ( $url, $component = -1 ) => parse_url( $url, $component ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$this->gateway->shouldNotReceive( 'retrieve_price', 'create_checkout_session' );
		$this->transactions->shouldNotReceive( 'create_pending' );

		$live = self::config(
			array(
				'EZ37_STRIPE_MODE'                => 'live',
				'EZ37_STRIPE_LIVE_SECRET_KEY'     => 'sk_live_' . self::FAKE,
				'EZ37_STRIPE_LIVE_WEBHOOK_SECRET' => 'whsec_' . self::FAKE,
			)
		);

		$this->expect_reason( CheckoutException::DISABLED, fn () => $this->service( $live )->start( self::input() ) );
		$this->assertFalse( CheckoutService::site_uses_https() );
	}

	public function test_checkout_disabled_when_configuration_invalid(): void {
		$this->gateway->shouldNotReceive( 'retrieve_price', 'create_checkout_session' );

		$this->expect_reason(
			CheckoutException::DISABLED,
			fn () => $this->service( self::config( array( 'EZ37_STRIPE_TEST_WEBHOOK_SECRET' => null ) ) )->start( self::input() )
		);
	}

	public function test_unknown_offering(): void {
		$this->gateway->shouldNotReceive( 'retrieve_price', 'create_checkout_session' );
		$this->transactions->shouldNotReceive( 'create_pending' );

		$this->expect_reason( CheckoutException::UNAVAILABLE, fn () => $this->service()->start( self::input( 'mentor-coaching' ) ) );
	}

	public function test_inactive_offering(): void {
		$this->gateway->shouldNotReceive( 'retrieve_price', 'create_checkout_session' );

		$this->expect_reason(
			CheckoutException::UNAVAILABLE,
			fn () => $this->service( null, self::catalogue( array( 'active' => false ) ) )->start( self::input() )
		);
	}

	public function test_offering_without_price_for_mode(): void {
		$this->gateway->shouldNotReceive( 'retrieve_price', 'create_checkout_session' );

		$this->expect_reason(
			CheckoutException::UNAVAILABLE,
			fn () => $this->service(
				null,
				self::catalogue(
					array(
						'price_ids' => array(
							'test' => '',
							'live' => '',
						),
					)
				)
			)->start( self::input() )
		);
	}

	/**
	 * Stripe Price problems that must block checkout.
	 *
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function price_mismatches(): array {
		return array(
			'archived'        => array( array( 'active' => false ) ),
			'recurring'       => array( array( 'type' => 'recurring' ) ),
			'wrong currency'  => array( array( 'currency' => 'cad' ) ),
			'wrong amount'    => array( array( 'unit_amount' => 1000 ) ),
			'no amount'       => array( array( 'unit_amount' => null ) ),
			'live price used' => array( array( 'livemode' => true ) ),
		);
	}

	/**
	 * A misconfigured Stripe Price blocks checkout before anything is created.
	 *
	 * @dataProvider price_mismatches
	 *
	 * @param array<string, mixed> $overrides Price fields to change.
	 */
	public function test_price_mismatch_blocks_checkout( array $overrides ): void {
		$this->gateway->shouldReceive( 'retrieve_price' )->andReturn( self::stripe_price( $overrides ) );
		$this->gateway->shouldNotReceive( 'create_checkout_session' );
		$this->transactions->shouldNotReceive( 'create_pending' );
		Functions\expect( 'set_transient' )->never();

		$this->expect_reason( CheckoutException::UNAVAILABLE, fn () => $this->service()->start( self::input() ) );
	}

	public function test_price_lookup_failure(): void {
		$this->gateway->shouldReceive( 'retrieve_price' )->andThrow( new GatewayException( 'No such price', 'resource_missing', 404 ) );
		$this->transactions->shouldNotReceive( 'create_pending' );

		$this->expect_reason( CheckoutException::STRIPE_ERROR, fn () => $this->service()->start( self::input() ) );
	}

	public function test_verified_price_is_cached(): void {
		Functions\when( 'get_transient' )->justReturn( 1 );
		$this->gateway->shouldNotReceive( 'retrieve_price' );
		$this->transactions->shouldReceive( 'create_pending' )->andReturn(
			array(
				'id'        => 1,
				'public_id' => 'p1',
			)
		);
		$this->gateway->shouldReceive( 'create_checkout_session' )->andReturn(
			array(
				'id'         => 'cs_test_2',
				'url'        => 'https://checkout.stripe.com/x',
				'expires_at' => 0,
			)
		);
		$this->transactions->shouldReceive( 'attach_checkout_session' )->once();

		$this->assertSame( 'https://checkout.stripe.com/x', $this->service()->start( self::input() ) );
	}

	public function test_session_creation_failure_marks_transaction_failed(): void {
		$this->gateway->shouldReceive( 'retrieve_price' )->andReturn( self::stripe_price() );
		$this->transactions->shouldReceive( 'create_pending' )->andReturn(
			array(
				'id'        => 7,
				'public_id' => 'p7',
			)
		);
		$this->gateway->shouldReceive( 'create_checkout_session' )
			->andThrow( new GatewayException( 'Invalid API key sk_test_' . self::FAKE, 'api_key_invalid', 401 ) );
		$this->transactions->shouldReceive( 'mark_session_failed' )->once()->with( 7, 'api_key_invalid', Mockery::type( 'string' ) );
		$this->transactions->shouldNotReceive( 'attach_checkout_session' );

		$this->expect_reason( CheckoutException::STRIPE_ERROR, fn () => $this->service()->start( self::input() ) );

		$this->assertStringNotContainsString( 'FAKE0000', implode( "\n", $this->log ), 'Keys never reach the log.' );
	}

	public function test_database_failure_does_not_call_stripe(): void {
		$this->gateway->shouldReceive( 'retrieve_price' )->andReturn( self::stripe_price() );
		$this->transactions->shouldReceive( 'create_pending' )->andThrow( new \RuntimeException( 'DB down' ) );
		$this->gateway->shouldNotReceive( 'create_checkout_session' );

		$this->expect_reason( CheckoutException::STRIPE_ERROR, fn () => $this->service()->start( self::input() ) );
	}

	public function test_custom_return_paths(): void {
		$config = self::config(
			array(
				'EZ37_PAYMENTS_SUCCESS_PATH' => '/thank-you/',
				'EZ37_PAYMENTS_CANCEL_PATH'  => '/checkout/',
			)
		);
		$params = $this->service( $config )->session_params(
			self::catalogue()->find( 'leadership-coaching' ),
			'price_TEST123',
			self::input(),
			'p1'
		);

		$this->assertSame( 'https://ez37.test/thank-you/?session_id={CHECKOUT_SESSION_ID}', $params['success_url'] );
		$this->assertSame( 'https://ez37.test/checkout/?offering=leadership-coaching', $params['cancel_url'] );
	}
}
