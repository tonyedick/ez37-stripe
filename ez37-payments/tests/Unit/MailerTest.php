<?php
/**
 * Tests for Mailer.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use Brain\Monkey\Functions;
use EZ37\Payments\Config;
use EZ37\Payments\Email\EmailRepository;
use EZ37\Payments\Email\Mailer;
use EZ37\Payments\Offerings\OfferingCatalogue;
use EZ37\Payments\Support\Logger;
use Mockery;
use Mockery\MockInterface;

final class MailerTest extends TestCase {

	/**
	 * Email log mock.
	 *
	 * @var EmailRepository&MockInterface
	 */
	private $log;

	/**
	 * Small and large test files.
	 *
	 * @var array<string, string>
	 */
	private array $files = array();

	protected function setUp(): void {
		parent::setUp();
		$this->log = Mockery::mock( EmailRepository::class );

		$this->files['small'] = tempnam( sys_get_temp_dir(), 'ez37' );
		file_put_contents( $this->files['small'], str_repeat( 'x', 100 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		if ( ! defined( 'EZ37_PAYMENTS_DIR' ) ) {
			define( 'EZ37_PAYMENTS_DIR', dirname( __DIR__, 2 ) . '/' );
		}

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'get_bloginfo' )->justReturn( 'EZ37 Payments (local dev)' );
		Functions\when( 'wp_specialchars_decode' )->returnArg();
		Functions\when( 'get_option' )->justReturn( 'Y-m-d' );
		Functions\when( 'wp_date' )->alias( static fn ( $f, $t ) => gmdate( 'Y-m-d H:i', (int) $t ) );
	}

	protected function tearDown(): void {
		@unlink( $this->files['small'] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		parent::tearDown();
	}

	private function mailer( ?string $admin = 'sales@example.test' ): Mailer {
		return new Mailer(
			new Config( null === $admin ? array() : array( 'EZ37_PAYMENTS_ADMIN_EMAIL' => $admin ) ),
			$this->log,
			new Logger( 'error', static function (): void {} )
		);
	}

	private static function offering() {
		return ( new OfferingCatalogue(
			array(
				'leadership-coaching' => array(
					'name'      => 'Leadership Coaching',
					'active'    => true,
					'currency'  => 'usd',
					'amount'    => 100,
					'price_ids' => array(
						'test' => 'price_TEST',
						'live' => '',
					),
				),
			)
		) )->find( 'leadership-coaching' );
	}

	private static function transaction(): array {
		return array(
			'id'                       => '42',
			'public_id'                => 'abcdef1234567890',
			'customer_name'            => 'Jane <b>Doe</b>',
			'customer_email'           => 'jane@example.com',
			'amount'                   => '100',
			'amount_paid'              => '100',
			'currency'                 => 'usd',
			'stripe_mode'              => 'test',
			'stripe_payment_intent_id' => 'pi_123',
			'paid_at'                  => '2026-11-13 10:25:13',
		);
	}

	private function grant( int $max_attach = 5242880 ): array {
		return array(
			'label'      => 'Brochure',
			'url'        => 'https://ez37.test/?ez37_access=' . str_repeat( 'a', 64 ),
			'expires_at' => '2026-11-20 10:25:13',
			'file'       => $this->files['small'],
			'attach'     => true,
			'max_attach' => $max_attach,
		);
	}

	public function test_customer_email_contains_link_attachment_and_escaped_name(): void {
		$this->log->shouldNotReceive( 'begin' );
		$this->log->shouldReceive( 'finish' )->once()->with( 42, EmailRepository::CUSTOMER_FULFILMENT, true, true, null );

		$sent = null;
		Functions\expect( 'wp_mail' )->once()->andReturnUsing(
			static function ( $to, $subject, $body, $headers, $attachments ) use ( &$sent ): bool {
				$sent = array(
					'to'          => $to,
					'subject'     => $subject,
					'body'        => $body,
					'headers'     => $headers,
					'attachments' => $attachments,
				);
				return true;
			}
		);

		$this->assertTrue( $this->mailer()->send_customer_fulfilment( self::transaction(), self::offering(), array( $this->grant() ) ) );

		$this->assertSame( 'jane@example.com', $sent['to'] );
		$this->assertStringContainsString( 'Leadership Coaching', $sent['subject'] );
		$this->assertStringContainsString( 'ez37_access=' . str_repeat( 'a', 64 ), $sent['body'] );
		$this->assertStringContainsString( '$1.00 USD', $sent['body'] );
		$this->assertStringContainsString( 'ABCDEF1234', $sent['body'] );
		$this->assertStringContainsString( 'Jane &lt;b&gt;Doe&lt;/b&gt;', $sent['body'] );
		$this->assertStringNotContainsString( '<b>Doe', $sent['body'] );
		$this->assertSame( array( $this->files['small'] ), $sent['attachments'] );
	}

	public function test_large_file_is_sent_as_link_only(): void {
		$this->log->shouldReceive( 'begin' )->andReturn( true );
		$this->log->shouldReceive( 'finish' )->once()->with( 42, EmailRepository::CUSTOMER_FULFILMENT, true, false, null );
		Functions\expect( 'wp_mail' )->once()->with( Mockery::any(), Mockery::any(), Mockery::any(), Mockery::any(), array() )->andReturn( true );

		$this->mailer()->send_customer_fulfilment( self::transaction(), self::offering(), array( $this->grant( 10 ) ) );
		$this->addToAssertionCount( 1 );
	}

	public function test_reservation_uses_the_email_log(): void {
		$this->log->shouldReceive( 'begin' )->once()->with( 42, EmailRepository::CUSTOMER_FULFILMENT, 'jane@example.com' )->andReturn( false );
		Functions\expect( 'wp_mail' )->never();

		$this->assertFalse( $this->mailer()->reserve_customer_email( self::transaction() ) );
	}

	public function test_release_marks_the_reservation_failed(): void {
		$this->log->shouldReceive( 'finish' )->once()->with( 42, EmailRepository::CUSTOMER_FULFILMENT, false, false, 'Resource unavailable: x.' );

		$this->mailer()->release_customer_email( 42, 'Resource unavailable: x.' );
		$this->addToAssertionCount( 1 );
	}

	public function test_send_failure_is_logged_and_thrown(): void {
		$this->log->shouldReceive( 'begin' )->andReturn( true );
		$this->log->shouldReceive( 'finish' )->once()->with( 42, EmailRepository::CUSTOMER_FULFILMENT, false, true, Mockery::type( 'string' ) );
		Functions\expect( 'wp_mail' )->andReturn( false );

		$this->expectException( \RuntimeException::class );
		$this->mailer()->send_customer_fulfilment( self::transaction(), self::offering(), array( $this->grant() ) );
	}

	public function test_admin_notice_is_plain_text_with_sale_details(): void {
		$this->log->shouldReceive( 'begin' )->once()->with( 42, EmailRepository::ADMIN_SALE_NOTICE, 'sales@example.test' )->andReturn( true );
		$this->log->shouldReceive( 'finish' )->once()->with( 42, EmailRepository::ADMIN_SALE_NOTICE, true, false, null );

		$sent = null;
		Functions\expect( 'wp_mail' )->once()->andReturnUsing(
			static function ( $to, $subject, $body, $headers ) use ( &$sent ): bool {
				$sent = array(
					'to'      => $to,
					'subject' => $subject,
					'body'    => $body,
					'headers' => $headers,
				);
				return true;
			}
		);

		$this->mailer()->send_admin_sale_notice( self::transaction(), self::offering() );

		$this->assertSame( 'sales@example.test', $sent['to'] );
		$this->assertStringContainsString( '[TEST]', $sent['subject'] );
		$this->assertStringContainsString( 'jane@example.com', $sent['body'] );
		$this->assertStringContainsString( 'pi_123', $sent['body'] );
		$this->assertContains( 'Content-Type: text/plain; charset=UTF-8', $sent['headers'] );
	}

	public function test_no_admin_notice_without_recipient(): void {
		$this->log->shouldNotReceive( 'begin' );
		Functions\expect( 'wp_mail' )->never();

		$this->mailer( null )->send_admin_sale_notice( self::transaction(), self::offering() );
		$this->addToAssertionCount( 1 );
	}
}
