<?php
/**
 * Tests for Config.
 *
 * Key-shaped strings below are FAKE placeholders built at runtime so that no
 * secret-looking literal exists in the repository.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use EZ37\Payments\Config;

final class ConfigTest extends TestCase {

	private static function fake( string $prefix ): string {
		return $prefix . 'FAKE0000000000000000';
	}

	/**
	 * A complete, valid test-mode configuration.
	 *
	 * @return array<string, string>
	 */
	private static function valid_test(): array {
		return array(
			'EZ37_STRIPE_MODE'                => 'test',
			'EZ37_STRIPE_TEST_SECRET_KEY'     => self::fake( 'sk_test_' ),
			'EZ37_STRIPE_TEST_WEBHOOK_SECRET' => self::fake( 'whsec_' ),
			'EZ37_PAYMENTS_CHECKOUT_ENABLED'  => '1',
		);
	}

	public function test_defaults_to_test_mode_with_checkout_disabled(): void {
		$config = new Config( array() );

		$this->assertSame( 'test', $config->mode() );
		$this->assertFalse( $config->is_live() );
		$this->assertFalse( $config->is_checkout_enabled() );
		$this->assertFalse( $config->can_accept_payments() );
		$this->assertSame( 'info', $config->log_level() );
	}

	public function test_valid_test_configuration_has_no_errors(): void {
		$config = new Config( self::valid_test() );

		$this->assertSame( array(), $config->errors() );
		$this->assertSame( array(), $config->warnings() );
		$this->assertTrue( $config->can_accept_payments() );
		$this->assertSame( self::fake( 'sk_test_' ), $config->stripe_secret_key() );
		$this->assertSame( self::fake( 'whsec_' ), $config->stripe_webhook_secret() );
	}

	public function test_restricted_keys_are_accepted(): void {
		$values                                = self::valid_test();
		$values['EZ37_STRIPE_TEST_SECRET_KEY'] = self::fake( 'rk_test_' );

		$config = new Config( $values );

		$this->assertSame( array(), $config->errors() );
		$this->assertSame( self::fake( 'rk_test_' ), $config->stripe_secret_key() );
	}

	public function test_invalid_mode_falls_back_to_test_and_reports_error(): void {
		$values                     = self::valid_test();
		$values['EZ37_STRIPE_MODE'] = 'LIVEE';

		$config = new Config( $values );

		$this->assertSame( 'test', $config->mode() );
		$this->assertNotEmpty( $config->errors() );
		$this->assertFalse( $config->can_accept_payments() );
	}

	public function test_mode_is_case_insensitive(): void {
		$config = new Config( array( 'EZ37_STRIPE_MODE' => 'LIVE' ) );

		$this->assertSame( 'live', $config->mode() );
	}

	public function test_live_mode_rejects_test_key(): void {
		$config = new Config(
			array(
				'EZ37_STRIPE_MODE'                => 'live',
				'EZ37_STRIPE_LIVE_SECRET_KEY'     => self::fake( 'sk_test_' ),
				'EZ37_STRIPE_LIVE_WEBHOOK_SECRET' => self::fake( 'whsec_' ),
				'EZ37_PAYMENTS_CHECKOUT_ENABLED'  => '1',
			)
		);

		$this->assertNull( $config->stripe_secret_key() );
		$this->assertFalse( $config->can_accept_payments() );
		$this->assertStringContainsString( 'EZ37_STRIPE_LIVE_SECRET_KEY', implode( ' ', $config->errors() ) );
	}

	public function test_test_mode_rejects_live_key_in_test_slot(): void {
		$values                                = self::valid_test();
		$values['EZ37_STRIPE_TEST_SECRET_KEY'] = self::fake( 'sk_live_' );

		$config = new Config( $values );

		$this->assertNull( $config->stripe_secret_key() );
		$this->assertFalse( $config->can_accept_payments() );
	}

	public function test_test_mode_never_returns_live_credentials(): void {
		$values                                    = self::valid_test();
		$values['EZ37_STRIPE_LIVE_SECRET_KEY']     = self::fake( 'sk_live_' );
		$values['EZ37_STRIPE_LIVE_WEBHOOK_SECRET'] = self::fake( 'whsec_' );

		$config = new Config( $values );

		$this->assertSame( self::fake( 'sk_test_' ), $config->stripe_secret_key() );
		$this->assertNotEmpty( $config->warnings(), 'Live key present in test mode should warn.' );
		$this->assertTrue( $config->can_accept_payments(), 'Warning must not block test mode.' );
	}

	public function test_publishable_key_is_not_a_secret_key(): void {
		$values                                = self::valid_test();
		$values['EZ37_STRIPE_TEST_SECRET_KEY'] = self::fake( 'pk_test_' );

		$config = new Config( $values );

		$this->assertNull( $config->stripe_secret_key() );
		$this->assertNotEmpty( $config->errors() );
	}

	public function test_missing_or_malformed_webhook_secret_is_an_error(): void {
		$values = self::valid_test();
		unset( $values['EZ37_STRIPE_TEST_WEBHOOK_SECRET'] );
		$this->assertFalse( ( new Config( $values ) )->can_accept_payments() );

		$values['EZ37_STRIPE_TEST_WEBHOOK_SECRET'] = 'not-a-signing-secret';
		$config                                    = new Config( $values );
		$this->assertNull( $config->stripe_webhook_secret() );
		$this->assertFalse( $config->can_accept_payments() );
	}

	public function test_checkout_switch_accepts_common_truthy_values_only(): void {
		foreach ( array( '1', 'true', 'TRUE', 'yes', 'on', true ) as $on ) {
			$values                                   = self::valid_test();
			$values['EZ37_PAYMENTS_CHECKOUT_ENABLED'] = $on;
			$this->assertTrue( ( new Config( $values ) )->is_checkout_enabled(), var_export( $on, true ) );
		}

		foreach ( array( '0', 'false', 'no', 'off', '', 'enabled', false ) as $off ) {
			$values                                   = self::valid_test();
			$values['EZ37_PAYMENTS_CHECKOUT_ENABLED'] = $off;
			$this->assertFalse( ( new Config( $values ) )->is_checkout_enabled(), var_export( $off, true ) );
		}
	}

	public function test_values_are_trimmed_and_blank_values_ignored(): void {
		$values                                = self::valid_test();
		$values['EZ37_STRIPE_TEST_SECRET_KEY'] = '  ' . self::fake( 'sk_test_' ) . "\n";
		$values['EZ37_PAYMENTS_ADMIN_EMAIL']   = '   ';

		$config = new Config( $values );

		$this->assertSame( self::fake( 'sk_test_' ), $config->stripe_secret_key() );
		$this->assertNull( $config->admin_email() );
		$this->assertSame( array(), $config->errors() );
	}

	public function test_invalid_log_level_and_admin_email_are_reported(): void {
		$values                              = self::valid_test();
		$values['EZ37_PAYMENTS_LOG_LEVEL']   = 'verbose';
		$values['EZ37_PAYMENTS_ADMIN_EMAIL'] = 'not-an-email';

		$config = new Config( $values );

		$this->assertSame( 'info', $config->log_level() );
		$this->assertNull( $config->admin_email() );
		$this->assertCount( 2, $config->errors() );
	}

	public function test_unknown_settings_are_ignored(): void {
		$values             = self::valid_test();
		$values['DB_PASSWORD'] = 'should-never-be-read';

		$config = new Config( $values );

		$this->assertArrayNotHasKey( 'DB_PASSWORD', $config->__debugInfo() );
	}

	public function test_secrets_never_appear_in_debug_output_or_errors(): void {
		$values                                = self::valid_test();
		$values['EZ37_STRIPE_LIVE_SECRET_KEY'] = self::fake( 'sk_live_' );

		$config = new Config( $values );
		$dump   = print_r( $config, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		$all    = $dump . implode( ' ', $config->errors() ) . implode( ' ', $config->warnings() );

		$this->assertStringNotContainsString( 'FAKE0000', $all );
		$this->assertStringContainsString( '[redacted]', $dump );
	}

	public function test_from_environment_reads_environment_variables(): void {
		putenv( 'EZ37_PAYMENTS_LOG_LEVEL=debug' );

		try {
			$this->assertSame( 'debug', Config::from_environment()->log_level() );
		} finally {
			putenv( 'EZ37_PAYMENTS_LOG_LEVEL' );
		}
	}

	public function test_key_matches_mode(): void {
		$this->assertTrue( Config::key_matches_mode( self::fake( 'sk_live_' ), 'live' ) );
		$this->assertTrue( Config::key_matches_mode( self::fake( 'rk_live_' ), 'live' ) );
		$this->assertFalse( Config::key_matches_mode( self::fake( 'sk_live_' ), 'test' ) );
		$this->assertFalse( Config::key_matches_mode( self::fake( 'pk_live_' ), 'live' ) );
		$this->assertFalse( Config::key_matches_mode( 'sk_live', 'live' ) );
	}
}
