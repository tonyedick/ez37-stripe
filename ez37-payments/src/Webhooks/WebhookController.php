<?php
/**
 * Stripe webhook endpoint.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Webhooks;

use EZ37\Payments\Config;
use EZ37\Payments\Support\Logger;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;

/**
 * Receives Stripe events at POST /wp-json/ez37/v1/stripe/webhook.
 *
 * Every request must carry a valid Stripe-Signature header for the active
 * mode's signing secret, computed over the exact raw body, and no older than
 * five minutes (replay protection). Anything else gets 400 and is never
 * processed.
 *
 * Response codes tell Stripe what to do next:
 * - 200: received (processed, ignored or a duplicate). Stripe stops retrying.
 * - 400/413: invalid request. Stripe retries, but it will keep failing.
 * - 500: our problem (e.g. database down). Stripe retries later.
 */
final class WebhookController {

	public const ROUTE_NAMESPACE = 'ez37/v1';
	public const ROUTE           = '/stripe/webhook';

	/**
	 * Signature timestamp tolerance in seconds (Stripe's default; never 0).
	 */
	public const TOLERANCE = 300;

	/**
	 * Largest accepted body. Stripe events are far smaller.
	 */
	public const MAX_BODY_BYTES = 524288;

	/**
	 * Constructor.
	 *
	 * @param Config         $config    Configuration (signing secret).
	 * @param EventProcessor $processor Event processing.
	 * @param Logger         $logger    Logger.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly EventProcessor $processor,
		private readonly Logger $logger
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
						'methods'             => 'POST',
						'callback'            => array( $this, 'handle' ),
						// Public by design: authenticity is proven by the Stripe signature.
						'permission_callback' => '__return_true',
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
		$result = $this->receive( $request->get_body(), (string) $request->get_header( 'stripe_signature' ) );

		return new \WP_REST_Response( array( 'result' => $result['result'] ), $result['status'] );
	}

	/**
	 * Verify and process a raw webhook request.
	 *
	 * @param string $payload   Raw request body, byte for byte as received.
	 * @param string $signature Stripe-Signature header value.
	 * @return array{status: int, result: string}
	 */
	public function receive( string $payload, string $signature ): array {
		if ( strlen( $payload ) > self::MAX_BODY_BYTES ) {
			$this->logger->warning( 'Webhook rejected: body too large.', array( 'bytes' => strlen( $payload ) ) );
			return array(
				'status' => 413,
				'result' => 'too_large',
			);
		}

		$secret = $this->config->stripe_webhook_secret();
		if ( null === $secret ) {
			$this->logger->error( 'Webhook received but no signing secret is configured for the active mode.' );
			return array(
				'status' => 500,
				'result' => 'not_configured',
			);
		}

		if ( '' === $signature ) {
			$this->logger->warning( 'Webhook rejected: missing Stripe-Signature header.' );
			return array(
				'status' => 400,
				'result' => 'invalid_signature',
			);
		}

		try {
			$event = Webhook::constructEvent( $payload, $signature, $secret, self::TOLERANCE );
		} catch ( SignatureVerificationException $e ) {
			// The payload is not logged: it is unauthenticated input.
			$this->logger->warning( 'Webhook rejected: signature verification failed.', array( 'reason' => $e->getMessage() ) );
			return array(
				'status' => 400,
				'result' => 'invalid_signature',
			);
		} catch ( UnexpectedValueException $e ) {
			$this->logger->warning( 'Webhook rejected: payload is not a valid event.' );
			return array(
				'status' => 400,
				'result' => 'invalid_payload',
			);
		}

		try {
			$outcome = $this->processor->process( $event->toArray() );
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Webhook processing failed; Stripe will retry.',
				array(
					'event'     => (string) $event->id,
					'exception' => $e,
				)
			);
			return array(
				'status' => 500,
				'result' => 'error',
			);
		}

		return array(
			'status' => 200,
			'result' => $outcome,
		);
	}
}
