<?php
/**
 * [ez37_checkout] shortcode.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Frontend;

use EZ37\Payments\Checkout\CheckoutException;
use EZ37\Payments\Checkout\CheckoutRequestHandler;
use EZ37\Payments\Config;
use EZ37\Payments\Offerings\OfferingCatalogue;

/**
 * Renders the checkout form for one offering.
 *
 * Usage in Elementor (Shortcode widget) or any page:
 *   [ez37_checkout offering="leadership-coaching"]
 * or put [ez37_checkout] on one checkout page and link buttons to
 *   /checkout/?offering=leadership-coaching
 *
 * The form carries only the offering key, name and email. Price and currency
 * are displayed from server configuration and never submitted.
 *
 * Also provides [ez37_offering_price offering="leadership-coaching"], which
 * prints the configured price (e.g. "$1.00 USD") for use in Elementor text,
 * so prices are never typed into page content by hand.
 */
final class CheckoutShortcode {

	public const TAG       = 'ez37_checkout';
	public const PRICE_TAG = 'ez37_offering_price';

	/**
	 * Constructor.
	 *
	 * @param Config            $config    Configuration.
	 * @param OfferingCatalogue $catalogue Offerings.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly OfferingCatalogue $catalogue
	) {}

	/**
	 * Register the shortcode.
	 */
	public function register(): void {
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_shortcode( self::PRICE_TAG, array( $this, 'render_price' ) );
	}

	/**
	 * [ez37_offering_price offering="key"]: the configured price, or nothing
	 * when the offering is unknown or inactive.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render_price( $atts = array() ): string {
		$atts     = shortcode_atts( array( 'offering' => '' ), is_array( $atts ) ? $atts : array(), self::PRICE_TAG );
		$offering = $this->catalogue->find( sanitize_key( $atts['offering'] ) );

		if ( null === $offering || ! $offering->active ) {
			return '';
		}

		return '<span class="ez37-price">' . esc_html( $offering->formatted_price() ) . '</span>';
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string, string>|string $atts Shortcode attributes.
	 */
	public function render( $atts = array() ): string {
		$atts = shortcode_atts( array( 'offering' => '' ), is_array( $atts ) ? $atts : array(), self::TAG );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only display parameters.
		$key   = '' !== $atts['offering'] ? $atts['offering'] : ( isset( $_GET['offering'] ) ? sanitize_key( wp_unslash( $_GET['offering'] ) ) : '' );
		$error = isset( $_GET['ez37_error'] ) ? sanitize_key( wp_unslash( $_GET['ez37_error'] ) ) : '';
		// phpcs:enable

		$offering = $this->catalogue->find( sanitize_key( $key ) );
		$mode     = $this->config->mode();

		Styles::enqueue();

		if ( null === $offering || ! $offering->is_purchasable( $mode ) || ! $this->config->can_accept_payments() ) {
			return Template::render(
				'checkout-unavailable.php',
				array( 'message' => __( 'This programme is not available for online purchase at the moment. Please contact us for details.', 'ez37-payments' ) )
			);
		}

		return Template::render(
			'checkout-form.php',
			array(
				'offering'       => $offering,
				'action_url'     => admin_url( 'admin-post.php' ),
				'action'         => CheckoutRequestHandler::ACTION,
				'nonce_field'    => CheckoutRequestHandler::NONCE_FIELD,
				'honeypot_field' => CheckoutRequestHandler::HONEYPOT_FIELD,
				'error_message'  => self::error_message( $error ),
				'is_test_mode'   => ! $this->config->is_live(),
			)
		);
	}

	/**
	 * Friendly message for an error code. Deliberately generic.
	 *
	 * @param string $code CheckoutException reason, or empty.
	 */
	public static function error_message( string $code ): string {
		return match ( $code ) {
			''                              => '',
			CheckoutException::INVALID_INPUT => __( 'Please enter your full name and a valid email address.', 'ez37-payments' ),
			CheckoutException::SECURITY      => __( 'Your session expired. Please try again.', 'ez37-payments' ),
			CheckoutException::RATE_LIMITED  => __( 'Too many attempts. Please wait a few minutes and try again.', 'ez37-payments' ),
			CheckoutException::UNAVAILABLE,
			CheckoutException::DISABLED      => __( 'This programme is not available for online purchase at the moment.', 'ez37-payments' ),
			default                          => __( 'We could not start the payment. No money has been taken. Please try again shortly.', 'ez37-payments' ),
		};
	}
}
