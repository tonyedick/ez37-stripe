<?php
/**
 * Tests for the checkout request handler helpers, shortcode messages and return-path settings.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

namespace EZ37\Payments\Tests\Unit;

use Brain\Monkey\Functions;
use EZ37\Payments\Checkout\CheckoutException;
use EZ37\Payments\Checkout\CheckoutRequestHandler;
use EZ37\Payments\Config;
use EZ37\Payments\Frontend\CheckoutShortcode;
use EZ37\Payments\Offerings\OfferingCatalogue;

final class CheckoutFrontTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( static fn ( $url, $component = -1 ) => parse_url( $url, $component ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		Functions\when( '__' )->returnArg();
	}

	public function test_only_stripe_checkout_urls_are_followed(): void {
		$this->assertTrue( CheckoutRequestHandler::is_stripe_checkout_url( 'https://checkout.stripe.com/c/pay/cs_test_1#abc' ) );

		foreach ( array(
			'http://checkout.stripe.com/c/pay/cs_test_1',
			'https://checkout.stripe.com.evil.example/c/pay',
			'https://evil.example/?https://checkout.stripe.com',
			'javascript:alert(1)',
			'',
		) as $url ) {
			$this->assertFalse( CheckoutRequestHandler::is_stripe_checkout_url( $url ), $url );
		}
	}

	public function test_error_messages_are_generic_and_never_empty_for_known_codes(): void {
		$this->assertSame( '', CheckoutShortcode::error_message( '' ) );

		foreach ( array(
			CheckoutException::INVALID_INPUT,
			CheckoutException::SECURITY,
			CheckoutException::RATE_LIMITED,
			CheckoutException::UNAVAILABLE,
			CheckoutException::DISABLED,
			CheckoutException::STRIPE_ERROR,
			'anything-else',
		) as $code ) {
			$message = CheckoutShortcode::error_message( $code );
			$this->assertNotSame( '', $message, $code );
			$this->assertStringNotContainsStringIgnoringCase( 'stripe_error', $message );
		}
	}

	public function test_return_paths_default_and_override(): void {
		$default = new Config( array() );
		$this->assertSame( '/payment-complete/', $default->success_path() );
		$this->assertSame( '/payment-cancelled/', $default->cancel_path() );

		$custom = new Config(
			array(
				'EZ37_PAYMENTS_SUCCESS_PATH' => '/thank-you/',
				'EZ37_PAYMENTS_CANCEL_PATH'  => '/checkout/',
			)
		);
		$this->assertSame( '/thank-you/', $custom->success_path() );
		$this->assertSame( '/checkout/', $custom->cancel_path() );
	}

	public function test_unsafe_return_paths_fall_back_to_defaults_with_warning(): void {
		foreach ( array( 'https://evil.example/', '//evil.example/', 'thank-you', '/thank you/', '/a?b=c' ) as $path ) {
			$config = new Config( array( 'EZ37_PAYMENTS_SUCCESS_PATH' => $path ) );
			$this->assertSame( '/payment-complete/', $config->success_path(), $path );
			$this->assertNotEmpty( $config->warnings(), $path );
		}
	}

	public function test_price_shortcode_shows_configured_price_only_for_active_offerings(): void {
		Functions\when( 'shortcode_atts' )->alias( static fn ( $defaults, $atts ) => array_merge( $defaults, $atts ) );
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'esc_html' )->alias( static fn ( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );

		$base      = array(
			'currency'  => 'usd',
			'amount'    => 150000,
			'price_ids' => array(
				'test' => 'price_TEST',
				'live' => '',
			),
		);
		$shortcode = new CheckoutShortcode(
			new Config( array() ),
			new OfferingCatalogue(
				array(
					'leadership-coaching' => $base + array(
						'name'   => 'Leadership Coaching',
						'active' => true,
					),
					'old-programme'       => $base + array(
						'name'   => 'Old',
						'active' => false,
					),
				)
			)
		);

		$this->assertSame( '<span class="ez37-price">$1,500.00 USD</span>', $shortcode->render_price( array( 'offering' => 'leadership-coaching' ) ) );
		$this->assertSame( '', $shortcode->render_price( array( 'offering' => 'old-programme' ) ) );
		$this->assertSame( '', $shortcode->render_price( array( 'offering' => 'unknown' ) ) );
		$this->assertSame( '', $shortcode->render_price( '' ) );
	}
}
