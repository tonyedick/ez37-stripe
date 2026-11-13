<?php
/**
 * Tests for WebhookController (signature verification uses the real Stripe SDK).
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use EZ37\Payments\Config;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Webhooks\EventProcessor;
use EZ37\Payments\Webhooks\WebhookController;
use Mockery;
use Mockery\MockInterface;

final class WebhookControllerTest extends TestCase {

	/**
	 * Fake signing secret, assembled at runtime.
	 */
	private const SECRET_SUFFIX = 'FAKE0000000000000000';

	/**
	 * Processor mock.
	 *
	 * @var EventProcessor&MockInterface
	 */
	private $processor;

	/**
	 * Captured log lines.
	 *
	 * @var list<string>
	 */
	private array $log = array();

	protected function setUp(): void {
		parent::setUp();
		$this->processor = Mockery::mock( EventProcessor::class );
		$this->log       = array();
	}

	private static function secret(): string {
		return 'whsec_' . self::SECRET_SUFFIX;
	}

	private function controller( ?string $secret = null ): WebhookController {
		$config = new Config(
			array(
				'EZ37_STRIPE_MODE'                => 'test',
				'EZ37_STRIPE_TEST_SECRET_KEY'     => 'sk_test_' . self::SECRET_SUFFIX,
				'EZ37_STRIPE_TEST_WEBHOOK_SECRET' => $secret ?? self::secret(),
			)
		);

		return new WebhookController(
			$config,
			$this->processor,
			new Logger(
				'debug',
				function ( string $line ): void {
					$this->log[] = $line;
				}
			)
		);
	}

	private static function payload( string $type = 'checkout.session.completed' ): string {
		return (string) json_encode( // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- No WordPress in unit tests.
			array(
				'id'       => 'evt_test_1',
				'object'   => 'event',
				'type'     => $type,
				'livemode' => false,
				'data'     => array(
					'object' => array(
						'id'     => 'cs_test_1',
						'object' => 'checkout.session',
					),
				),
			)
		);
	}

	private static function sign( string $payload, ?int $timestamp = null, ?string $secret = null ): string {
		$timestamp = $timestamp ?? time();

		return 't=' . $timestamp . ',v1=' . hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret ?? self::secret() );
	}

	public function test_valid_signature_is_processed(): void {
		$payload = self::payload();
		$this->processor->shouldReceive( 'process' )
			->once()
			->with( Mockery::on( static fn ( $event ) => 'evt_test_1' === $event['id'] && 'cs_test_1' === $event['data']['object']['id'] ) )
			->andReturn( 'processed' );

		$result = $this->controller()->receive( $payload, self::sign( $payload ) );

		$this->assertSame( 200, $result['status'] );
		$this->assertSame( 'processed', $result['result'] );
	}

	public function test_wrong_secret_is_rejected(): void {
		$payload = self::payload();
		$this->processor->shouldNotReceive( 'process' );

		$result = $this->controller()->receive( $payload, self::sign( $payload, null, 'whsec_OTHER' . self::SECRET_SUFFIX ) );

		$this->assertSame( 400, $result['status'] );
		$this->assertSame( 'invalid_signature', $result['result'] );
	}

	public function test_tampered_body_is_rejected(): void {
		$payload  = self::payload();
		$header   = self::sign( $payload );
		$tampered = str_replace( 'cs_test_1', 'cs_test_2', $payload );
		$this->processor->shouldNotReceive( 'process' );

		$this->assertSame( 400, $this->controller()->receive( $tampered, $header )['status'] );
	}

	public function test_missing_signature_header_is_rejected(): void {
		$this->processor->shouldNotReceive( 'process' );

		$this->assertSame( 400, $this->controller()->receive( self::payload(), '' )['status'] );
	}

	public function test_garbage_signature_header_is_rejected(): void {
		$this->processor->shouldNotReceive( 'process' );

		$this->assertSame( 400, $this->controller()->receive( self::payload(), 'not-a-signature' )['status'] );
	}

	public function test_replayed_old_event_is_rejected(): void {
		$payload = self::payload();
		$this->processor->shouldNotReceive( 'process' );

		$result = $this->controller()->receive( $payload, self::sign( $payload, time() - 600 ) );

		$this->assertSame( 400, $result['status'], 'Older than the 5-minute tolerance.' );
	}

	public function test_invalid_json_with_valid_signature_is_rejected(): void {
		$payload = '{not json';
		$this->processor->shouldNotReceive( 'process' );

		$result = $this->controller()->receive( $payload, self::sign( $payload ) );

		$this->assertSame( 400, $result['status'] );
		$this->assertSame( 'invalid_payload', $result['result'] );
	}

	public function test_oversized_body_is_rejected_before_verification(): void {
		$this->processor->shouldNotReceive( 'process' );

		$this->assertSame( 413, $this->controller()->receive( str_repeat( 'x', WebhookController::MAX_BODY_BYTES + 1 ), 't=1,v1=x' )['status'] );
	}

	public function test_missing_signing_secret_returns_500_so_stripe_retries(): void {
		$this->processor->shouldNotReceive( 'process' );

		$result = $this->controller( 'not-a-secret' )->receive( self::payload(), 't=1,v1=x' );

		$this->assertSame( 500, $result['status'] );
	}

	public function test_processing_failure_returns_500_so_stripe_retries(): void {
		$payload = self::payload();
		$this->processor->shouldReceive( 'process' )->andThrow( new \RuntimeException( 'DB down' ) );

		$this->assertSame( 500, $this->controller()->receive( $payload, self::sign( $payload ) )['status'] );
	}

	public function test_secrets_never_reach_the_log(): void {
		$payload = self::payload();
		$this->processor->shouldNotReceive( 'process' );

		$this->controller()->receive( $payload, self::sign( $payload, null, 'whsec_OTHER' . self::SECRET_SUFFIX ) );

		$this->assertStringNotContainsString( self::SECRET_SUFFIX, implode( "\n", $this->log ) );
	}
}
