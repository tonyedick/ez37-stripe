<?php
/**
 * [ez37_payment_status] and [ez37_payment_cancelled] shortcodes.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Frontend;

use EZ37\Payments\Offerings\OfferingCatalogue;

/**
 * The two pages Stripe returns customers to.
 *
 * [ez37_payment_status]     On the success page. Shows the real payment state
 *                           (read-only, decision D9) and polls while pending.
 * [ez37_payment_cancelled]  On the cancel page. Says nothing was charged and
 *                           links back to the checkout for the same offering.
 *                           Attribute: checkout_path (default "/checkout/").
 */
final class ReturnPageShortcodes {

	public const STATUS_TAG    = 'ez37_payment_status';
	public const CANCELLED_TAG = 'ez37_payment_cancelled';
	public const RECEIVED_TAG  = 'ez37_enrolment_received';

	/**
	 * How often the success page re-checks while pending (milliseconds), and how many times.
	 */
	public const POLL_INTERVAL_MS = 3000;
	public const POLL_ATTEMPTS    = 10;

	/**
	 * Constructor.
	 *
	 * @param StatusService     $status    Status lookup.
	 * @param OfferingCatalogue $catalogue Offerings.
	 */
	public function __construct(
		private readonly StatusService $status,
		private readonly OfferingCatalogue $catalogue
	) {}

	/**
	 * Register shortcodes and privacy headers.
	 */
	public function register(): void {
		add_shortcode( self::STATUS_TAG, array( $this, 'render_status' ) );
		add_shortcode( self::CANCELLED_TAG, array( $this, 'render_cancelled' ) );
		add_shortcode( self::RECEIVED_TAG, array( $this, 'render_received' ) );
		add_action( 'send_headers', array( $this, 'send_privacy_headers' ) );
	}

	/**
	 * When a session ID is in the URL: don't cache, don't index, don't leak it via Referer.
	 */
	public function send_privacy_headers(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only check for presence.
		if ( ! isset( $_GET['session_id'] ) || headers_sent() ) {
			return;
		}

		nocache_headers();
		header( 'Referrer-Policy: no-referrer' );
		header( 'X-Robots-Tag: noindex, nofollow' );
	}

	/**
	 * [ez37_payment_status].
	 */
	public function render_status(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only lookup key from Stripe's redirect.
		$session_id = isset( $_GET['session_id'] ) ? sanitize_text_field( wp_unslash( $_GET['session_id'] ) ) : '';
		$result     = $this->status->lookup( $session_id );

		Styles::enqueue();

		if ( StatusService::STATE_PENDING === $result['state'] ) {
			wp_enqueue_script(
				'ez37-payment-status',
				plugins_url( 'assets/payment-status.js', EZ37_PAYMENTS_FILE ),
				array(),
				EZ37_PAYMENTS_VERSION,
				true
			);
			wp_localize_script(
				'ez37-payment-status',
				'ez37PaymentStatus',
				array(
					'endpoint'    => rest_url( StatusController::ROUTE_NAMESPACE . StatusController::ROUTE ),
					'sessionId'   => $session_id,
					'intervalMs'  => self::POLL_INTERVAL_MS,
					'maxAttempts' => self::POLL_ATTEMPTS,
				)
			);
		}

		return Template::render( 'payment-status.php', $result );
	}

	/**
	 * [ez37_payment_cancelled].
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render_cancelled( $atts = array() ): string {
		$atts = shortcode_atts( array( 'checkout_path' => '/checkout/' ), is_array( $atts ) ? $atts : array(), self::CANCELLED_TAG );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display parameter.
		$key      = isset( $_GET['offering'] ) ? sanitize_key( wp_unslash( $_GET['offering'] ) ) : '';
		$offering = $this->catalogue->find( $key );
		$retry    = '';

		Styles::enqueue();

		if ( null !== $offering && $offering->active && 1 === preg_match( '#^/[A-Za-z0-9/_-]*$#', $atts['checkout_path'] ) ) {
			$retry = add_query_arg( 'offering', rawurlencode( $offering->key ), home_url( $atts['checkout_path'] ) );
		}

		return Template::render(
			'payment-cancelled.php',
			array(
				'offering_name' => null === $offering ? '' : $offering->name,
				'retry_url'     => $retry,
			)
		);
	}

	/**
	 * [ez37_enrolment_received]: shown after an enrolment that will be invoiced (nothing was charged).
	 */
	public function render_received(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only display of a public offering name.
		$key      = isset( $_GET['offering'] ) ? sanitize_key( wp_unslash( $_GET['offering'] ) ) : '';
		$offering = '' !== $key ? $this->catalogue->find( $key ) : null;
		$name     = null !== $offering ? $offering->name : __( 'the programme', 'ez37-payments' );

		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( Styles::HANDLE );
		}

		if ( null !== $offering && $offering->free ) {
			return '<div class="ez37-status ez37-status--paid" role="status">'
				. '<h2 class="ez37-status__title">' . esc_html__( 'Registration received', 'ez37-payments' ) . '</h2>'
				. '<p>' . esc_html( sprintf( /* translators: %s: programme name */ __( 'Thank you for registering for %s. We have your details and your acceptance of the Peer-to-Peer Agreement.', 'ez37-payments' ), $name ) ) . '</p>'
				. '<p>' . esc_html__( 'There is no charge. Our team will email you shortly with the link to the list of participants so you can choose your peer coaching partner(s).', 'ez37-payments' ) . '</p>'
				. '</div>';
		}

		return '<div class="ez37-status ez37-status--paid" role="status">'
			. '<h2 class="ez37-status__title">' . esc_html__( 'Enrolment received', 'ez37-payments' ) . '</h2>'
			. '<p>' . esc_html( sprintf( /* translators: %s: programme name */ __( 'Thank you for enrolling in the %s. We have your details and your acceptance of the Enrolment Agreement.', 'ez37-payments' ), $name ) ) . '</p>'
			. '<p>' . esc_html__( 'You have not been charged. Our team will contact you shortly with an invoice based on the payment option you chose. A confirmation has also been sent to your email.', 'ez37-payments' ) . '</p>'
			. '</div>';
	}
}
