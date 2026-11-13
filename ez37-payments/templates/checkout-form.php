<?php
/**
 * Checkout form.
 *
 * Available variables (from CheckoutShortcode):
 *
 * @var \EZ37\Payments\Offerings\Offering $offering
 * @var string $action_url
 * @var string $action
 * @var string $nonce_field
 * @var string $honeypot_field
 * @var string $error_message
 * @var bool   $is_test_mode
 *
 * @package EZ37\Payments
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ez37-checkout" id="ez37-checkout">
	<?php if ( $is_test_mode ) : ?>
		<p class="ez37-checkout__test-notice" role="note">
			<?php esc_html_e( 'Test mode: no real payment will be taken.', 'ez37-payments' ); ?>
		</p>
	<?php endif; ?>

	<h3 class="ez37-checkout__title"><?php echo esc_html( $offering->name ); ?></h3>
	<p class="ez37-checkout__price"><?php echo esc_html( $offering->formatted_price() ); ?></p>

	<?php if ( '' !== $error_message ) : ?>
		<div class="ez37-checkout__error" role="alert"><?php echo esc_html( $error_message ); ?></div>
	<?php endif; ?>

	<?php /* Disabling the button on submit prevents accidental double submissions. */ ?>
	<form class="ez37-checkout__form" method="post" action="<?php echo esc_url( $action_url ); ?>" onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;}">
		<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
		<input type="hidden" name="offering" value="<?php echo esc_attr( $offering->key ); ?>">
		<?php wp_nonce_field( $action, $nonce_field ); ?>

		<p class="ez37-checkout__field">
			<label for="ez37-customer-name"><?php esc_html_e( 'Full name', 'ez37-payments' ); ?></label>
			<input type="text" id="ez37-customer-name" name="customer_name" required minlength="2" maxlength="100" autocomplete="name">
		</p>

		<p class="ez37-checkout__field">
			<label for="ez37-customer-email"><?php esc_html_e( 'Email address', 'ez37-payments' ); ?></label>
			<input type="email" id="ez37-customer-email" name="customer_email" required maxlength="254" autocomplete="email">
		</p>

		<?php /* Honeypot: hidden from people; bots tend to fill it in. */ ?>
		<p class="ez37-checkout__hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;">
			<label for="ez37-website"><?php esc_html_e( 'Leave this field empty', 'ez37-payments' ); ?></label>
			<input type="text" id="ez37-website" name="<?php echo esc_attr( $honeypot_field ); ?>" value="" tabindex="-1" autocomplete="off">
		</p>

		<p class="ez37-checkout__submit">
			<button type="submit"><?php esc_html_e( 'Continue to secure payment', 'ez37-payments' ); ?></button>
		</p>

		<p class="ez37-checkout__small">
			<?php esc_html_e( 'You will be taken to Stripe to pay securely. Your card details are never shared with us.', 'ez37-payments' ); ?>
		</p>
	</form>
	<script>
		/* Re-enable the button if the customer comes back with the browser's Back button. */
		window.addEventListener( 'pageshow', function () {
			document.querySelectorAll( '.ez37-checkout__form button[type=submit]' ).forEach( function ( b ) { b.disabled = false; } );
		} );
	</script>
</div>
