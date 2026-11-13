<?php
/**
 * Payment status REST endpoint (used by the success page to poll).
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Frontend;

use EZ37\Payments\Checkout\RateLimiter;
use EZ37\Payments\Support\ClientIp;

/**
 * GET /wp-json/ez37/v1/payment-status?session_id=cs_...
 *
 * Returns only the customer-facing state (paid, pending, review, failed,
 * expired, not_found). Rate limited per IP; responses are never cached.
 */
final class StatusController {

	public const ROUTE_NAMESPACE = 'ez37/v1';
	public const ROUTE           = '/payment-status';

	/**
	 * Constructor.
	 *
	 * @param StatusService $status       Lookup.
	 * @param RateLimiter   $rate_limiter Throttling (enumeration defence).
	 */
	public function __construct(
		private readonly StatusService $status,
		private readonly RateLimiter $rate_limiter
	) {}

	/**
	 * Register the REST route.
	 */
	public function register(): void {
		add_action(
			'rest_api_init',
			function (): void {
				register_rest_route(
					self::ROUTE_NAMESPACE,
					self::ROUTE,
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'handle' ),
						// Public by design: the unguessable session ID is the lookup key.
						'permission_callback' => '__return_true',
						'args'                => array(
							'session_id' => array(
								'type'     => 'string',
								'required' => true,
							),
						),
					)
				);
			}
		);
	}

	/**
	 * REST callback.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function handle( \WP_REST_Request $request ): \WP_REST_Response {
		$ip = ClientIp::get();

		if ( ! $this->rate_limiter->attempt( 'status', $ip ) ) {
			$response = new \WP_REST_Response( array( 'state' => 'rate_limited' ), 429 );
		} else {
			$result   = $this->status->lookup( (string) $request->get_param( 'session_id' ) );
			$response = new \WP_REST_Response( array( 'state' => $result['state'] ), 200 );
		}

		$response->header( 'Cache-Control', 'no-store, private' );
		$response->header( 'X-Robots-Tag', 'noindex' );

		return $response;
	}
}
