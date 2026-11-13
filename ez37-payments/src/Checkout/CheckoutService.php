<?php
/**
 * Starts a Stripe Checkout.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Checkout;

use EZ37\Payments\Config;
use EZ37\Payments\Offerings\Offering;
use EZ37\Payments\Offerings\OfferingCatalogue;
use EZ37\Payments\Stripe\GatewayException;
use EZ37\Payments\Stripe\StripeGateway;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Transactions\TransactionRepository;

/**
 * Turns validated input into a pending transaction and a Stripe Checkout Session.
 *
 * Price authority: the amount, currency and Stripe Price come only from the
 * offering configuration, and the Stripe Price is checked against that
 * configuration before any session is created.
 *
 * This class never marks anything as paid. Only verified webhooks do (Phase 4).
 */
final class CheckoutService {

	/**
	 * Checkout Session lifetime. Stripe allows 30 minutes to 24 hours.
	 */
	public const SESSION_LIFETIME = 3600;

	/**
	 * How long a successful Price check is cached.
	 */
	public const PRICE_CHECK_TTL = 600;

	/**
	 * Constructor.
	 *
	 * @param Config                $config       Configuration.
	 * @param OfferingCatalogue     $catalogue    Offerings.
	 * @param StripeGateway         $gateway      Stripe API boundary.
	 * @param TransactionRepository $transactions Transaction storage.
	 * @param Logger                $logger       Logger.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly OfferingCatalogue $catalogue,
		private readonly StripeGateway $gateway,
		private readonly TransactionRepository $transactions,
		private readonly Logger $logger
	) {}

	/**
	 * Start a checkout and return the Stripe-hosted Checkout URL.
	 *
	 * @param CheckoutInput $input Validated customer input.
	 *
	 * @throws CheckoutException When checkout cannot start; reason says why.
	 */
	public function start( CheckoutInput $input ): string {
		if ( ! $this->config->can_accept_payments() ) {
			throw new CheckoutException( CheckoutException::DISABLED, 'Checkout is disabled or misconfigured.' );
		}

		$mode     = $this->config->mode();
		$offering = $this->catalogue->find( $input->offering_key );

		if ( null === $offering || ! $offering->is_purchasable( $mode ) ) {
			throw new CheckoutException( CheckoutException::UNAVAILABLE, 'Offering unknown, inactive or without a price in ' . $mode . ' mode.' );
		}

		$price_id = (string) $offering->price_id( $mode );
		$this->verify_price( $offering, $price_id );

		try {
			$transaction = $this->transactions->create_pending( $offering, $price_id, $mode, $input->name, $input->email );
		} catch ( \RuntimeException $e ) {
			$this->logger->error( 'Could not create transaction.', array( 'exception' => $e ) );
			throw new CheckoutException( CheckoutException::STRIPE_ERROR, 'Database error.', $e );
		}

		try {
			$session = $this->gateway->create_checkout_session(
				$this->session_params( $offering, $price_id, $input, $transaction['public_id'] ),
				'ez37-checkout-' . $transaction['public_id']
			);
		} catch ( GatewayException $e ) {
			$this->transactions->mark_session_failed( $transaction['id'], '' === $e->stripe_code ? 'gateway_error' : $e->stripe_code, $e->getMessage() );
			$this->logger->error(
				'Checkout Session creation failed.',
				array(
					'transaction' => $transaction['public_id'],
					'offering'    => $offering->key,
					'exception'   => $e,
				)
			);
			throw new CheckoutException( CheckoutException::STRIPE_ERROR, 'Stripe error.', $e );
		}

		$this->transactions->attach_checkout_session( $transaction['id'], $session['id'] );

		$this->logger->info(
			'Checkout started.',
			array(
				'transaction' => $transaction['public_id'],
				'offering'    => $offering->key,
				'session'     => $session['id'],
				'mode'        => $mode,
			)
		);

		return $session['url'];
	}

