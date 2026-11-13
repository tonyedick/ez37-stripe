<?php
/**
 * Stripe API boundary.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Stripe;

use EZ37\Payments\Config;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

/**
 * The only class that talks to the Stripe SDK.
 *
 * Returns plain arrays so the rest of the plugin does not depend on SDK
 * object types, and converts every SDK error into a GatewayException.
 * Not final, so unit tests can replace it with a mock.
 */
class StripeGateway {

	/**
	 * Lazily created SDK client.
	 *
	 * @var StripeClient|null
	 */
	private ?StripeClient $client = null;

	/**
	 * Constructor.
	 *
	 * @param Config $config Configuration (supplies the secret key for the active mode).
	 */
	public function __construct( private readonly Config $config ) {}

	/**
	 * Create a Checkout Session.
	 *
	 * @param array<string, mixed> $params          Stripe Checkout Session parameters.
	 * @param string               $idempotency_key Makes retries of the same request safe.
	 * @return array{id: string, url: string, expires_at: int}
	 *
	 * @throws GatewayException When Stripe rejects the request or cannot be reached.
	 */
	public function create_checkout_session( array $params, string $idempotency_key ): array {
		try {
			$session = $this->client()->checkout->sessions->create( $params, array( 'idempotency_key' => $idempotency_key ) );
		} catch ( ApiErrorException $e ) {
			throw new GatewayException( self::describe( 'Checkout Session creation failed', $e ), (string) $e->getStripeCode(), (int) $e->getHttpStatus(), $e );
		}

		if ( ! is_string( $session->url ?? null ) || '' === $session->url ) {
			throw new GatewayException( 'Stripe returned a Checkout Session without a URL.' );
		}

		return array(
			'id'         => (string) $session->id,
			'url'        => (string) $session->url,
			'expires_at' => (int) $session->expires_at,
		);
	}

	/**
	 * Retrieve a Checkout Session (used to reconcile missed webhooks).
	 *
	 * @param string $session_id Checkout Session ID.
	 * @return array<string, mixed> The session as an array.
	 *
	 * @throws GatewayException When Stripe rejects the request or cannot be reached.
	 */
	public function retrieve_checkout_session( string $session_id ): array {
		try {
			$session = $this->client()->checkout->sessions->retrieve( $session_id );
		} catch ( ApiErrorException $e ) {
			throw new GatewayException( self::describe( 'Checkout Session lookup failed', $e ), (string) $e->getStripeCode(), (int) $e->getHttpStatus(), $e );
		}

		return $session->toArray();
	}

	/**
	 * Retrieve a Price.
	 *
	 * @param string $price_id Stripe Price ID.
	 * @return array{id: string, active: bool, currency: string, unit_amount: int|null, type: string, livemode: bool}
	 *
	 * @throws GatewayException When Stripe rejects the request or cannot be reached.
	 */
	public function retrieve_price( string $price_id ): array {
		try {
			$price = $this->client()->prices->retrieve( $price_id );
		} catch ( ApiErrorException $e ) {
			throw new GatewayException( self::describe( 'Price lookup failed', $e ), (string) $e->getStripeCode(), (int) $e->getHttpStatus(), $e );
		}

		return array(
			'id'          => (string) $price->id,
			'active'      => (bool) $price->active,
			'currency'    => (string) $price->currency,
			'unit_amount' => null === $price->unit_amount ? null : (int) $price->unit_amount,
			'type'        => (string) $price->type,
			'livemode'    => (bool) $price->livemode,
		);
	}

	/**
	 * The SDK client, created on first use.
	 *
	 * @throws GatewayException When no valid secret key is configured.
	 */
	private function client(): StripeClient {
		if ( null === $this->client ) {
			$key = $this->config->stripe_secret_key();

			if ( null === $key ) {
				throw new GatewayException( 'No valid Stripe secret key is configured for the active mode.' );
			}

			$this->client = new StripeClient( $key );
		}

		return $this->client;
	}

	/**
	 * Log-safe description of an SDK exception (the Logger also redacts keys).
	 *
	 * @param string            $context What was being attempted.
	 * @param ApiErrorException $e       SDK exception.
	 */
	private static function describe( string $context, ApiErrorException $e ): string {
		$code = (string) $e->getStripeCode();

		return sprintf( '%s (%s, HTTP %d): %s', $context, '' === $code ? get_class( $e ) : $code, (int) $e->getHttpStatus(), $e->getMessage() );
	}
}
