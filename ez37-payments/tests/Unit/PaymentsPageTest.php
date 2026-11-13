<?php
/**
 * Tests for the wp-admin Payments screen.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use Brain\Monkey\Functions;
use EZ37\Payments\Admin\PaymentsPage;
use EZ37\Payments\Config;
use EZ37\Payments\Email\EmailRepository;
use EZ37\Payments\Fulfilment\FulfilmentService;
use EZ37\Payments\Plugin;
use EZ37\Payments\Resources\EntitlementRepository;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Transactions\TransactionRepository;
use EZ37\Payments\Webhooks\EventRepository;
use Mockery;
use Mockery\MockInterface;

final class PaymentsPageTest extends TestCase {

	/**
	 * Mocks.
	 *
	 * @var TransactionRepository&MockInterface
	 */
	private $transactions;

	/**
	 * Mocks.
	 *
	 * @var EntitlementRepository&MockInterface
	 */
	private $entitlements;

	/**
	 * Mocks.
	 *
	 * @var FulfilmentService&MockInterface
	 */
	private $fulfilment;

	/**
	 * Captured redirect URL.
	 *
	 * @var string|null
	 */
	private ?string $redirected = null;

	protected function setUp(): void {
		parent::setUp();

		$this->transactions = Mockery::mock( TransactionRepository::class );
		$this->entitlements = Mockery::mock( EntitlementRepository::class );
		$this->fulfilment   = Mockery::mock( FulfilmentService::class );

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'absint' )->alias( static fn ( $v ) => abs( (int) $v ) );
		Functions\when( 'sanitize_key' )->alias( static fn ( $v ) => strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', (string) $v ) ) );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'admin_url' )->alias( static fn ( $p = '' ) => 'https://ez37.test/wp-admin/' . $p );
		Functions\when( 'add_query_arg' )->alias( static fn ( array $args, string $url ) => $url . '?' . http_build_query( $args ) );
		Functions\when( 'get_current_user_id' )->justReturn( 1 );
		Functions\when( 'wp_die' )->alias(
			static function ( $message = '', $title = '', $args = array() ): void {
				throw new \RuntimeException( 'wp_die:' . ( $args['response'] ?? 500 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Test double.
			}
		);
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( string $url ): void {
				$this->redirected = $url;
				throw new \RuntimeException( 'redirect' );
			}
		);
	}

	protected function tearDown(): void {
		$_POST = array();
		parent::tearDown();
	}

	private function page(): PaymentsPage {
		return new PaymentsPage(
			new Config( array() ),
			$this->transactions,
			Mockery::mock( EventRepository::class ),
			Mockery::mock( EmailRepository::class ),
			$this->entitlements,
			$this->fulfilment,
			new Logger( 'error', static function (): void {} )
		);
	}

	/**
	 * Transaction row.
	 *
	 * @param array<string, mixed> $overrides Changes.
	 * @return array<string, mixed>
	 */
	private static function transaction( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'                => '42',
				'public_id'         => 'abcdef1234567890abcdef1234',
				'payment_status'    => 'paid',
				'fulfilment_status' => 'completed',
				'review_required'   => '0',
			),
			$overrides
		);
	}

	private static function entitlement( string $status = EntitlementRepository::ACTIVE ): array {
		return array(
			'id'           => '1',
			'resource_key' => 'brochure',
			'status'       => $status,
		);
	}

	/**
	 * Run handle_action() and return what it ended with.
	 *
	 * @param array<string, string> $post POST data.
	 */
	private function act( array $post ): string {
		$_POST = $post;

		try {
			$this->page()->handle_action();
		} catch ( \RuntimeException $e ) {
			return $e->getMessage();
		}

		return 'returned';
	}

	// --- Which actions are offered -----------------------------------------

	public function test_completed_delivery_offers_resend_and_revoke(): void {
		$this->assertSame(
			array( 'resend', 'revoke' ),
			PaymentsPage::available_actions( self::transaction(), array( self::entitlement() ) )
		);
	}

	public function test_failed_delivery_offers_retry(): void {
		foreach ( array( 'failed', 'failed_permanent' ) as $status ) {
			$this->assertSame(
				array( 'retry' ),
				PaymentsPage::available_actions( self::transaction( array( 'fulfilment_status' => $status ) ), array() ),
				$status
			);
		}
	}

	public function test_unpaid_or_flagged_payments_offer_nothing(): void {
		foreach ( array( 'pending', 'processing', 'failed', 'expired' ) as $status ) {
			$this->assertSame( array(), PaymentsPage::available_actions( self::transaction( array( 'payment_status' => $status ) ), array( self::entitlement() ) ), $status );
		}

		$this->assertSame( array(), PaymentsPage::available_actions( self::transaction( array( 'review_required' => '1' ) ), array( self::entitlement() ) ) );
	}

	public function test_revoked_access_cannot_be_resent_or_retried(): void {
		$this->assertSame( array(), PaymentsPage::available_actions( self::transaction(), array( self::entitlement( EntitlementRepository::REVOKED ) ) ) );
		$this->assertSame(
			array(),
			PaymentsPage::available_actions( self::transaction( array( 'fulfilment_status' => 'failed' ) ), array( self::entitlement( EntitlementRepository::REVOKED ) ) )
		);
	}

	public function test_delivery_in_progress_offers_nothing_but_revoke(): void {
		$this->assertSame( array( 'revoke' ), PaymentsPage::available_actions( self::transaction( array( 'fulfilment_status' => 'in_progress' ) ), array( self::entitlement() ) ) );
		$this->assertSame( array(), PaymentsPage::available_actions( self::transaction( array( 'fulfilment_status' => 'pending' ) ), array() ) );
	}

	// --- Authorisation and request validation ------------------------------

	public function test_action_requires_capability(): void {
		Functions\expect( 'current_user_can' )->once()->with( Plugin::CAPABILITY )->andReturn( false );
		Functions\expect( 'check_admin_referer' )->never();
		$this->fulfilment->shouldNotReceive( 'retry', 'resend' );
		$this->entitlements->shouldNotReceive( 'revoke_for_transaction' );

		$this->assertSame(
			'wp_die:403',
			$this->act(
				array(
					'transaction' => '42',
					'ez37_do'     => 'revoke',
				)
			)
		);
	}

	public function test_unknown_action_or_missing_id_is_rejected(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )->never();

		$this->assertSame( 'wp_die:400', $this->act( array( 'transaction' => '42', 'ez37_do' => 'refund' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertSame( 'wp_die:400', $this->act( array( 'transaction' => '0', 'ez37_do' => 'retry' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
	}

	public function test_nonce_is_specific_to_action_and_transaction(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )
			->once()
			->with( 'ez37_payments_admin_revoke_42' )
			->andReturnUsing(
				static function (): void {
					throw new \RuntimeException( 'bad nonce' );
				}
			);
		$this->entitlements->shouldNotReceive( 'revoke_for_transaction' );

		$this->assertSame( 'bad nonce', $this->act( array( 'transaction' => '42', 'ez37_do' => 'revoke' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertNotSame( PaymentsPage::nonce_action( 'revoke', 42 ), PaymentsPage::nonce_action( 'revoke', 43 ) );
		$this->assertNotSame( PaymentsPage::nonce_action( 'revoke', 42 ), PaymentsPage::nonce_action( 'resend', 42 ) );
	}

	public function test_action_not_valid_for_current_state_is_refused(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		$this->transactions->shouldReceive( 'find_by_id' )->with( 42 )->andReturn( self::transaction() );
		$this->entitlements->shouldReceive( 'for_transaction' )->with( 42 )->andReturn( array( self::entitlement() ) );
		$this->fulfilment->shouldNotReceive( 'retry' );

		$this->assertSame( 'redirect', $this->act( array( 'transaction' => '42', 'ez37_do' => 'retry' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertStringContainsString( 'ez37_notice=not_allowed', (string) $this->redirected );
	}

	// --- The actions --------------------------------------------------------

	public function test_revoke_revokes_and_reports(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		$this->transactions->shouldReceive( 'find_by_id' )->andReturn( self::transaction() );
		$this->entitlements->shouldReceive( 'for_transaction' )->andReturn( array( self::entitlement() ) );
		$this->entitlements->shouldReceive( 'revoke_for_transaction' )->once()->with( 42 )->andReturn( 1 );

		$this->assertSame( 'redirect', $this->act( array( 'transaction' => '42', 'ez37_do' => 'revoke' ) ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertStringContainsString( 'ez37_notice=revoked', (string) $this->redirected );
		$this->assertStringContainsString( 'transaction=42', (string) $this->redirected );
	}

	public function test_resend_calls_fulfilment_and_reports_failure(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		$this->transactions->shouldReceive( 'find_by_id' )->andReturn( self::transaction() );
		$this->entitlements->shouldReceive( 'for_transaction' )->andReturn( array( self::entitlement() ) );
		$this->fulfilment->shouldReceive( 'resend' )->once()->with( 42 )->andReturn( false );

		$this->act( array( 'transaction' => '42', 'ez37_do' => 'resend' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertStringContainsString( 'ez37_notice=resend_failed', (string) $this->redirected );
	}

	public function test_retry_calls_fulfilment(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'check_admin_referer' )->justReturn( 1 );
		$this->transactions->shouldReceive( 'find_by_id' )->andReturn( self::transaction( array( 'fulfilment_status' => 'failed_permanent' ) ) );
		$this->entitlements->shouldReceive( 'for_transaction' )->andReturn( array() );
		$this->fulfilment->shouldReceive( 'retry' )->once()->with( 42 )->andReturn( true );

		$this->act( array( 'transaction' => '42', 'ez37_do' => 'retry' ) ); // phpcs:ignore WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
		$this->assertStringContainsString( 'ez37_notice=retried', (string) $this->redirected );
	}

	// --- Helpers ------------------------------------------------------------

	public function test_status_badge_escapes_unknown_values(): void {
		$this->assertStringContainsString( 'ez37-badge--good', PaymentsPage::status_badge( 'paid' ) );
		$this->assertStringNotContainsString( '<script>', PaymentsPage::status_badge( '<script>x</script>' ) );
	}

	public function test_stripe_dashboard_link_respects_mode(): void {
		$this->assertSame( 'https://dashboard.stripe.com/test/payments/pi_123', PaymentsPage::stripe_dashboard_url( 'pi_123', 'test' ) );
		$this->assertSame( 'https://dashboard.stripe.com/payments/pi_123', PaymentsPage::stripe_dashboard_url( 'pi_123', 'live' ) );
	}

	public function test_short_reference_matches_email(): void {
		$this->assertSame( 'ABCDEF1234', PaymentsPage::short_reference( 'abcdef1234567890abcdef1234' ) );
	}

	public function test_unknown_notice_code_shows_nothing(): void {
		$this->assertSame( '', PaymentsPage::notice_message( '<b>hi</b>' ) );
		$this->assertNotSame( '', PaymentsPage::notice_message( 'revoked' ) );
	}
}
