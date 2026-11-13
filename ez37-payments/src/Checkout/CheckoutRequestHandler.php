<?php
/**
 * Handles the checkout form POST.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Checkout;

use EZ37\Payments\Support\ClientIp;
use EZ37\Payments\Support\Logger;

/**
 * Receives the checkout form via admin-post.php (action "ez37_checkout").
 *
 * The admin-post.php endpoint is used rather than a REST route because the form is a
 * plain HTML form: it works without JavaScript, inside any Elementor layout,
 * and lets us redirect the browser straight to Stripe (303) or back to the
 * checkout page with an error code.
 *
 * Checks, in order: same-site Origin/Referer, nonce, honeypot, IP rate limit,
 * input validation, email rate limit. Then CheckoutService does the rest.
 */
final class CheckoutRequestHandler {

	public const ACTION      = 'ez37_checkout';
	public const NONCE_FIELD = '_ez37_nonce';

	/**
	 * Honeypot field: hidden from people, often filled in by bots.
	 */
	public const HONEYPOT_FIELD = 'ez37_website';

	/**
	 * Stripe-hosted Checkout host. Redirects anywhere else are refused.
	 */
	public const STRIPE_CHECKOUT_HOST = 'checkout.stripe.com';

	/**
	 * Constructor.
	 *
	 * @param CheckoutService $service      Checkout service.
	 * @param RateLimiter     $rate_limiter Abuse throttling.
	 * @param Logger          $logger       Logger.
	 */
	public function __construct(
		private readonly CheckoutService $service,
		private readonly RateLimiter $rate_limiter,
		private readonly Logger $logger
	) {}

	/**
	 * Register admin-post hooks (logged-out visitors and logged-in users).
	 */
	public function register(): void {
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Handle the request and redirect. Never returns.
	 */
	public function handle(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Nonce is verified in process().
		$post = wp_unslash( $_POST );
		// phpcs:enable

		$offering = is_string( $post['offering'] ?? null ) ? sanitize_key( $post['offering'] ) : '';

		try {
			$url = $this->process( $post );
		} catch ( CheckoutException $e ) {
			$this->logger->warning(
				'Checkout refused.',
				array(
					'reason'   => $e->reason,
					'detail'   => $e->getMessage(),
					'offering' => $offering,
				)
			);
			$this->redirect( $this->error_url( $e->reason, $offering ), 303 );
		}

		$this->redirect( $url, 303 );
	}

	/**
	 * Run every check and start the checkout. Returns the Stripe Checkout URL.
	 *
	 * @param array<string, mixed> $post Unslashed POST data.
	 *
	 * @throws CheckoutException When any check fails.
	 */
	public function process( array $post ): string {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		if ( 'POST' !== $method ) {
			throw new CheckoutException( CheckoutException::SECURITY, 'Not a POST request.' );
		}

		if ( ! $this->is_same_site_request() ) {
			throw new CheckoutException( CheckoutException::SECURITY, 'Origin/Referer is not this site.' );
		}

		$nonce = is_string( $post[ self::NONCE_FIELD ] ?? null ) ? $post[ self::NONCE_FIELD ] : '';
		if ( false === wp_verify_nonce( $nonce, self::ACTION ) ) {
			throw new CheckoutException( CheckoutException::SECURITY, 'Invalid or expired nonce.' );
		}

		if ( '' !== ( is_string( $post[ self::HONEYPOT_FIELD ] ?? null ) ? $post[ self::HONEYPOT_FIELD ] : '' ) ) {
			throw new CheckoutException( CheckoutException::SECURITY, 'Honeypot filled.' );
		}

		if ( ! $this->rate_limiter->attempt( 'ip', ClientIp::get() ) ) {
			throw new CheckoutException( CheckoutException::RATE_LIMITED, 'Too many attempts from this IP.' );
		}

		$input = CheckoutInput::from_request( $post );

		if ( ! $this->rate_limiter->attempt( 'email', $input->email ) ) {
			throw new CheckoutException( CheckoutException::RATE_LIMITED, 'Too many attempts for this email.' );
		}

		$url = $this->service->start( $input );

		if ( ! self::is_stripe_checkout_url( $url ) ) {
			throw new CheckoutException( CheckoutException::STRIPE_ERROR, 'Unexpected Checkout URL host.' );
		}

		return $url;
	}

	/**
	 * Whether a URL is an https URL on Stripe's Checkout host.
	 *
	 * @param string $url URL returned by Stripe.
	 */
	public static function is_stripe_checkout_url( string $url ): bool {
		$parts = wp_parse_url( $url );

		return is_array( $parts )
			&& 'https' === ( $parts['scheme'] ?? '' )
			&& self::STRIPE_CHECKOUT_HOST === ( $parts['host'] ?? '' );
	}

	/**
	 * CSRF defence in depth: the browser's Origin (or, failing that, Referer)
	 * must be this site. Modern browsers send Origin on every form POST.
	 */
	private function is_same_site_request(): bool {
		$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$source    = $_SERVER['HTTP_ORIGIN'] ?? ( $_SERVER['HTTP_REFERER'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Only its host is compared.

		if ( ! is_string( $source ) || '' === $source || 'null' === $source ) {
			return false;
		}

		return is_string( $site_host ) && strtolower( (string) wp_parse_url( $source, PHP_URL_HOST ) ) === strtolower( $site_host );
	}

	/**
	 * Page to send the customer back to after an error, with a reason code.
	 *
	 * @param string $reason   CheckoutException reason.
	 * @param string $offering Offering key (already sanitised).
	 */
	private function error_url( string $reason, string $offering ): string {
		$back = wp_validate_redirect( (string) wp_get_referer(), home_url( '/' ) );
		$back = remove_query_arg( array( 'ez37_error' ), $back );

		$args = array( 'ez37_error' => $reason );
		if ( '' !== $offering ) {
			$args['offering'] = $offering;
		}

		return add_query_arg( $args, $back ) . '#ez37-checkout';
	}

	/**
	 * Redirect and stop.
	 *
	 * @param string $url    Target.
	 * @param int    $status HTTP status.
	 */
	private function redirect( string $url, int $status ): never {
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Target is either this site (validated) or checkout.stripe.com (validated).
		wp_redirect( $url, $status );
		exit;
	}
}
