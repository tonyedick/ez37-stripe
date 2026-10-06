<?php
/**
 * Fulfilment status values and allowed transitions.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Transactions;

/**
 * Fulfilment status of a transaction (stored in ez37_transactions.fulfilment_status).
 *
 * Deliberately independent of PaymentStatus: a fulfilment or email failure
 * never changes the payment status of a paid transaction.
 *
 *   not_started ──(payment confirmed)──▶ pending
 *   pending | failed ──(job claims it)──▶ in_progress
 *   in_progress ──▶ completed | failed | failed_permanent
 *   failed_permanent ──(admin retry)──▶ pending
 *
 * A job that crashes leaves the row "in_progress" with an expired
 * fulfilment_locked_until; the sweeper may re-claim such rows. That lock
 * check lives in the repository query, not here.
 */
enum FulfilmentStatus: string {

	/** Payment not confirmed yet; nothing to fulfil. */
	case NotStarted = 'not_started';

	/** Payment confirmed; waiting for a fulfilment job. */
	case Pending = 'pending';

	/** A job has claimed this transaction (see fulfilment_locked_until). */
	case InProgress = 'in_progress';

	/** Entitlement granted and customer email sent. */
	case Completed = 'completed';

	/** Last attempt failed; will be retried automatically. */
	case Failed = 'failed';

	/** Retries exhausted; needs an administrator. */
	case FailedPermanent = 'failed_permanent';

	/**
	 * States this status may move to.
	 *
	 * @return list<self>
	 */
	public function allowed_next(): array {
		return match ( $this ) {
			self::NotStarted      => array( self::Pending ),
			self::Pending         => array( self::InProgress ),
			self::InProgress      => array( self::Completed, self::Failed, self::FailedPermanent ),
			self::Failed          => array( self::InProgress ),
			self::FailedPermanent => array( self::Pending ),
			self::Completed       => array(),
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
	 * Statuses from which $this may be reached.
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
}
