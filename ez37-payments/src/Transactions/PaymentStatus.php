<?php
/**
 * Payment status values and allowed transitions.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Transactions;

/**
 * Payment status of a transaction (stored in ez37_transactions.payment_status).
 *
 * Transitions are forward-only. Repositories must apply them with a
 * conditional UPDATE (… WHERE payment_status IN (allowed_from())) so that
 * duplicate or out-of-order webhook events can never move a transaction
 * backwards — for example, a late "expired" event cannot undo "paid".
 *
 *   pending ──▶ paid | processing | failed | expired
 *   processing ──▶ paid | failed
 *   paid, failed, expired: final (refunds are manual at launch — decision D12)
 */
enum PaymentStatus: string {

	/** Checkout Session created; customer has not paid yet. */
	case Pending = 'pending';

	/** Checkout completed with a delayed payment method; funds not yet confirmed. */
	case Processing = 'processing';

	/** Payment confirmed by a verified Stripe webhook. */
	case Paid = 'paid';

	/** Session creation failed, or a delayed payment failed. */
	case Failed = 'failed';

	/** Checkout Session expired without payment (abandoned or cancelled). */
	case Expired = 'expired';

	/** Customer chose instalments / other terms: no card payment; the EZ37 team sends an invoice. */
	case InvoiceRequested = 'invoice_requested';

	/** A free registration (no payment involved): stored, and the EZ37 team follows up. */
	case Registered = 'registered';

	/**
	 * States this status may move to.
	 *
	 * @return list<self>
	 */
	public function allowed_next(): array {
		return match ( $this ) {
			self::Pending    => array( self::Processing, self::Paid, self::Failed, self::Expired ),
			self::Processing => array( self::Paid, self::Failed ),
			self::Paid, self::Failed, self::Expired, self::InvoiceRequested, self::Registered => array(),
		};
	}

	/**
	 * Whether a move from this status to $next is allowed.
	 *
	 * @param self $next Target status.
	 */
	public function can_transition_to( self $next ): bool {
		return in_array( $next, $this->allowed_next(), true );
	}

	/**
	 * Statuses from which $this may be reached — used to build the WHERE
	 * clause of an atomic, conditional UPDATE.
	 *
	 * @return list<self>
	 */
	public function allowed_from(): array {
		$from = array();

		foreach ( self::cases() as $candidate ) {
			if ( $candidate->can_transition_to( $this ) ) {
				$from[] = $candidate;
			}
		}

		return $from;
	}

	/**
	 * Whether no further transitions are possible.
	 */
	public function is_final(): bool {
		return array() === $this->allowed_next();
	}
}
