<?php
/**
 * Applies verified Stripe events to transactions.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Webhooks;

use EZ37\Payments\Config;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Transactions\FulfilmentStatus;
use EZ37\Payments\Transactions\PaymentStatus;
use EZ37\Payments\Transactions\TransactionRepository;

/**
 * Turns verified Stripe events into payment-state changes.
 *
 * This is the ONLY place a transaction becomes "paid".
 *
 * Safety rules:
 * - Each event ID is processed once (ez37_stripe_events UNIQUE key). A
 *   redelivery of an event whose earlier attempt crashed is processed again,
 *   which is safe because of the next rule.
 * - State changes are forward-only conditional UPDATEs, so duplicate,
 *   concurrent or out-of-order events cannot double-apply or go backwards.
 * - A session that does not match its transaction (reference, mode, amount,
 *   currency, livemode) is flagged for review and never marked paid.
 * - The event payload is authenticated by its signature, so no extra API
 *   call is made to re-fetch the session.
 */
class EventProcessor {

	/**
	 * Event types this plugin handles. Everything else is acknowledged and ignored.
	 */
	public const HANDLED_TYPES = array(
		'checkout.session.completed',
		'checkout.session.async_payment_succeeded',
		'checkout.session.async_payment_failed',
		'checkout.session.expired',
	);

	/**
	 * Fired once, when a transaction first becomes paid. Fulfilment (Phase 6) listens to it.
	 */
	public const PAID_ACTION = 'ez37_payments_transaction_paid';

	/**
	 * Constructor.
	 *
	 * @param Config                $config       Configuration.
	 * @param TransactionRepository $transactions Transactions.
	 * @param EventRepository       $events       Event log.
	 * @param Logger                $logger       Logger.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly TransactionRepository $transactions,
		private readonly EventRepository $events,
		private readonly Logger $logger
	) {}

	/**
	 * Process one verified event.
	 *
	 * @param array<string, mixed> $event Event as an array (Stripe\Event::toArray()).
	 * @return string Outcome: processed, ignored, duplicate or review.
	 *
	 * @throws \RuntimeException On an internal failure (the caller answers 500 so Stripe retries).
	 */
	public function process( array $event ): string {
		$event_id = is_string( $event['id'] ?? null ) ? $event['id'] : '';
		$type     = is_string( $event['type'] ?? null ) ? $event['type'] : '';
		$livemode = (bool) ( $event['livemode'] ?? false );
		$object   = $event['data']['object'] ?? null;
		$object   = is_array( $object ) ? $object : array();

		if ( '' === $event_id || '' === $type ) {
			throw new \RuntimeException( 'Event without id or type.' );
		}

		$object_id = is_string( $object['id'] ?? null ) ? $object['id'] : null;

		if ( ! $this->events->record( $event_id, $type, $livemode, $object_id ) ) {
			$previous = $this->events->status( $event_id );
			if ( EventRepository::PROCESSED === $previous || EventRepository::IGNORED === $previous ) {
				$this->logger->info( 'Duplicate Stripe event ignored.', array( 'event' => $event_id ) );
				return 'duplicate';
			}
			// Earlier attempt did not finish; processing again is safe.
		}

		if ( $livemode !== $this->config->is_live() ) {
			return $this->ignore( $event_id, 'livemode does not match the configured mode' );
		}

		if ( ! in_array( $type, self::HANDLED_TYPES, true ) ) {
			return $this->ignore( $event_id, 'event type not handled: ' . $type );
		}

		try {
			return $this->handle_session_event( $event_id, $type, $object );
		} catch ( \Throwable $e ) {
			$this->events->finish( $event_id, EventRepository::FAILED, null, $e->getMessage() );
			throw new \RuntimeException( 'Failed to process ' . $event_id, 0, $e );
		}
	}

	/**
	 * Apply a checkout.session.* event.
	 *
	 * @param string               $event_id Event ID.
	 * @param string               $type     Event type.
	 * @param array<string, mixed> $session  Checkout Session.
	 */
	private function handle_session_event( string $event_id, string $type, array $session ): string {
		$session_id  = is_string( $session['id'] ?? null ) ? $session['id'] : '';
		$transaction = '' === $session_id ? null : $this->transactions->find_by_session_id( $session_id );

		if ( null === $transaction ) {
			return $this->ignore( $event_id, 'no transaction for this Checkout Session' );
		}

		$id            = (int) $transaction['id'];
		$payment_state = is_string( $session['payment_status'] ?? null ) ? $session['payment_status'] : '';
		$pays          = ( 'checkout.session.completed' === $type && 'paid' === $payment_state )
			|| 'checkout.session.async_payment_succeeded' === $type;

		$problems = $this->check_session( $transaction, $session, $pays );

		if ( 'checkout.session.completed' === $type && ! in_array( $payment_state, array( 'paid', 'unpaid' ), true ) ) {
			$problems[] = 'unexpected payment_status ' . $payment_state;
		}

		if ( array() !== $problems ) {
			$summary = implode( '; ', $problems );
			$this->transactions->flag_for_review( $id, 'webhook_mismatch', $summary );
			$this->events->finish( $event_id, EventRepository::PROCESSED, $id, 'flagged for review: ' . $summary );
			$this->logger->error(
				'Checkout Session does not match its transaction; flagged for review, not fulfilled.',
				array(
					'event'       => $event_id,
					'transaction' => (string) $transaction['public_id'],
					'problems'    => $problems,
				)
			);
			return 'review';
		}

		if ( $pays ) {
			$changed = $this->mark_paid( $id, $session );
		} elseif ( 'checkout.session.completed' === $type ) {
			// Delayed payment method: completed but funds not confirmed yet.
			$changed = $this->transactions->transition( $id, PaymentStatus::Processing );
		} elseif ( 'checkout.session.async_payment_failed' === $type ) {
			$changed = $this->transactions->transition(
				$id,
				PaymentStatus::Failed,
				array(
					'failure_code'    => 'async_payment_failed',
					'failure_message' => 'The delayed payment failed.',
				)
			);
		} else {
			$changed = $this->transactions->transition( $id, PaymentStatus::Expired );
		}

		$this->events->finish( $event_id, EventRepository::PROCESSED, $id, $changed ? null : 'no state change (already applied or not allowed)' );

		$this->logger->info(
			$changed ? 'Transaction updated from Stripe event.' : 'Stripe event caused no state change.',
			array(
				'event'       => $event_id,
				'type'        => $type,
				'transaction' => (string) $transaction['public_id'],
			)
		);

		if ( $changed && $pays ) {
			do_action( 'ez37_payments_transaction_paid', $id );
			// Same as self::PAID_ACTION; literal so the hook name is greppable.
		}

		return 'processed';
	}

