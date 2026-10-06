<?php
/**
 * Uninstall routine.
 *
 * Payment records are financial data. By default they are KEPT when the
 * plugin is deleted. Tables are only dropped if the site owner adds this to
 * wp-config.php before deleting the plugin:
 *
 *     define( 'EZ37_PAYMENTS_ALLOW_DATA_DELETION', true );
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$ez37_payments_autoload = __DIR__ . '/vendor/autoload.php';

if ( ! is_readable( $ez37_payments_autoload ) ) {
	return;
}

require_once $ez37_payments_autoload;

$ez37_payments_admin = get_role( 'administrator' );
if ( null !== $ez37_payments_admin ) {
	$ez37_payments_admin->remove_cap( \EZ37\Payments\Plugin::CAPABILITY );
}

if ( defined( 'EZ37_PAYMENTS_ALLOW_DATA_DELETION' ) && true === EZ37_PAYMENTS_ALLOW_DATA_DELETION ) {
	\EZ37\Payments\Install\Schema::drop_tables();
}
