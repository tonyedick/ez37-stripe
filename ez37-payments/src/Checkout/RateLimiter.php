<?php
/**
 * Simple fixed-window rate limiter.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Checkout;

/**
 * Limits how often one visitor can start a checkout, using transients.
 *
 * Keys are hashed (no raw IPs or emails are stored). The counter is not
 * strictly atomic; that is acceptable for abuse throttling, which is its only
 * purpose. Payment correctness never depends on it.
 */
class RateLimiter {

	/**
	 * Constructor.
	 *
	 * @param int $limit  Maximum attempts per window.
	 * @param int $window Window length in seconds.
	 */
	public function __construct(
		private readonly int $limit = 10,
		private readonly int $window = 600
	) {}

	/**
	 * Record an attempt and report whether it is allowed.
	 *
	 * @param string $bucket   Purpose, e.g. "ip" or "email".
	 * @param string $identity Value to limit (hashed before storage).
	 */
	public function attempt( string $bucket, string $identity ): bool {
		$key   = 'ez37_rl_' . $bucket . '_' . substr( hash( 'sha256', wp_salt( 'nonce' ) . '|' . $identity ), 0, 32 );
		$count = (int) get_transient( $key );

		if ( $count >= $this->limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $this->window );

		return true;
	}
}
