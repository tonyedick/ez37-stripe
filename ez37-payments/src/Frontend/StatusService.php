<?php
/**
 * Customer-facing payment status lookup.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Frontend;

use EZ37\Payments\Offerings\Offering;
use EZ37\Payments\Transactions\PaymentStatus;
use EZ37\Payments\Transactions\TransactionRepository;

/**
 * Tells the returning customer what state their payment is in.
 *
 * Read-only (decision D9): it never changes a transaction and never treats
 * the visit as proof of payment. Only the minimum is disclosed: state,
 * offering name, amount and a masked email. The Checkout Session ID in the
 * success URL acts as the lookup key; it is long, random and only known to
 * the customer (and Stripe).
 */
class StatusService {

	public const STATE_PAID      = 'paid';
	public const STATE_PENDING   = 'pending';
	public const STATE_REVIEW    = 'review';
	public const STATE_FAILED    = 'failed';
	public const STATE_EXPIRED   = 'expired';
	public const STATE_NOT_FOUND = 'not_found';

	/**
	 * Checkout Session ID format accepted for lookups.
	 */
	public const SESSION_ID_PATTERN = '/^cs_(test|live)_[A-Za-z0-9]{10,250}$/';

	/**
	 * Constructor.
	 *
	 * @param TransactionRepository $transactions Transactions.
	 */
	public function __construct( private readonly TransactionRepository $transactions ) {}

	/**
	 * Look up the status for a Checkout Session ID from the success URL.
	 *
	 * @param string $session_id Value of ?session_id=.
	 * @return array{state: string, offering: string, amount: string, email: string}
	 */
	public function lookup( string $session_id ): array {
		$empty = array(
			'state'    => self::STATE_NOT_FOUND,
			'offering' => '',
			'amount'   => '',
			'email'    => '',
		);

		if ( 1 !== preg_match( self::SESSION_ID_PATTERN, $session_id ) ) {
			return $empty;
		}

		$row = $this->transactions->find_by_session_id( $session_id );
		if ( null === $row ) {
			return $empty;
		}

		return array(
			'state'    => self::state_for( $row ),
			'offering' => (string) $row['offering_name'],
			'amount'   => Offering::format_amount( (int) $row['amount'], (string) $row['currency'] ),
			'email'    => self::mask_email( (string) $row['customer_email'] ),
		);
	}

	/**
	 * Customer-facing state for a transaction row.
	 *
	 * @param array<string, mixed> $row Transaction row.
	 */
	public static function state_for( array $row ): string {
		if ( ! empty( $row['review_required'] ) ) {
			return self::STATE_REVIEW;
		}

		return match ( PaymentStatus::tryFrom( (string) $row['payment_status'] ) ) {
			PaymentStatus::Paid    => self::STATE_PAID,
			PaymentStatus::Failed  => self::STATE_FAILED,
			PaymentStatus::Expired => self::STATE_EXPIRED,
			default                => self::STATE_PENDING,
		};
	}

	/**
	 * Mask an email for display: "jane.doe@example.com" → "j***@example.com".
	 *
	 * @param string $email Email.
	 */
	public static function mask_email( string $email ): string {
		$at = strrpos( $email, '@' );
		if ( false === $at || 0 === $at ) {
			return '';
		}

		return substr( $email, 0, 1 ) . '***' . substr( $email, $at );
	}
}
