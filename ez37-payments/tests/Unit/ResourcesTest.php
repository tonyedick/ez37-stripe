<?php
/**
 * Tests for ResourceCatalogue, Resource and AccessController::authorise().
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use EZ37\Payments\Config;
use EZ37\Payments\Resources\AccessController;
use EZ37\Payments\Resources\EntitlementRepository;
use EZ37\Payments\Resources\ResourceCatalogue;
use EZ37\Payments\Checkout\RateLimiter;
use EZ37\Payments\Support\Logger;
use Mockery;
use Mockery\MockInterface;

final class ResourcesTest extends TestCase {

	/**
	 * Temporary private folder.
	 *
	 * @var string
	 */
	private string $dir;

	/**
	 * Entitlements mock.
	 *
	 * @var EntitlementRepository&MockInterface
	 */
	private $entitlements;

	protected function setUp(): void {
		parent::setUp();

		$this->dir = sys_get_temp_dir() . '/ez37-private-' . bin2hex( random_bytes( 4 ) );
		mkdir( $this->dir . '/leadership-coaching', 0700, true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_mkdir
		file_put_contents( $this->dir . '/leadership-coaching/brochure.pdf', '%PDF-1.4 test' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents( sys_get_temp_dir() . '/ez37-outside.txt', 'secret' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->entitlements = Mockery::mock( EntitlementRepository::class );
	}

	protected function tearDown(): void {
		@unlink( $this->dir . '/leadership-coaching/brochure.pdf' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		@rmdir( $this->dir . '/leadership-coaching' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		@rmdir( $this->dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		parent::tearDown();
	}

	/**
	 * A valid definition.
	 *
	 * @param array<string, mixed> $overrides Changes.
	 * @return array<string, mixed>
	 */
	private static function definition( array $overrides = array() ): array {
		return array_merge(
			array(
				'type'        => 'private_file',
				'label'       => 'Brochure',
				'path'        => 'leadership-coaching/brochure.pdf',
				'attach'      => true,
				'max_attach'  => 5242880,
				'access_days' => 7,
			),
			$overrides
		);
	}

	public function test_valid_resource_resolves_inside_private_folder(): void {
		$resource = ( new ResourceCatalogue( array( 'brochure' => self::definition() ) ) )->find( 'brochure' );

		$this->assertNotNull( $resource );
		$this->assertSame( realpath( $this->dir . '/leadership-coaching/brochure.pdf' ), $resource->file_path( realpath( $this->dir ) ) );
		$this->assertSame( 'brochure.pdf', $resource->download_name() );
	}

	public function test_missing_file_or_folder_resolves_to_null(): void {
		$resource = ( new ResourceCatalogue( array( 'brochure' => self::definition( array( 'path' => 'leadership-coaching/nope.pdf' ) ) ) ) )->find( 'brochure' );

		$this->assertNull( $resource->file_path( realpath( $this->dir ) ) );
		$this->assertNull( $resource->file_path( null ) );
	}

	public function test_unsafe_paths_are_rejected(): void {
		foreach ( array( '../ez37-outside.txt', 'leadership-coaching/../../ez37-outside.txt', '/etc/passwd', '', 'a//b', '.hidden', 'a b.pdf' ) as $path ) {
			$catalogue = new ResourceCatalogue( array( 'brochure' => self::definition( array( 'path' => $path ) ) ) );
			$this->assertNull( $catalogue->find( 'brochure' ), $path );
			$this->assertCount( 1, $catalogue->errors(), $path );
		}
	}

	public function test_invalid_definitions_are_rejected(): void {
		foreach ( array(
			array( 'type' => 'url' ),
			array( 'label' => '' ),
			array( 'attach' => 'yes' ),
			array( 'max_attach' => -1 ),
			array( 'access_days' => 0 ),
			array( 'access_days' => '7' ),
		) as $overrides ) {
			$this->assertNull( ( new ResourceCatalogue( array( 'brochure' => self::definition( $overrides ) ) ) )->find( 'brochure' ), (string) json_encode( $overrides ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		}
	}

	public function test_shipped_resources_config_is_valid(): void {
		$catalogue = ResourceCatalogue::from_file( dirname( __DIR__, 2 ) . '/config/resources.php' );

		$this->assertSame( array(), $catalogue->errors() );
		$this->assertNotNull( $catalogue->find( 'leadership-coaching-brochure' ) );
		$this->assertSame( 7, $catalogue->find( 'leadership-coaching-brochure' )->access_days );
	}

	private function access(): AccessController {
		return new AccessController(
			new Config( array( 'EZ37_PAYMENTS_PRIVATE_DIR' => $this->dir ) ),
			$this->entitlements,
			new ResourceCatalogue( array( 'brochure' => self::definition() ) ),
			new RateLimiter(),
			new Logger( 'error', static function (): void {} )
		);
	}

	/**
	 * An entitlement row joined with its transaction.
	 *
	 * @param array<string, mixed> $overrides Changes.
	 * @return array<string, mixed>
	 */
	private static function entitlement( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'              => '9',
				'resource_key'    => 'brochure',
				'status'          => 'active',
				'expires_at'      => '2026-11-20 10:00:00',
				'payment_status'  => 'paid',
				'review_required' => '0',
			),
			$overrides
		);
	}

	public function test_valid_token_grants_access_and_counts_it(): void {
		$token = AccessController::new_token();
		$this->entitlements->shouldReceive( 'find_by_token_hash' )->with( $token['hash'] )->andReturn( self::entitlement() );
		$this->entitlements->shouldReceive( 'record_access' )->once()->with( 9 )->andReturn( true );

		$result = $this->access()->authorise( $token['token'], '2026-11-13 10:00:00' );

		$this->assertSame( 'ok', $result['result'] );
		$this->assertSame( realpath( $this->dir . '/leadership-coaching/brochure.pdf' ), $result['file'] );
	}

	public function test_tokens_are_random_and_only_hashes_are_looked_up(): void {
		$a = AccessController::new_token();
		$b = AccessController::new_token();

		$this->assertMatchesRegularExpression( AccessController::TOKEN_PATTERN, $a['token'] );
		$this->assertNotSame( $a['token'], $b['token'] );
		$this->assertSame( hash( 'sha256', $a['token'] ), $a['hash'] );
		$this->assertNotSame( $a['token'], $a['hash'] );
	}

	public function test_malformed_token_never_queries(): void {
		$this->entitlements->shouldNotReceive( 'find_by_token_hash' );

		foreach ( array( '', 'abc', str_repeat( 'g', 64 ), str_repeat( 'A', 64 ), str_repeat( 'a', 65 ) ) as $token ) {
			$this->assertSame( 'invalid', $this->access()->authorise( $token, '2026-11-13 10:00:00' )['result'] );
		}
	}

	/**
	 * Situations that must not serve the file.
	 *
	 * @return array<string, array{0: array<string, mixed>|null, 1: string}>
	 */
	public static function refusals(): array {
		return array(
			'unknown token' => array( null, 'invalid' ),
			'revoked'       => array( array( 'status' => 'revoked' ), 'invalid' ),
			'not paid'      => array( array( 'payment_status' => 'pending' ), 'not_paid' ),
			'under review'  => array( array( 'review_required' => '1' ), 'not_paid' ),
			'expired'       => array( array( 'expires_at' => '2026-11-13 09:59:59' ), 'expired' ),
			'expires now'   => array( array( 'expires_at' => '2026-11-13 10:00:00' ), 'expired' ),
			'unknown file'  => array( array( 'resource_key' => 'gone' ), 'unavailable' ),
		);
	}

	/**
	 * Refused access.
	 *
	 * @dataProvider refusals
	 *
	 * @param array<string, mixed>|null $overrides Entitlement changes, or null for no row.
	 * @param string                    $expected  Expected result.
	 */
	public function test_access_is_refused( ?array $overrides, string $expected ): void {
		$this->entitlements->shouldReceive( 'find_by_token_hash' )->andReturn( null === $overrides ? null : self::entitlement( $overrides ) );
		$this->entitlements->shouldNotReceive( 'record_access' );

		$this->assertSame( $expected, $this->access()->authorise( str_repeat( 'a', 64 ), '2026-11-13 10:00:00' )['result'] );
	}

	public function test_download_limit_reached(): void {
		$this->entitlements->shouldReceive( 'find_by_token_hash' )->andReturn( self::entitlement() );
		$this->entitlements->shouldReceive( 'record_access' )->andReturn( false );

		$this->assertSame( 'limit_reached', $this->access()->authorise( str_repeat( 'a', 64 ), '2026-11-13 10:00:00' )['result'] );
	}
}
