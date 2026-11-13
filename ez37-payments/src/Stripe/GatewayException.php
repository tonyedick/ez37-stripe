<?php
/**
 * Stripe gateway failure.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Stripe;

/**
 * Thrown by StripeGateway when a Stripe call fails.
 *
 * The message is safe to log (it never contains API keys). It is NOT shown
 * to customers; they see a generic message instead.
 */
final class GatewayException extends \RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string          $message     Log-safe description.
	 * @param string          $stripe_code Stripe error code, if any (e.g. "resource_missing").
	 * @param int             $http_status HTTP status from Stripe, 0 for network errors.
	 * @param \Throwable|null $previous    Original exception.
	 */
	public function __construct(
		string $message,
		public readonly string $stripe_code = '',
		public readonly int $http_status = 0,
		?\Throwable $previous = null
	) {
		parent::__construct( $message, 0, $previous );
	}
}
