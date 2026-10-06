<?php
/**
 * Tests for Schema.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use Brain\Monkey\Functions;
use EZ37\Payments\Install\Schema;

final class SchemaTest extends TestCase {

	private const COLLATE = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';

	/**
	 * SQL keyed by unprefixed table name.
	 *
	 * @return array<string, string>
	 */
	private static function sql(): array {
		$out = array();

		foreach ( Schema::create_table_sql( 'wp_', self::COLLATE ) as $statement ) {
			preg_match( '/^CREATE TABLE wp_(\w+) \(/', $statement, $m );
			$out[ $m[1] ] = $statement;
		}

		return $out;
	}

	public function test_creates_exactly_the_four_plugin_tables_with_prefix(): void {
		$this->assertSame(
			array( 'ez37_transactions', 'ez37_stripe_events', 'ez37_entitlements', 'ez37_email_log' ),
			array_keys( self::sql() )
		);

		foreach ( Schema::create_table_sql( 'custom_', self::COLLATE ) as $statement ) {
			$this->assertStringStartsWith( 'CREATE TABLE custom_ez37_', $statement );
		}
	}

	public function test_statements_follow_dbdelta_formatting_rules(): void {
		foreach ( self::sql() as $table => $statement ) {
			$this->assertStringContainsString( 'PRIMARY KEY  (id)', $statement, "{$table}: two spaces after PRIMARY KEY" );
			$this->assertDoesNotMatchRegularExpression( '/\bINDEX\b/', $statement, "{$table}: use KEY, not INDEX" );
			$this->assertStringNotContainsString( 'IF NOT EXISTS', $statement, $table );
			$this->assertStringNotContainsString( '`', $statement, "{$table}: no backticks" );
			$this->assertStringEndsWith( self::COLLATE . ';', $statement, $table );
			$this->assertDoesNotMatchRegularExpression( '/,\s*\)\s*' . preg_quote( self::COLLATE, '/' ) . '/', $statement, "{$table}: no trailing comma" );
		}
	}

	public function test_idempotency_constraints_exist(): void {
		$sql = self::sql();

		$this->assertStringContainsString( 'UNIQUE KEY public_id (public_id)', $sql['ez37_transactions'] );
		$this->assertStringContainsString( 'UNIQUE KEY stripe_checkout_session_id (stripe_checkout_session_id)', $sql['ez37_transactions'] );
		$this->assertStringContainsString( 'UNIQUE KEY stripe_payment_intent_id (stripe_payment_intent_id)', $sql['ez37_transactions'] );
		$this->assertStringContainsString( 'UNIQUE KEY stripe_event_id (stripe_event_id)', $sql['ez37_stripe_events'] );
		$this->assertStringContainsString( 'UNIQUE KEY transaction_resource (transaction_id,resource_key)', $sql['ez37_entitlements'] );
		$this->assertStringContainsString( 'UNIQUE KEY access_token_hash (access_token_hash)', $sql['ez37_entitlements'] );
		$this->assertStringContainsString( 'UNIQUE KEY transaction_email_type (transaction_id,email_type)', $sql['ez37_email_log'] );
	}

	public function test_stripe_identifiers_are_case_sensitive_ascii(): void {
		$sql = implode( "\n", self::sql() );

		foreach ( array( 'stripe_checkout_session_id', 'stripe_payment_intent_id', 'stripe_customer_id', 'stripe_event_id', 'stripe_price_id', 'access_token_hash', 'public_id' ) as $column ) {
			$this->assertMatchesRegularExpression(
				'/^' . $column . ' \w+\(\d+\) CHARACTER SET ascii COLLATE ascii_bin /m',
				$sql,
				$column
			);
		}
	}

	public function test_text_index_columns_use_prefix_length_for_utf8mb4(): void {
		$sql = self::sql();

		$this->assertStringContainsString( 'KEY customer_email (customer_email(191))', $sql['ez37_transactions'] );
		$this->assertStringContainsString( 'KEY customer_email (customer_email(191))', $sql['ez37_entitlements'] );
	}

	public function test_transactions_cover_the_required_data_model(): void {
		$required = array(
			'public_id', 'offering_key', 'customer_name', 'customer_email', 'amount', 'currency',
			'stripe_checkout_session_id', 'stripe_payment_intent_id', 'stripe_customer_id',
			'amount_subtotal', 'amount_tax', 'amount_paid',
			'payment_status', 'fulfilment_status', 'fulfilment_attempts', 'fulfilment_locked_until',
			'review_required', 'failure_code', 'failure_message', 'created_at', 'updated_at', 'paid_at', 'fulfilled_at',
		);

		foreach ( $required as $column ) {
			$this->assertMatchesRegularExpression( '/^' . $column . ' /m', self::sql()['ez37_transactions'], $column );
		}
	}

	public function test_status_defaults_match_enums(): void {
		$sql = self::sql()['ez37_transactions'];

		$this->assertStringContainsString( "payment_status varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'pending'", $sql );
		$this->assertStringContainsString( "fulfilment_status varchar(20) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'not_started'", $sql );
	}

	public function test_amounts_are_unsigned_integers(): void {
		foreach ( array( 'amount', 'amount_subtotal', 'amount_tax', 'amount_paid' ) as $column ) {
			$this->assertMatchesRegularExpression( '/^' . $column . ' bigint\(20\) unsigned /m', self::sql()['ez37_transactions'], $column );
		}
	}

	public function test_install_runs_dbdelta_and_records_version(): void {
		$GLOBALS['wpdb'] = new class() {
			public string $prefix = 'wp_';

			public function get_charset_collate(): string {
				return 'DEFAULT CHARACTER SET utf8mb4';
			}
		};

		Functions\expect( 'dbDelta' )
			->once()
			->with( \Mockery::on( static fn ( $statements ) => is_array( $statements ) && 4 === count( $statements ) ) );
		Functions\expect( 'update_option' )
			->once()
			->with( Schema::VERSION_OPTION, Schema::DB_VERSION, true );

		Schema::install();

		unset( $GLOBALS['wpdb'] );
		$this->addToAssertionCount( 1 );
	}

	public function test_maybe_upgrade_does_nothing_when_version_is_current(): void {
		Functions\when( 'get_option' )->justReturn( Schema::DB_VERSION );
		Functions\expect( 'dbDelta' )->never();

		Schema::maybe_upgrade();

		$this->addToAssertionCount( 1 );
	}
}
