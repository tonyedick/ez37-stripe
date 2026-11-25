<?php
/**
 * Front-end stylesheet.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Frontend;

/**
 * Registers assets/ez37-payments.css and loads it only on pages that render
 * one of the plugin's shortcodes.
 *
 * The styles are intentionally minimal and driven by CSS custom properties,
 * so the site's Elementor kit stays in charge of typography and colours.
 * Filter ez37_payments_load_styles (bool) switches them off.
 */
final class Styles {

	public const HANDLE = 'ez37-payments';

	/**
	 * Register the stylesheet.
	 */
	public function register(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_style' ) );
	}

	/**
	 * Register (not enqueue) the stylesheet.
	 */
	public function register_style(): void {
		wp_register_style( self::HANDLE, plugins_url( 'assets/ez37-payments.css', EZ37_PAYMENTS_FILE ), array(), EZ37_PAYMENTS_VERSION );
	}

	/**
	 * Enqueue the agreement drawer script (called when a form shows an agreement).
	 */
	public static function enqueue_drawer(): void {
		wp_enqueue_script(
			'ez37-agreement-drawer',
			plugins_url( 'assets/agreement-drawer.js', EZ37_PAYMENTS_FILE ),
			array(),
			EZ37_PAYMENTS_VERSION,
			true
		);
	}

	/**
	 * Enqueue the stylesheet (called from shortcode callbacks).
	 */
	public static function enqueue(): void {
		if ( ! (bool) apply_filters( 'ez37_payments_load_styles', true ) ) {
			return;
		}

		wp_enqueue_style( self::HANDLE );
	}
}
