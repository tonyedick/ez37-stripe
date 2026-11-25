<?php
/**
 * EZ37 Payments — DEMO PREFLIGHT. LOCAL DEVELOPMENT ONLY.
 *
 * One command that checks everything the payment flow needs, says exactly what to fix, and warms
 * the Stripe price check so the first checkout in a demo is as fast as the hundredth.
 * Prints no secrets (only prefixes and lengths).
 *
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/demo-preflight.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run this with WP-CLI eval-file.\n" );
}
if ( 'local' !== wp_get_environment_type() && false === strpos( home_url(), 'ddev.site' ) ) {
	WP_CLI::error( 'Refusing to run: this does not look like the local DDEV site.' );
}

use EZ37\Payments\Agreements\AgreementCatalogue;
use EZ37\Payments\Offerings\OfferingCatalogue;
use EZ37\Payments\Plugin;
use EZ37\Payments\Resources\ResourceCatalogue;
use EZ37\Payments\Stripe\StripeGateway;

$bad = 0;
$ok  = static function ( string $msg ): void {
	WP_CLI::log( "  [ OK ] $msg" );
};
$no  = static function ( string $msg, string $fix ) use ( &$bad ): void {
	++$bad;
	WP_CLI::log( "  [FAIL] $msg\n         -> $fix" );
};
$warn = static function ( string $msg ): void {
	WP_CLI::log( "  [WARN] $msg" );
};

WP_CLI::log( "\nEZ37 demo preflight\n" );

if ( ! class_exists( Plugin::class ) ) {
	WP_CLI::error( 'The EZ37 Payments plugin is not active. Activate it first.' );
}

$config = Plugin::instance()->config();
$dir    = WP_PLUGIN_DIR . '/ez37-payments/';
if ( ! is_dir( $dir ) ) {
	$dir = EZ37_PAYMENTS_DIR;
}

WP_CLI::log( 'Configuration' );
'test' === $config->mode() ? $ok( 'Stripe mode is TEST' ) : $no( 'Stripe mode is "' . $config->mode() . '"', 'Set EZ37_STRIPE_MODE=test in .ddev/.env, then ddev restart' );
$config->is_checkout_enabled() ? $ok( 'Checkout is enabled' ) : $no( 'Checkout is switched off', 'Set EZ37_PAYMENTS_CHECKOUT_ENABLED=1 in .ddev/.env, then ddev restart' );
$key = (string) $config->stripe_secret_key();
'' !== $key ? $ok( 'Stripe test secret key is set (' . substr( $key, 0, 8 ) . '…)' ) : $no( 'No Stripe test secret key', 'Set EZ37_STRIPE_TEST_SECRET_KEY=sk_test_… in .ddev/.env, then ddev restart' );
$whsec = (string) $config->stripe_webhook_secret();
if ( '' === $whsec ) {
	$no( 'No webhook signing secret', 'Run: stripe listen --print-secret   then put it in .ddev/.env as EZ37_STRIPE_TEST_WEBHOOK_SECRET, then ddev restart' );
} else {
	$ok( 'Webhook secret is set (' . substr( $whsec, 0, 8 ) . '…, length ' . strlen( $whsec ) . ')' );
	if ( strlen( $whsec ) < 60 ) {
		$warn( 'It is short for a Stripe CLI secret. If the CLI shows [400], compare it with: stripe listen --print-secret' );
	}
}
foreach ( $config->errors() as $e ) {
	$no( $e, 'Fix in .ddev/.env, then ddev restart' );
}

WP_CLI::log( "\nOffering, agreement and resource" );
$offerings = OfferingCatalogue::from_file( $dir . 'config/offerings.php' );
$offering  = $offerings->find( 'pccp' );
if ( null === $offering ) {
	$no( 'Offering "pccp" not found', 'Check ez37-payments/config/offerings.php' );
} else {
	$price_id = (string) $offering->price_id( $config->mode() );
	$offering->is_purchasable( $config->mode() ) ? $ok( 'PCCP is purchasable: ' . $offering->formatted_price() ) : $no( 'PCCP is not purchasable', 'It needs active=true and a test Price ID in config/offerings.php' );
	'' !== $price_id ? $ok( 'Test Price ID set (' . substr( $price_id, 0, 10 ) . '…)' ) : $no( 'No test Price ID for PCCP', 'Create a $2,699 one-time Price in the Stripe sandbox and paste its price_… into config/offerings.php (price_ids → test)' );
}

$agreements = AgreementCatalogue::from_file( $dir . 'config/agreements.php' );
null !== $offering && null !== $agreements->find( $offering->agreement_key ) ? $ok( 'Enrolment agreement found' ) : $no( 'Enrolment agreement missing', 'Check config/agreements.php (pccp-enrolment)' );

$resources = ResourceCatalogue::from_file( $dir . 'config/resources.php' );
$res       = $resources->find( 'pccp-programme-brochure' );
$pdir      = $config->private_dir();
if ( null === $res || null === $pdir ) {
	$no( 'Resource or private folder not configured', 'Check EZ37_PAYMENTS_PRIVATE_DIR in .ddev/.env and config/resources.php' );
} elseif ( null === $res->file_path( $pdir ) ) {
	$no( 'Brochure file not found in the private folder', 'Put brochure-placeholder.pdf in sample-resources/pccp/' );
} else {
	$ok( 'Brochure PDF is in place' );
}

WP_CLI::log( "\nPages and routes" );
foreach ( array( 'checkout', 'payment-complete', 'payment-cancelled' ) as $slug ) {
	get_page_by_path( $slug ) ? $ok( "/$slug/ exists" ) : $no( "/$slug/ is missing", 'Run bin/pages-local.php then bin/enrol-local.php' );
}
$routes = rest_get_server()->get_routes();
isset( $routes['/ez37/v1/stripe/webhook'] ) ? $ok( 'Webhook route registered' ) : $no( 'Webhook route not registered', 'Re-save permalinks: ddev exec wp --path=/var/www/html/wordpress rewrite flush' );
'' !== get_option( 'permalink_structure' ) ? $ok( 'Pretty permalinks on' ) : $no( 'Permalinks are "Plain"', 'Settings → Permalinks → Post name' );

WP_CLI::log( "\nEmail" );
$sent = wp_mail( 'preflight@example.test', 'EZ37 preflight test', 'If you can see this in Mailpit, email works.' );
$sent ? $ok( 'Test email sent: look for "EZ37 preflight test" in Mailpit (ddev launch -m)' ) : $no( 'wp_mail failed', 'Check DDEV Mailpit is running (ddev describe)' );

WP_CLI::log( "\nStripe connection (warms the price check)" );
if ( null !== $offering && '' !== $key && '' !== ( $price_id ?? '' ) ) {
	$t = microtime( true );
	try {
		$p = ( new StripeGateway( $config ) )->retrieve_price( $price_id );
		$ms = (int) ( ( microtime( true ) - $t ) * 1000 );
		if ( $p['active'] && 'one_time' === $p['type'] && $p['currency'] === $offering->currency && $p['unit_amount'] === $offering->amount && $p['livemode'] === $config->is_live() ) {
			$ok( "Stripe reachable ({$ms} ms); Price matches the configured amount" );
			set_transient( 'ez37_price_ok_' . md5( $config->mode() . '|' . $price_id . '|' . $offering->amount . '|' . $offering->currency ), 1, HOUR_IN_SECONDS );
			$ok( 'Price check warmed: the first checkout will not wait for it' );
		} else {
			$no( 'Stripe Price does not match the configuration', 'Price must be active, one-time, ' . strtoupper( $offering->currency ) . ' ' . ( $offering->amount / 100 ) . ', in test mode' );
		}
	} catch ( \Throwable $e ) {
		$no( 'Could not reach Stripe or read the Price: ' . $e->getMessage(), 'Check the secret key, the Price ID, and your internet connection' );
	}
}

WP_CLI::log( "\nStill to do on your Mac (cannot be checked from here)" );
WP_CLI::log( '  - stripe listen is running in a Terminal window and its secret matches .ddev/.env' );
WP_CLI::log( '  - Mailpit tab open: https://ez37.ddev.site:8026' );

WP_CLI::log( '' );
if ( 0 === $bad ) {
	WP_CLI::success( 'All checks passed. You are ready for the demo.' );
} else {
	WP_CLI::error( "$bad problem(s) found. Fix the lines marked FAIL, then run this again." );
}
