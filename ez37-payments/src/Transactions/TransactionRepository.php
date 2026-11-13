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
 * Creates pending transactions, attaches Checkout Sessions, records failures,
 * looks transactions up by session and applies atomic payment-state
 * transitions (from verified webhooks).
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
	 * Find a transaction by its Stripe Checkout Session ID.
	 *
	 * @param string $session_id Checkout Session ID.
	 * @return array<string, mixed>|null Row, or null when not found.
	 */
	public function find_by_session_id( string $session_id ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; payment state must be read fresh.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE stripe_checkout_session_id = %s LIMIT 1',
				Schema::table( Schema::TRANSACTIONS ),
				$session_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Transactions still waiting for payment confirmation that are old enough
	 * to suspect a missed webhook, oldest first.
	 *
	 * @param string $mode            "test" or "live" (only the active mode is reconciled).
	 * @param int    $min_age_minutes Ignore transactions younger than this.
	 * @param int    $max_age_hours   Ignore transactions older than this.
	 * @param int    $limit           Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public function find_unconfirmed( string $mode, int $min_age_minutes, int $max_age_hours, int $limit ): array {
		global $wpdb;

		$now = time();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; payment state must be read fresh.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM %i WHERE payment_status IN (%s, %s) AND stripe_mode = %s AND stripe_checkout_session_id IS NOT NULL AND review_required = 0 AND created_at <= %s AND created_at >= %s ORDER BY created_at ASC LIMIT %d',
				Schema::table( Schema::TRANSACTIONS ),
				PaymentStatus::Pending->value,
				PaymentStatus::Processing->value,
				$mode,
				gmdate( 'Y-m-d H:i:s', $now - $min_age_minutes * 60 ),
				gmdate( 'Y-m-d H:i:s', $now - $max_age_hours * 3600 ),
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Atomically move a transaction to a new payment status.
	 *
	 * The UPDATE only matches when the current status is one the target may be
	 * reached from (PaymentStatus::allowed_from()), so duplicate, concurrent or
	 * out-of-order calls cannot apply a change twice or move it backwards.
	 *
	 * @param int                            $id  Transaction ID.
	 * @param PaymentStatus                  $to  Target status.
	 * @param array<string, int|string|null> $set Extra columns to set in the same UPDATE.
	 * @return bool True only if this call changed the row.
	 *
	 * @throws \InvalidArgumentException On an invalid column name.
	 * @throws \RuntimeException On a database error.
	 */
	public function transition( int $id, PaymentStatus $to, array $set = array() ): bool {
		global $wpdb;

		$from = array_map( static fn ( PaymentStatus $status ): string => $status->value, $to->allowed_from() );
		if ( array() === $from ) {
			return false;
		}

		$set['payment_status'] = $to->value;
		$set['updated_at']     = self::now();

		$assignments = array();
		$args        = array( Schema::table( Schema::TRANSACTIONS ) );

		foreach ( $set as $column => $value ) {
			if ( 1 !== preg_match( '/^[a-z_]+$/', $column ) ) {
				throw new \InvalidArgumentException( 'Invalid column name.' );
			}

			$args[] = $column;
			if ( null === $value ) {
				$assignments[] = '%i = NULL';
				continue;
			}

			$assignments[] = is_int( $value ) ? '%i = %d' : '%i = %s';
			$args[]        = $value;
		}

		$args[] = $id;
		$args   = array_merge( $args, $from );

		$sql = 'UPDATE %i SET ' . implode( ', ', $assignments )
			. ' WHERE id = %d AND payment_status IN (' . implode( ', ', array_fill( 0, count( $from ), '%s' ) ) . ')';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- $sql holds only placeholders; every value goes through prepare().
		$result = $wpdb->query( $wpdb->prepare( $sql, $args ) );

		if ( false === $result ) {
			throw new \RuntimeException( 'Transition failed: ' . (string) $wpdb->last_error );
		}

		return 1 === $result;
	}

	/**
	 * Flag a transaction for manual review. It will not be fulfilled automatically.
	 *
	 * @param int    $id      Transaction ID.
	 * @param string $code    Machine-readable reason (max 64 chars).
	 * @param string $message Log-safe description (truncated to 500 chars).
	 */
	public function flag_for_review( int $id, string $code, string $message ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->update(
			Schema::table( Schema::TRANSACTIONS ),
			array(
				'review_required' => 1,
				'failure_code'    => substr( $code, 0, 64 ),
				'failure_message' => substr( $message, 0, 500 ),
				'updated_at'      => self::now(),
			),
			array( 'id' => $id ),
			array( '%d', '%s', '%s', '%s' ),
			array( '%d' )
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
