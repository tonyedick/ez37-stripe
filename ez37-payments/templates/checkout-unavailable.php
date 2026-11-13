<?php
/**
 * Shown when an offering cannot be bought (unknown, inactive, no price, or checkout disabled).
 *
 * @var string $message
 *
 * @package EZ37\Payments
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ez37-checkout ez37-checkout--unavailable" id="ez37-checkout">
	<p><?php echo esc_html( $message ); ?></p>
</div>
