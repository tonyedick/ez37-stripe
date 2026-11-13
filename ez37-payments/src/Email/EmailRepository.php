<?php
/**
 * Email log persistence.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Email;

use EZ37\Payments\Install\Schema;

/**
 * All SQL for ez37_email_log. One row per (transaction, email type): the
 * UNIQUE key is what prevents duplicate emails when webhooks are redelivered,
 * pages refreshed or fulfilment retried. Not final, so tests can mock it.
 */
class EmailRepository {

	public const CUSTOMER_FULFILMENT = 'customer_fulfilment';
	public const ADMIN_SALE_NOTICE   = 'admin_sale_notice';

	public const SENDING = 'sending';
	public const SENT    = 'sent';
	public const FAILED  = 'failed';

	/**
	 * Whether this email was already sent successfully.
	 *
	 * @param int    $transaction_id Transaction ID.
	 * @param string $type           Email type.
	 */
	public function was_sent( int $transaction_id, string $type ): bool {
		return self::SENT === $this->status( $transaction_id, $type );
	}

	/**
	 * Reserve the right to send this email. Returns false if it was already sent.
	 *
	 * @param int    $transaction_id Transaction ID.
	 * @param string $type           Email type.
	 * @param string $recipient      Recipient address.
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public function begin( int $transaction_id, string $type, string $recipient ): bool {
		global $wpdb;

		$table = Schema::table( Schema::EMAIL_LOG );
		$now   = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; idempotency insert.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (transaction_id, email_type, recipient, status, attachment_included, attempts, created_at, updated_at) VALUES (%d, %s, %s, %s, 0, 1, %s, %s)',
				$table,
				$transaction_id,
				$type,
				$recipient,
				self::SENDING,
				$now,
				$now
			)
		);

		if ( false === $inserted ) {
			throw new \RuntimeException( 'Could not write email log: ' . (string) $wpdb->last_error );
		}

		if ( 1 === $inserted ) {
			return true;
		}

		// Existing row: retry unless it was sent.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET status = %s, recipient = %s, attempts = attempts + 1, updated_at = %s WHERE transaction_id = %d AND email_type = %s AND status <> %s',
				$table,
				self::SENDING,
				$recipient,
				$now,
				$transaction_id,
				$type,
				self::SENT
			)
		);

		return 1 === $updated;
	}

	/**
	 * Record the result of a send.
	 *
	 * @param int         $transaction_id      Transaction ID.
	 * @param string      $type                Email type.
	 * @param bool        $sent                Whether wp_mail() accepted it.
	 * @param bool        $attachment_included Whether a file was attached.
	 * @param string|null $error               Log-safe error (truncated).
	 */
	public function finish( int $transaction_id, string $type, bool $sent, bool $attachment_included, ?string $error ): void {
		global $wpdb;

		$now = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->update(
			Schema::table( Schema::EMAIL_LOG ),
			array(
				'status'              => $sent ? self::SENT : self::FAILED,
				'attachment_included' => $attachment_included ? 1 : 0,
				'last_error'          => null === $error ? null : substr( $error, 0, 500 ),
				'sent_at'             => $sent ? $now : null,
				'updated_at'          => $now,
			),
			array(
				'transaction_id' => $transaction_id,
				'email_type'     => $type,
			),
			array( '%s', '%d', '%s', '%s', '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Administrator override: allow an email that was already sent to be sent again.
	 *
	 * @param int    $transaction_id Transaction ID.
	 * @param string $type           Email type.
	 */
	public function allow_resend( int $transaction_id, string $type ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->update(
			Schema::table( Schema::EMAIL_LOG ),
			array(
				'status'     => self::FAILED,
				'last_error' => 'Resend requested by an administrator.',
				'updated_at' => gmdate( 'Y-m-d H:i:s' ),
			),
			array(
				'transaction_id' => $transaction_id,
				'email_type'     => $type,
			)
		);
	}

	/**
	 * Email log rows for a transaction (admin screen).
	 *
	 * @param int $transaction_id Transaction ID.
	 * @return list<array<string, mixed>>
	 */
	public function for_transaction( int $transaction_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; admin view.
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE transaction_id = %d ORDER BY id', Schema::table( Schema::EMAIL_LOG ), $transaction_id ),
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Current status of an email, or null.
	 *
	 * @param int    $transaction_id Transaction ID.
	 * @param string $type           Email type.
	 */
	private function status( int $transaction_id, string $type ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; must read fresh state.
		$status = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT status FROM %i WHERE transaction_id = %d AND email_type = %s',
				Schema::table( Schema::EMAIL_LOG ),
				$transaction_id,
				$type
			)
		);

		return is_string( $status ) ? $status : null;
	}
}
