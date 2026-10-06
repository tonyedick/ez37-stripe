<?php
/**
 * Tests for Logger.
 *
 * Secret-shaped strings are FAKE and assembled at runtime.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use EZ37\Payments\Support\Logger;

final class LoggerTest extends TestCase {

	/**
	 * Captured lines.
	 *
	 * @var list<string>
	 */
	private array $lines = array();

	private function logger( string $level = 'debug' ): Logger {
		$this->lines = array();

		return new Logger(
			$level,
			function ( string $line ): void {
				$this->lines[] = $line;
			}
		);
	}

	private static function fake( string $prefix ): string {
		return $prefix . 'FAKEabc123XYZ7890';
	}

	public function test_respects_minimum_level(): void {
		$logger = $this->logger( 'warning' );

		$logger->debug( 'd' );
		$logger->info( 'i' );
		$logger->warning( 'w' );
		$logger->error( 'e' );

		$this->assertCount( 2, $this->lines );
		$this->assertStringContainsString( 'WARNING: w', $this->lines[0] );
		$this->assertStringContainsString( 'ERROR: e', $this->lines[1] );
	}

	public function test_lines_are_prefixed(): void {
		$this->logger()->info( 'hello' );

		$this->assertSame( '[ez37-payments] INFO: hello', $this->lines[0] );
	}

	public function test_redacts_stripe_keys_in_messages(): void {
		$logger = $this->logger();

		foreach ( array( 'sk_live_', 'sk_test_', 'rk_live_', 'rk_test_', 'whsec_' ) as $prefix ) {
			$logger->error( 'Key was ' . self::fake( $prefix ) . ' oops' );
		}

		$all = implode( "\n", $this->lines );
		$this->assertStringNotContainsString( 'FAKEabc123', $all );
		$this->assertStringContainsString( 'sk_live_[redacted]', $all );
		$this->assertStringContainsString( 'whsec_[redacted]', $all );
	}

	public function test_redacts_checkout_client_secret_and_signature_header(): void {
		$this->logger()->error(
			'cs_test_a1b2c3_secret_FAKEabc123 and t=1700000000,v1=abcdef0123456789,v0=0123abcdef'
		);

		$this->assertStringNotContainsString( 'FAKEabc123', $this->lines[0] );
		$this->assertStringNotContainsString( 'abcdef0123456789', $this->lines[0] );
	}

	public function test_does_not_redact_ordinary_stripe_ids(): void {
		$this->logger()->info( 'Session cs_test_a1B2c3 for pi_3AbCdEf and evt_1XyZ' );

		$this->assertStringContainsString( 'cs_test_a1B2c3', $this->lines[0] );
		$this->assertStringContainsString( 'pi_3AbCdEf', $this->lines[0] );
		$this->assertStringContainsString( 'evt_1XyZ', $this->lines[0] );
	}

	public function test_masks_email_addresses(): void {
		$this->logger()->info( 'Paid by jane.doe@example.com', array( 'email' => 'john@example.org' ) );

		$this->assertStringNotContainsString( 'jane.doe@', $this->lines[0] );
		$this->assertStringNotContainsString( 'john@', $this->lines[0] );
		$this->assertStringContainsString( 'j***@example.com', $this->lines[0] );
		$this->assertStringContainsString( 'j***@example.org', $this->lines[0] );
	}

	public function test_redacts_sensitive_context_keys_recursively(): void {
		$this->logger()->info(
			'ctx',
			array(
				'webhook_secret' => 'anything',
				'Stripe-Signature' => 'anything',
				'nested'         => array(
					'password'     => 'hunter2',
					'access_token' => 'raw-token',
					'session_id'   => 'cs_test_keepme',
				),
			)
		);

		$line = $this->lines[0];
		$this->assertStringNotContainsString( 'anything', $line );
		$this->assertStringNotContainsString( 'hunter2', $line );
		$this->assertStringNotContainsString( 'raw-token', $line );
		$this->assertStringContainsString( 'cs_test_keepme', $line );
	}

	public function test_exceptions_in_context_are_summarised_and_redacted(): void {
		$this->logger()->error( 'failed', array( 'exception' => new \RuntimeException( 'bad key ' . self::fake( 'sk_test_' ) ) ) );

		$this->assertStringContainsString( 'RuntimeException: bad key sk_test_[redacted]', $this->lines[0] );
	}

	public function test_objects_are_not_dumped(): void {
		$this->logger()->info( 'obj', array( 'thing' => new \stdClass() ) );

		$this->assertStringContainsString( '[stdClass]', $this->lines[0] );
	}

	public function test_newlines_are_stripped_to_prevent_log_injection(): void {
		$this->logger()->info( "line one\n[ez37-payments] ERROR: forged" );

		$this->assertCount( 1, $this->lines );
		$this->assertStringNotContainsString( "\n", $this->lines[0] );
	}

	public function test_long_lines_are_truncated(): void {
		$this->logger()->info( str_repeat( 'x', 5000 ) );

		$this->assertLessThan( 2100, strlen( $this->lines[0] ) );
		$this->assertStringEndsWith( '[truncated]', $this->lines[0] );
	}
}
