<?php
/**
 * Activation handling.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Install;

use EZ37\Payments\Plugin;
use EZ37\Payments\Transactions\Reconciler;

/**
 * Runs on plugin activation.
 *
 * Deactivation only stops scheduled jobs: payment records and the schema
 * are kept. Data is only removed on uninstall, and only with an
 * explicit opt-in (see uninstall.php).
 */
final class Activator {

	/**
	 * Activation hook callback.
	 *
	 * @param bool $network_wide Whether activated network-wide on multisite.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			wp_die(
				esc_html__( 'EZ37 Payments does not support network-wide activation. Activate it on the individual site instead.', 'ez37-payments' ),
				esc_html__( 'Plugin activation error', 'ez37-payments' ),
				array( 'back_link' => true )
			);
		}

		Schema::install();

		$admin = get_role( 'administrator' );
		if ( null !== $admin ) {
			$admin->add_cap( Plugin::CAPABILITY );
		}
	}

	/**
	 * Deactivation hook callback: stop scheduled jobs. Data is kept.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( Reconciler::CRON_HOOK );
	}
}