	/**
	 * Checkout Session parameters.
	 *
	 * @param Offering      $offering  Offering.
	 * @param string        $price_id  Stripe Price ID.
	 * @param CheckoutInput $input     Customer input.
	 * @param string        $public_id Transaction public ID.
	 * @return array<string, mixed>
	 */
	public function session_params( Offering $offering, string $price_id, CheckoutInput $input, string $public_id ): array {
		$metadata = array(
			'ez37_transaction' => $public_id,
			'ez37_offering'    => $offering->key,
		);

		return array(
			'mode'                         => 'payment',
			'line_items'                   => array(
				array(
					'price'    => $price_id,
					'quantity' => 1,
				),
			),
			'customer_email'               => $input->email,
			'customer_creation'            => 'always',
			'client_reference_id'          => $public_id,
			'metadata'                     => $metadata,
			'payment_intent_data'          => array( 'metadata' => $metadata ),
			// Cards only; Apple Pay / Google Pay run on the card method (decision D3).
			// API 2026-10-28 rejects payment_method_types; this filters the
			// payment methods enabled in the Dashboard instead.
			'allowed_payment_method_types' => array( 'card' ),
			// Always charge in the offering currency (USD, decision D2), whatever the
			// account's Adaptive Pricing setting is.
			'adaptive_pricing'             => array( 'enabled' => false ),
			'expires_at'                   => time() + self::SESSION_LIFETIME,
			'success_url'                  => $this->config->success_url() . ( str_contains( $this->config->success_url(), '?' ) ? '&' : '?' ) . 'session_id={CHECKOUT_SESSION_ID}',
			'cancel_url'                   => add_query_arg( 'offering', rawurlencode( $offering->key ), $this->config->cancel_url() ),
		);
	}

	/**
	 * Check that the Stripe Price matches the offering configuration.
	 *
	 * A mismatch (wrong amount, currency, inactive, recurring, wrong mode)
	 * blocks checkout for that offering, so a configuration mistake can never
	 * charge a customer the wrong amount.
	 *
	 * @param Offering $offering Offering.
	 * @param string   $price_id Stripe Price ID.
	 *
	 * @throws CheckoutException When the Price cannot be verified.
	 */
	private function verify_price( Offering $offering, string $price_id ): void {
		$cache_key = 'ez37_price_ok_' . md5( $this->config->mode() . '|' . $price_id . '|' . $offering->amount . '|' . $offering->currency );

		if ( false !== get_transient( $cache_key ) ) {
			return;
		}

		try {
			$price = $this->gateway->retrieve_price( $price_id );
		} catch ( GatewayException $e ) {
			$this->logger->error(
				'Could not verify Stripe Price.',
				array(
					'offering'  => $offering->key,
					'exception' => $e,
				)
			);
			throw new CheckoutException( CheckoutException::STRIPE_ERROR, 'Price lookup failed.', $e );
		}

		$problems = array();

		if ( ! $price['active'] ) {
			$problems[] = 'price is archived';
		}
		if ( 'one_time' !== $price['type'] ) {
			$problems[] = 'price is not one-time';
		}
		if ( $price['currency'] !== $offering->currency ) {
			$problems[] = sprintf( 'currency %s does not match configured %s', $price['currency'], $offering->currency );
		}
		if ( $price['unit_amount'] !== $offering->amount ) {
			$problems[] = sprintf( 'amount %s does not match configured %d', var_export( $price['unit_amount'], true ), $offering->amount ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export -- Log detail.
		}
		if ( $price['livemode'] !== $this->config->is_live() ) {
			$problems[] = 'price belongs to the other Stripe mode';
		}

		if ( array() !== $problems ) {
			$this->logger->error(
				'Stripe Price does not match offering configuration; checkout blocked.',
				array(
					'offering' => $offering->key,
					'price'    => $price_id,
					'problems' => $problems,
				)
			);
			throw new CheckoutException( CheckoutException::UNAVAILABLE, 'Price mismatch: ' . implode( '; ', $problems ) );
		}

		set_transient( $cache_key, 1, self::PRICE_CHECK_TTL );
	}
}
