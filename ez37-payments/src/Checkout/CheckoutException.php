<?php
/**
 * Customer-facing checkout failure.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Checkout;

/**
 * Thrown when a checkout cannot start. Carries a short reason code that the
 * checkout page turns into a friendly, generic message (see CheckoutShortcode).
 * The exception message itself is for logs only.
 */
final class CheckoutException extends \RuntimeException {

	public const UNAVAILABLE   = 'unavailable';
	public const INVALID_INPUT = 'invalid_input';
	public const SECURITY      = 'security';
	public const RATE_LIMITED  = 'rate_limited';
	public const DISABLED      = 'disabled';
	public const STRIPE_ERROR  = 'stripe_error';

	/**
	 * Constructor.
	 *
	 * @param string          $reason   One of the class constants.
	 * @param string          $message  Log-only detail.
	 * @param \Throwable|null $previous Cause.
	 */
	public function __construct(
		public readonly string $reason,
		string $message = '',
		?\Throwable $previous = null
	) {
		parent::__construct( '' === $message ? $reason : $message, 0, $previous );
	}
}
