<?php
/**
 * EZ37 Payments — create the local test pages (LOCAL DEVELOPMENT ONLY).
 *
 * Creates, or repairs, these published pages:
 *   /leadership-coaching/   programme page: price + "Enrol now" button
 *   /checkout/              [ez37_checkout]
 *   /payment-complete/      [ez37_payment_status]
 *   /payment-cancelled/     [ez37_payment_cancelled]
 *
 * Safe to re-run: an existing page is only changed if it lacks its shortcode.
 *
 * Run from the project root:
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/pages-local.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run this with WP-CLI eval-file.\n" );
}

if ( 'local' !== wp_get_environment_type() && false === strpos( home_url(), 'ddev.site' ) ) {
	WP_CLI::error( 'Refusing to run: this does not look like the local DDEV site.' );
}

$pages = array(
	'leadership-coaching' => array(
		'title'   => 'Leadership Coaching',
		'needle'  => '[ez37_offering_price',
		'content' => "<!-- wp:paragraph -->\n<p>Practical one-to-one coaching for leaders who want to grow. This is a test programme page for the payment flow.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p><strong>Price:</strong> [ez37_offering_price offering=\"leadership-coaching\"]</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button -->\n<div class=\"wp-block-button\"><a class=\"wp-block-button__link wp-element-button\" href=\"/checkout/?offering=leadership-coaching\">Enrol now</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->",
	),
	'checkout'            => array(
		'title'   => 'Checkout',
		'needle'  => '[ez37_checkout',
		'content' => "<!-- wp:shortcode -->\n[ez37_checkout]\n<!-- /wp:shortcode -->",
	),
	'payment-complete'    => array(
		'title'   => 'Payment complete',
		'needle'  => '[ez37_payment_status',
		'content' => "<!-- wp:shortcode -->\n[ez37_payment_status]\n<!-- /wp:shortcode -->",
	),
	'payment-cancelled'   => array(
		'title'   => 'Payment cancelled',
		'needle'  => '[ez37_payment_cancelled',
		'content' => "<!-- wp:shortcode -->\n[ez37_payment_cancelled]\n<!-- /wp:shortcode -->",
	),
);

foreach ( $pages as $slug => $def ) {
	$existing = get_page_by_path( $slug, OBJECT, 'page' );

	if ( $existing ) {
		$has_shortcode = false !== strpos( (string) $existing->post_content, $def['needle'] );
		$has_elementor = '' !== (string) get_post_meta( $existing->ID, '_elementor_data', true );

		if ( $has_shortcode || $has_elementor ) {
			if ( 'publish' !== $existing->post_status ) {
				wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
				WP_CLI::log( sprintf( '%-20s published (was %s)', $slug, $existing->post_status ) );
			} else {
				WP_CLI::log( sprintf( '%-20s already set up (id %d), left unchanged', $slug, $existing->ID ) );
			}
			continue;
		}

		wp_update_post(
			array(
				'ID'           => $existing->ID,
				'post_content' => $def['content'],
				'post_status'  => 'publish',
			)
		);
		WP_CLI::log( sprintf( '%-20s repaired (id %d)', $slug, $existing->ID ) );
		continue;
	}

	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $def['title'],
			'post_name'    => $slug,
			'post_content' => $def['content'],
		),
		true
	);

	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	WP_CLI::log( sprintf( '%-20s created (id %d)', $slug, $id ) );
}

flush_rewrite_rules( false );

WP_CLI::success( 'Test pages ready.' );
WP_CLI::log( 'Start here:  ' . home_url( '/leadership-coaching/' ) );
WP_CLI::log( 'Or direct:   ' . home_url( '/checkout/?offering=leadership-coaching' ) );
