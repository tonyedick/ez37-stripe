<?php
/**
 * Success page status ([ez37_payment_status]).
 *
 * Variables (from StatusService::lookup):
 *
 * @var string $state    paid | pending | review | failed | expired | not_found
 * @var string $offering Offering name.
 * @var string $amount   Formatted amount, e.g. "$1.00 USD".
 * @var string $email    Masked email, e.g. "j***@example.com".
 *
 * @package EZ37\Payments
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ez37-status ez37-status--<?php echo esc_attr( $state ); ?>" data-ez37-status="<?php echo esc_attr( $state ); ?>" role="status" aria-live="polite">
	<?php if ( 'paid' === $state ) : ?>
		<h2 class="ez37-status__title"><?php esc_html_e( 'Payment confirmed', 'ez37-payments' ); ?></h2>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: offering name, 2: amount */
					__( 'Thank you for purchasing %1$s (%2$s).', 'ez37-payments' ),
					$offering,
					$amount
				)
			);
			?>
		</p>
		<p>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: masked email address */
					__( 'A confirmation email will be sent to %s. Stripe also sends a separate payment receipt.', 'ez37-payments' ),
					$email
				)
			);
			?>
		</p>

	<?php elseif ( 'pending' === $state ) : ?>
		<h2 class="ez37-status__title"><?php esc_html_e( 'Confirming your payment…', 'ez37-payments' ); ?></h2>
		<p class="ez37-status__waiting"><?php esc_html_e( 'This usually takes a few seconds. Please keep this page open.', 'ez37-payments' ); ?></p>
		<p class="ez37-status__slow" hidden>
			<?php
			echo esc_html(
				sprintf(
					/* translators: %s: masked email address */
					__( 'We are still confirming your payment. You will receive an email at %s as soon as it is confirmed. There is no need to pay again.', 'ez37-payments' ),
					$email
				)
			);
			?>
		</p>

	<?php elseif ( 'review' === $state ) : ?>
		<h2 class="ez37-status__title"><?php esc_html_e( 'We are checking your payment', 'ez37-payments' ); ?></h2>
		<p><?php esc_html_e( 'Your payment needs a quick manual check. We will contact you by email. Please do not pay again.', 'ez37-payments' ); ?></p>

	<?php elseif ( 'failed' === $state || 'expired' === $state ) : ?>
		<h2 class="ez37-status__title"><?php esc_html_e( 'Payment not completed', 'ez37-payments' ); ?></h2>
		<p><?php esc_html_e( 'This payment was not completed and nothing was charged. You are welcome to try again.', 'ez37-payments' ); ?></p>

	<?php else : ?>
		<h2 class="ez37-status__title"><?php esc_html_e( 'We could not find this payment', 'ez37-payments' ); ?></h2>
		<p><?php esc_html_e( 'If you have just paid, please check your email for confirmation or contact us.', 'ez37-payments' ); ?></p>
	<?php endif; ?>
</div>
