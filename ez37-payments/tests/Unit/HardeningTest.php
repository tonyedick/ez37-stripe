<?php
/**
 * Regression tests for the Phase 9 security audit fixes.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use Brain\Monkey\Filters;
use EZ37\Payments\Config;
use EZ37\Payments\Support\ClientIp;
use EZ37\Payments\Support\Logger;

final class HardeningTest extends TestCase {

	/**
	 * Saved REMOTE_ADDR.
	 *
	 * @var mixed
	 */
	private $remote_addr;

	protected function setUp(): void {
		parent::setUp();
		$this->remote_addr = $_SERVER['REMOTE_ADDR'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Saved and restored verbatim by the test.
	}

	protected function tearDown(): void {
		if ( null === $this->remote_addr ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->remote_addr;
		}
		parent::tearDown();
	}

	// SEC-A1: download tokens never reach the log.
	public function test_logger_redacts_download_tokens(): void {
		$lines  = array();
		$logger = new Logger(
			'debug',
			static function ( string $line ) use ( &$lines ): void {
				$lines[] = $line;
			}
		);

		$token = str_repeat( 'ab', 32 );
		$logger->error( 'Link https://ez37.test/?ez37_access=' . $token . ' failed', array( 'url' => 'https://ez37.test/?ez37_access=' . $token ) );

		$this->assertStringNotContainsString( $token, $lines[0] );
		$this->assertStringContainsString( 'ez37_access=[redacted]', $lines[0] );
	}

	// SEC-A2: a private folder inside the WordPress folder is refused.
	public function test_private_folder_inside_wordpress_is_refused(): void {
		$inside = realpath( (string) constant( 'ABSPATH' ) );
		$this->assertIsString( $inside );

		$config = new Config( array( 'EZ37_PAYMENTS_PRIVATE_DIR' => $inside ) );
		$this->assertNull( $config->private_dir() );
		$this->assertNotEmpty( array_filter( $config->warnings(), static fn ( string $w ): bool => str_contains( $w, 'OUTSIDE the WordPress folder' ) ) );

		$sub = $inside . '/wp-admin';
		$this->assertNull( ( new Config( array( 'EZ37_PAYMENTS_PRIVATE_DIR' => $sub ) ) )->private_dir() );
	}

	public function test_private_folder_outside_wordpress_is_accepted(): void {
		$outside = realpath( __DIR__ );
		$this->assertSame( $outside, ( new Config( array( 'EZ37_PAYMENTS_PRIVATE_DIR' => __DIR__ ) ) )->private_dir() );
	}

	public function test_sibling_folder_with_shared_prefix_is_not_treated_as_inside(): void {
		// e.g. ABSPATH /var/www/html/wordpress and private /var/www/html/wordpress-private.
		$base    = sys_get_temp_dir() . '/ez37-prefix-' . bin2hex( random_bytes( 3 ) );
		$sibling = $base . '-private';
		mkdir( $sibling, 0700, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir

		$this->assertSame( realpath( $sibling ), ( new Config( array( 'EZ37_PAYMENTS_PRIVATE_DIR' => $sibling ) ) )->private_dir() );

		rmdir( $sibling ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}

	// SEC-A3: client IP for rate limiting.
	public function test_client_ip_defaults_to_remote_addr(): void {
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$this->assertSame( '203.0.113.7', ClientIp::get() );
	}

	public function test_client_ip_can_be_supplied_by_the_deployer(): void {
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		Filters\expectApplied( 'ez37_payments_client_ip' )->once()->with( '10.0.0.1' )->andReturn( '198.51.100.20' );

		$this->assertSame( '198.51.100.20', ClientIp::get() );
	}

	public function test_invalid_client_ip_becomes_unknown(): void {
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		Filters\expectApplied( 'ez37_payments_client_ip' )->andReturn( '1.2.3.4, 5.6.7.8' );
		$this->assertSame( 'unknown', ClientIp::get() );

		unset( $_SERVER['REMOTE_ADDR'] );
		Filters\expectApplied( 'ez37_payments_client_ip' )->andReturnFirstArg();
		$this->assertSame( 'unknown', ClientIp::get() );
	}
}
