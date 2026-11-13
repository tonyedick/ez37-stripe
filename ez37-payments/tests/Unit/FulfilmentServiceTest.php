<?php
/**
 * Tests for FulfilmentService.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use Brain\Monkey\Functions;
use EZ37\Payments\Config;
use EZ37\Payments\Email\Mailer;
use EZ37\Payments\Fulfilment\FulfilmentService;
use EZ37\Payments\Offerings\OfferingCatalogue;
use EZ37\Payments\Resources\EntitlementRepository;
use EZ37\Payments\Resources\ResourceCatalogue;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Transactions\TransactionRepository;
use Mockery;
use Mockery\MockInterface;

final class FulfilmentServiceTest extends TestCase {

	/**
	 * Temporary private folder.
	 *
	 * @var string
	 */
	private string $dir;

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
	 * @var Mailer&MockInterface
	 */
	private $mailer;

	protected function setUp(): void {
		parent::setUp();

		$this->dir = sys_get_temp_dir() . '/ez37-fulfil-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->dir, 0700, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		file_put_contents( $this->dir . '/brochure.pdf', '%PDF-1.4 test' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->transactions = Mockery::mock( TransactionRepository::class );
		$this->entitlements = Mockery::mock( EntitlementRepository::class );
		$this->mailer       = Mockery::mock( Mailer::class );

		Functions\when( 'home_url' )->alias( static fn ( $p = '' ) => 'https://ez37.test' . $p );
		Functions\when( 'add_query_arg' )->alias( static fn ( $k, $v, $u ) => $u . '?' . $k . '=' . $v );

		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 86400 ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constant, faked for unit tests.
		}
	}

	protected function tearDown(): void {
		@unlink( $this->dir . '/brochure.pdf' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		@rmdir( $this->dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		parent::tearDown();
	}

	private function service( string $resource_path = 'brochure.pdf' ): FulfilmentService {
		return new FulfilmentService(
			new Config( array( 'EZ37_PAYMENTS_PRIVATE_DIR' => $this->dir ) ),
			$this->transactions,
			new OfferingCatalogue(
				array(
					'leadership-coaching' => array(
						'name'       => 'Leadership Coaching',
						'active'     => true,
						'currency'   => 'usd',
						'amount'     => 100,
						'price_ids'  => array(
							'test' => 'price_TEST',
							'live' => '',
						),
						'fulfilment' => array(
							'handler'   => 'resource_access',
							'resources' => array( 'brochure' ),
						),
					),
				)
			),
			new ResourceCatalogue(
				array(
					'brochure' => array(
						'type'        => 'private_file',
						'label'       => 'Brochure',
						'path'        => $resource_path,
						'attach'      => true,
						'max_attach'  => 5242880,
						'access_days' => 7,
					),
				)
			),
			$this->entitlements,
			$this->mailer,
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
				'id'                  => '42',
				'public_id'           => 'pub42',
				'offering_key'        => 'leadership-coaching',
				'customer_email'      => 'jane@example.com',
				'fulfilment_attempts' => '1',
			),
			$overrides
		);
	}

	public function test_successful_fulfilment_grants_access_emails_and_completes(): void {
		$this->transactions->shouldReceive( 'claim_fulfilment' )->once()->with( 42, FulfilmentService::LOCK_SECONDS )->andReturn( true );
		$this->transactions->shouldReceive( 'find_by_id' )->with( 42 )->andReturn( self::transaction() );
		$this->mailer->shouldReceive( 'customer_email_sent' )->with( 42 )->andReturn( false );
		$this->entitlements->shouldReceive( 'grant' )
			->once()
			->with( 42, 'brochure', 'jane@example.com', Mockery::pattern( '/^[a-f0-9]{64}$/' ), Mockery::type( 'string' ) );

		$this->mailer->shouldReceive( 'send_customer_fulfilment' )
			->once()
			->with(
				Mockery::type( 'array' ),
				Mockery::any(),
				Mockery::on(
					static fn ( array $grants ) => 1 === count( $grants )
						&& 1 === preg_match( '#^https://ez37\.test/\?ez37_access=[a-f0-9]{64}$#', $grants[0]['url'] )
						&& str_ends_with( $grants[0]['file'], '/brochure.pdf' )
						&& true === $grants[0]['attach']
				)
			)
			->andReturn( true );
		$this->mailer->shouldReceive( 'send_admin_sale_notice' )->once();
		$this->transactions->shouldReceive( 'complete_fulfilment' )->once()->with( 42 );

		$this->assertTrue( $this->service()->fulfil( 42 ) );
	}

	public function test_link_expires_after_configured_days(): void {
		$this->transactions->shouldReceive( 'claim_fulfilment' )->andReturn( true );
		$this->transactions->shouldReceive( 'find_by_id' )->andReturn( self::transaction() );
		$this->mailer->shouldReceive( 'customer_email_sent' )->andReturn( false );
		$this->mailer->shouldReceive( 'send_customer_fulfilment' )->andReturn( true );
		$this->mailer->shouldReceive( 'send_admin_sale_notice' );
		$this->transactions->shouldReceive( 'complete_fulfilment' );

		$expires = null;
		$this->entitlements->shouldReceive( 'grant' )->andReturnUsing(
			static function ( $id, $key, $email, $hash, $at ) use ( &$expires ): void {
				$expires = $at;
			}
		);

		$this->service()->fulfil( 42 );

		$this->assertEqualsWithDelta( time() + 7 * 86400, strtotime( $expires . ' UTC' ), 5 );
	}

	public function test_not_claimed_means_nothing_happens(): void {
		$this->transactions->shouldReceive( 'claim_fulfilment' )->andReturn( false );
		$this->transactions->shouldNotReceive( 'find_by_id' );
		$this->mailer->shouldNotReceive( 'send_customer_fulfilment' );

		$this->assertFalse( $this->service()->fulfil( 42 ) );
	}

	public function test_email_already_sent_completes_without_new_links(): void {
		$this->transactions->shouldReceive( 'claim_fulfilment' )->andReturn( true );
		$this->transactions->shouldReceive( 'find_by_id' )->andReturn( self::transaction() );
		$this->mailer->shouldReceive( 'customer_email_sent' )->andReturn( true );
		$this->entitlements->shouldNotReceive( 'grant' );
		$this->mailer->shouldNotReceive( 'send_customer_fulfilment' );
		$this->mailer->shouldReceive( 'send_admin_sale_notice' )->once();
		$this->transactions->shouldReceive( 'complete_fulfilment' )->once();

		$this->assertTrue( $this->service()->fulfil( 42 ) );
	}

	public function test_missing_file_fails_retryably_and_never_touches_payment(): void {
		$this->transactions->shouldReceive( 'claim_fulfilment' )->andReturn( true );
		$this->transactions->shouldReceive( 'find_by_id' )->andReturn( self::transaction() );
		$this->mailer->shouldReceive( 'customer_email_sent' )->andReturn( false );
		$this->entitlements->shouldNotReceive( 'grant' );
		$this->mailer->shouldNotReceive( 'send_customer_fulfilment' );
		$this->transactions->shouldReceive( 'fail_fulfilment' )->once()->with( 42, false, Mockery::pattern( '/Resource unavailable/' ) );
		$this->transactions->shouldNotReceive( 'transition', 'complete_fulfilment' );

		$this->assertFalse( $this->service( 'missing.pdf' )->fulfil( 42 ) );
	}

	public function test_email_failure_fails_retryably(): void {
		$this->transactions->shouldReceive( 'claim_fulfilment' )->andReturn( true );
		$this->transactions->shouldReceive( 'find_by_id' )->andReturn( self::transaction() );
		$this->mailer->shouldReceive( 'customer_email_sent' )->andReturn( false );
		$this->entitlements->shouldReceive( 'grant' );
		$this->mailer->shouldReceive( 'send_customer_fulfilment' )->andThrow( new \RuntimeException( 'SMTP down' ) );
		$this->transactions->shouldReceive( 'fail_fulfilment' )->once()->with( 42, false, Mockery::type( 'string' ) );
		$this->transactions->shouldNotReceive( 'complete_fulfilment' );

		$this->assertFalse( $this->service()->fulfil( 42 ) );
	}

	public function test_last_attempt_fails_permanently(): void {
		$this->transactions->shouldReceive( 'claim_fulfilment' )->andReturn( true );
		$this->transactions->shouldReceive( 'find_by_id' )->andReturn( self::transaction( array( 'fulfilment_attempts' => (string) FulfilmentService::MAX_ATTEMPTS ) ) );
		$this->mailer->shouldReceive( 'customer_email_sent' )->andReturn( false );
		$this->entitlements->shouldReceive( 'grant' );
		$this->mailer->shouldReceive( 'send_customer_fulfilment' )->andThrow( new \RuntimeException( 'SMTP down' ) );
		$this->transactions->shouldReceive( 'fail_fulfilment' )->once()->with( 42, true, Mockery::type( 'string' ) );

		$this->assertFalse( $this->service()->fulfil( 42 ) );
	}

	public function test_offering_removed_from_config_fails(): void {
		$this->transactions->shouldReceive( 'claim_fulfilment' )->andReturn( true );
		$this->transactions->shouldReceive( 'find_by_id' )->andReturn( self::transaction( array( 'offering_key' => 'gone' ) ) );
		$this->transactions->shouldReceive( 'fail_fulfilment' )->once();

		$this->assertFalse( $this->service()->fulfil( 42 ) );
	}

	public function test_sweep_retries_due_transactions(): void {
		$this->transactions->shouldReceive( 'find_fulfilment_due' )->with( FulfilmentService::MAX_ATTEMPTS, 20 )->andReturn( array( 1, 2 ) );
		$this->transactions->shouldReceive( 'claim_fulfilment' )->andReturn( false );

		$this->assertSame( 0, $this->service()->sweep() );
	}

	public function test_paid_action_queues_a_background_job(): void {
		Functions\expect( 'wp_schedule_single_event' )->once()->with( Mockery::type( 'int' ), FulfilmentService::JOB_HOOK, array( 42 ) );

		$this->service()->queue( 42 );
		$this->addToAssertionCount( 1 );
	}

	public function test_retry_resets_then_fulfils(): void {
		$this->transactions->shouldReceive( 'reset_fulfilment' )->once()->with( 42 )->andReturn( true );
		$this->transactions->shouldReceive( 'claim_fulfilment' )->once()->andReturn( false );

		$this->assertFalse( $this->service()->retry( 42 ) );
	}

	public function test_retry_does_nothing_when_reset_is_refused(): void {
		$this->transactions->shouldReceive( 'reset_fulfilment' )->once()->andReturn( false );
		$this->transactions->shouldNotReceive( 'claim_fulfilment' );

		$this->assertFalse( $this->service()->retry( 42 ) );
	}

	public function test_resend_issues_a_new_link_and_sends_again(): void {
		$this->transactions->shouldReceive( 'reset_fulfilment' )->once()->with( 42 )->andReturn( true );
		$this->mailer->shouldReceive( 'allow_customer_resend' )->once()->with( 42 )->ordered();
		$this->transactions->shouldReceive( 'claim_fulfilment' )->andReturn( true );
		$this->transactions->shouldReceive( 'find_by_id' )->andReturn( self::transaction() );
		$this->mailer->shouldReceive( 'customer_email_sent' )->andReturn( false )->ordered();
		$this->entitlements->shouldReceive( 'grant' )->once()->with( 42, 'brochure', 'jane@example.com', Mockery::pattern( '/^[a-f0-9]{64}$/' ), Mockery::type( 'string' ) );
		$this->mailer->shouldReceive( 'send_customer_fulfilment' )->once()->andReturn( true );
		$this->mailer->shouldReceive( 'send_admin_sale_notice' )->once();
		$this->transactions->shouldReceive( 'complete_fulfilment' )->once()->with( 42 );

		$this->assertTrue( $this->service()->resend( 42 ) );
	}

	public function test_resend_refused_for_ineligible_transaction(): void {
		$this->transactions->shouldReceive( 'reset_fulfilment' )->once()->andReturn( false );
		$this->mailer->shouldNotReceive( 'allow_customer_resend' );

		$this->assertFalse( $this->service()->resend( 42 ) );
	}
}
