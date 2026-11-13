<?php
/**
 * Stripe event log persistence.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Webhooks;

use EZ37\Payments\Install\Schema;

/**
 * All SQL for ez37_stripe_events. The UNIQUE key on stripe_event_id is what
 * makes webhook processing idempotent: a duplicate delivery cannot insert a
 * second row. Not final, so unit tests can mock it.
 */
class EventRepository {

	public const RECEIVED  = 'received';
	public const PROCESSED = 'processed';
	public const IGNORED   = 'ignored';
	public const FAILED    = 'failed';

	/**
	 * Record a newly received event.
	 *
	 * @param string      $event_id  Stripe event ID.
	 * @param string      $type      Event type.
	 * @param bool        $livemode  Event livemode flag.
	 * @param string|null $object_id ID of data.object, if any.
	 * @return bool True if this is the first time the event was seen.
	 *
	 * @throws \RuntimeException On a database error.
	 */
	public function record( string $event_id, string $type, bool $livemode, ?string $object_id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; idempotency insert.
		$result = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (stripe_event_id, event_type, livemode, stripe_object_id, processing_status, received_at) VALUES (%s, %s, %d, %s, %s, %s)',
				Schema::table( Schema::STRIPE_EVENTS ),
				$event_id,
				substr( $type, 0, 100 ),
				$livemode ? 1 : 0,
				$object_id,
				self::RECEIVED,
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		if ( false === $result ) {
			throw new \RuntimeException( 'Could not record Stripe event: ' . (string) $wpdb->last_error );
		}

		return 1 === $result;
	}

	/**
	 * Processing status of a recorded event, or null if unknown.
	 *
	 * @param string $event_id Stripe event ID.
	 */
	public function status( string $event_id ): ?string {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; must read the current state.
		$status = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT processing_status FROM %i WHERE stripe_event_id = %s',
				Schema::table( Schema::STRIPE_EVENTS ),
				$event_id
			)
		);

		return is_string( $status ) ? $status : null;
	}

	/**
	 * Events recorded for a transaction (admin screen).
	 *
	 * @param int $transaction_id Transaction ID.
	 * @return list<array<string, mixed>>
	 */
	public function for_transaction( int $transaction_id ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; admin view.
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT * FROM %i WHERE transaction_id = %d ORDER BY id', Schema::table( Schema::STRIPE_EVENTS ), $transaction_id ),
			ARRAY_A
		);

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Record the outcome of processing an event.
	 *
	 * @param string      $event_id       Stripe event ID.
	 * @param string      $status         One of the class constants.
	 * @param int|null    $transaction_id Related transaction, if any.
	 * @param string|null $message        Short log-safe note (truncated to 500 chars).
	 */
	public function finish( string $event_id, string $status, ?int $transaction_id = null, ?string $message = null ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->update(
			Schema::table( Schema::STRIPE_EVENTS ),
			array(
				'processing_status' => $status,
				'transaction_id'    => $transaction_id,
				'error_message'     => null === $message ? null : substr( $message, 0, 500 ),
				'processed_at'      => gmdate( 'Y-m-d H:i:s' ),
			),
			array( 'stripe_event_id' => $event_id ),
			array( '%s', '%d', '%s', '%s' ),
			array( '%s' )
		);
	}
}