	/**
	 * Mark a transaction paid and queue fulfilment, atomically.
	 *
	 * @param int                  $id      Transaction ID.
	 * @param array<string, mixed> $session Checkout Session.
	 * @return bool Whether this call made the change.
	 */
	private function mark_paid( int $id, array $session ): bool {
		$tax = (int) ( $session['total_details']['amount_tax'] ?? 0 );

		return $this->transactions->transition(
			$id,
			PaymentStatus::Paid,
			array(
				'paid_at'                  => gmdate( 'Y-m-d H:i:s' ),
				'stripe_payment_intent_id' => self::string_id( $session['payment_intent'] ?? null ),
				'stripe_customer_id'       => self::string_id( $session['customer'] ?? null ),
				'amount_subtotal'          => (int) ( $session['amount_subtotal'] ?? 0 ),
				'amount_tax'               => $tax,
				'amount_paid'              => (int) ( $session['amount_total'] ?? 0 ),
				'fulfilment_status'        => FulfilmentStatus::Pending->value,
			)
		);
	}

	/**
	 * Compare the session with its transaction.
	 *
	 * @param array<string, mixed> $transaction Transaction row.
	 * @param array<string, mixed> $session     Checkout Session.
	 * @param bool                 $pays        Whether this event would mark it paid (amounts are checked then).
	 * @return list<string> Problems; empty when consistent.
	 */
	public function check_session( array $transaction, array $session, bool $pays ): array {
		$problems  = array();
		$public_id = (string) $transaction['public_id'];

		if ( ( $session['client_reference_id'] ?? null ) !== $public_id ) {
			$problems[] = 'client_reference_id does not match';
		}
		if ( ( $session['metadata']['ez37_transaction'] ?? null ) !== $public_id ) {
			$problems[] = 'metadata.ez37_transaction does not match';
		}
		if ( 'payment' !== ( $session['mode'] ?? null ) ) {
			$problems[] = 'mode is not payment';
		}
		if ( (bool) ( $session['livemode'] ?? false ) !== ( Config::MODE_LIVE === $transaction['stripe_mode'] ) ) {
			$problems[] = 'livemode does not match the transaction';
		}

		if ( $pays ) {
			$subtotal = $session['amount_subtotal'] ?? null;
			$total    = $session['amount_total'] ?? null;
			$tax      = (int) ( $session['total_details']['amount_tax'] ?? 0 );
			$discount = (int) ( $session['total_details']['amount_discount'] ?? 0 );
			$shipping = (int) ( $session['total_details']['amount_shipping'] ?? 0 );

			if ( ( $session['currency'] ?? null ) !== $transaction['currency'] ) {
				$problems[] = 'currency does not match';
			}
			if ( $subtotal !== (int) $transaction['amount'] ) {
				$problems[] = 'amount_subtotal does not match the configured price';
			}
			if ( 0 !== $discount || 0 !== $shipping ) {
				$problems[] = 'unexpected discount or shipping';
			}
			if ( ! is_int( $total ) || ! is_int( $subtotal ) || $total !== $subtotal + $tax ) {
				$problems[] = 'amount_total is not subtotal plus tax';
			}
		}

		return $problems;
	}

	/**
	 * Acknowledge an event without changing anything.
	 *
	 * @param string $event_id Event ID.
	 * @param string $reason   Why.
	 */
	private function ignore( string $event_id, string $reason ): string {
		$this->events->finish( $event_id, EventRepository::IGNORED, null, $reason );
		$this->logger->info(
			'Stripe event ignored.',
			array(
				'event'  => $event_id,
				'reason' => $reason,
			)
		);

		return 'ignored';
	}

	/**
	 * A Stripe ID from a field that may be an ID string, an expanded object or null.
	 *
	 * @param mixed $value Field value.
	 */
	private static function string_id( mixed $value ): ?string {
		if ( is_string( $value ) && '' !== $value ) {
			return $value;
		}

		return is_array( $value ) && is_string( $value['id'] ?? null ) ? $value['id'] : null;
	}
}
