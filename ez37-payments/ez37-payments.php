<?php
/**
 * Plugin Name:       EZ37 Payments
 * Description:       Custom Stripe Checkout payments, fulfilment and secure resource delivery for EZ37 Solutions.
 * Version:           0.7.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            EZ37 Solutions
 * License:           Proprietary
 * Text Domain:       ez37-payments
 * Domain Path:       /languages
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

define( 'EZ37_PAYMENTS_VERSION', '0.7.0' );
define( 'EZ37_PAYMENTS_FILE', __FILE__ );
define( 'EZ37_PAYMENTS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EZ37_PAYMENTS_MIN_PHP', '8.1' );

/*
 * Requirement checks run before anything else is loaded, so a misconfigured
 * server shows a clear admin notice instead of a fatal error.
 */
if ( version_compare( PHP_VERSION, EZ37_PAYMENTS_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: 1: required PHP version, 2: current PHP version */
						__( 'EZ37 Payments requires PHP %1$s or later. This server runs PHP %2$s. The plugin is inactive.', 'ez37-payments' ),
						EZ37_PAYMENTS_MIN_PHP,
						PHP_VERSION
					)
				)
			);
		}
	);
	return;
}

$ez37_payments_autoload = EZ37_PAYMENTS_DIR . 'vendor/autoload.php';

if ( ! is_readable( $ez37_payments_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'EZ37 Payments is missing its dependencies (vendor/autoload.php). Install the release build, or run "composer install" in the plugin folder for development.', 'ez37-payments' )
			);
		}
	);
	return;
}

require_once $ez37_payments_autoload;
unset( $ez37_payments_autoload );

register_activation_hook( __FILE__, array( \EZ37\Payments\Install\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \EZ37\Payments\Install\Activator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \EZ37\Payments\Plugin::class, 'boot' ) );
