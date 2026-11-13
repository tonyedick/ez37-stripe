<?php
/**
 * Transaction persistence.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Transactions;

use EZ37\Payments\Install\Schema;
use EZ37\Payments\Offerings\Offering;

/**
 * All SQL for ez37_transactions lives here.
 *
 * Phase 3 scope: create a pending transaction, attach its Checkout Session,
 * and record a session-creation failure. Webhook-driven state transitions
 * are added in Phases 4–5.
 *
 * Uses $wpdb->insert()/update() (which prepare values internally) and
 * $wpdb->prepare() for any hand-written SQL. Not final, so unit tests can mock it.
 */
class TransactionRepository {

	/**
	 * Create a pending transaction for an offering.
	 *
	 * @param Offering $offering Offering being bought.
	 * @param string   $price_id Stripe Price ID used.
	 * @param string   $mode     "test" or "live".
	 * @param string   $name     Validated customer name.
	 * @param string   $email    Validated customer email.
	 * @return array{id: int, public_id: string}
	 *
	 * @throws \RuntimeException When the row cannot be inserted.
	 */
	public function create_pending( Offering $offering, string $price_id, string $mode, string $name, string $email ): array {
		global $wpdb;

		$public_id = self::new_public_id();
		$now       = self::now();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table; caching not appropriate for payment writes.
		$inserted = $wpdb->insert(
			Schema::table( Schema::TRANSACTIONS ),
			array(
				'public_id'         => $public_id,
				'offering_key'      => $offering->key,
				'offering_name'     => $offering->name,
				'stripe_price_id'   => $price_id,
				'amount'            => $offering->amount,
				'currency'          => $offering->currency,
				'customer_name'     => $name,
				'customer_email'    => $email,
				'stripe_mode'       => $mode,
				'payment_status'    => PaymentStatus::Pending->value,
				'fulfilment_status' => FulfilmentStatus::NotStarted->value,
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( 1 !== $inserted ) {
			throw new \RuntimeException( 'Could not create transaction: ' . (string) $wpdb->last_error );
		}

		return array(
			'id'        => (int) $wpdb->insert_id,
			'public_id' => $public_id,
		);
	}

	/**
	 * Store the Checkout Session ID on a pending transaction.
	 *
	 * @param int    $id         Transaction ID.
	 * @param string $session_id Stripe Checkout Session ID.
	 */
	public function attach_checkout_session( int $id, string $session_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->update(
			Schema::table( Schema::TRANSACTIONS ),
			array(
				'stripe_checkout_session_id' => $session_id,
				'updated_at'                 => self::now(),
			),
			array(
				'id'             => $id,
				'payment_status' => PaymentStatus::Pending->value,
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Mark a pending transaction as failed because no Checkout Session could be created.
	 *
	 * @param int    $id      Transaction ID.
	 * @param string $code    Machine-readable reason (max 64 chars).
	 * @param string $message Log-safe description (truncated to 500 chars).
	 */
	public function mark_session_failed( int $id, string $code, string $message ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->update(
			Schema::table( Schema::TRANSACTIONS ),
			array(
				'payment_status'  => PaymentStatus::Failed->value,
				'failure_code'    => substr( $code, 0, 64 ),
				'failure_message' => substr( $message, 0, 500 ),
				'updated_at'      => self::now(),
			),
			array(
				'id'             => $id,
				'payment_status' => PaymentStatus::Pending->value,
			),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Random, non-sequential public reference: 26 lowercase hex characters (104 bits).
	 */
	public static function new_public_id(): string {
		return bin2hex( random_bytes( 13 ) );
	}

	/**
	 * Current time in UTC, MySQL DATETIME format.
	 */
	private static function now(): string {
		return gmdate( 'Y-m-d H:i:s' );
	}
}
