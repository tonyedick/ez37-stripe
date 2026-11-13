<?php
/**
 * Resource registry — what a paid customer receives (developer-edited, decision D8).
 *
 * Offerings refer to resources by key (config/offerings.php → fulfilment.resources).
 * Replacing the brochure with real programme material only means changing the
 * file (and, if wanted, the label here). The payment engine does not change.
 *
 * Fields:
 * - type         'private_file' (the only type so far).
 * - label        Shown to the customer in the email.
 * - path         Relative to EZ37_PAYMENTS_PRIVATE_DIR. Never inside the web root.
 * - attach       Also attach the file to the customer email (decision D6).
 * - max_attach   Largest file (bytes) to attach; bigger files are sent as link only.
 * - access_days  Download link lifetime (decision D6: 7 days).
 *
 * @package EZ37\Payments
 */

defined( 'ABSPATH' ) || exit;

return array(
	'leadership-coaching-brochure' => array(
		'type'        => 'private_file',
		'label'       => 'Leadership Coaching programme brochure',
		'path'        => 'leadership-coaching/brochure-placeholder.pdf',
		'attach'      => true,
		'max_attach'  => 5242880,
		'access_days' => 7,
	),
);
