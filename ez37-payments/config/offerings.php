<?php
/**
 * Offering catalogue — edited by a developer (decision D8).
 *
 * Each key is the offering identifier used on the website, e.g.
 * /checkout/?offering=leadership-coaching. The browser only ever sends this
 * key; price, currency and Stripe Price come from here.
 *
 * Fields:
 * - name        Shown to the customer on the checkout page and in emails.
 * - active      false hides the offering and refuses checkout.
 * - currency    Lowercase ISO code. Must match the Stripe Price.
 * - amount      Price in the smallest unit (cents). Must match the Stripe Price.
 * - price_ids   Stripe Price ID per mode. Price IDs are not secret.
 *               An empty value disables the offering in that mode.
 * - tax         'none' only, until tax is confirmed (decision D7).
 * - fulfilment  What the customer receives (implemented in Phase 6).
 *
 * TEST PRICES ONLY. Real prices and live Price IDs are added before go-live.
 *
 * @package EZ37\Payments
 */

defined( 'ABSPATH' ) || exit;

return array(
	'leadership-coaching' => array(
		'name'              => 'Leadership Coaching',
		'active'            => true,
		'currency'          => 'usd',
		'amount'            => 100,
		// TEST: $1.00.
				'price_ids' => array(
					'test'             => '',
					// Paste the sandbox Price ID (price_...) here.
								'live' => '',
				),
		'tax'               => 'none',
		'fulfilment'        => array(
			'handler'   => 'resource_access',
			'resources' => array( 'leadership-coaching-brochure' ),
		),
	),
);
