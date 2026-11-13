<?php
/**
 * PHPUnit bootstrap for unit tests.
 *
 * Unit tests run without WordPress or a database. WordPress functions are
 * mocked per test with Brain Monkey. ABSPATH points at a tiny fake tree so
 * that code which require_once's core admin files can be exercised.
 *
 * @package EZ37\Payments\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/fake-wp/' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core constant, faked for unit tests.
}

if ( ! defined( 'EZ37_PAYMENTS_DIR' ) ) {
	define( 'EZ37_PAYMENTS_DIR', dirname( __DIR__, 2 ) . '/' );
}

if ( ! defined( 'EZ37_PAYMENTS_FILE' ) ) {
	define( 'EZ37_PAYMENTS_FILE', dirname( __DIR__, 2 ) . '/ez37-payments.php' );
}
