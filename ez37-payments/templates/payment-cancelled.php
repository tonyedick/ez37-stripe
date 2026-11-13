<?php
/**
 * Cancel page ([ez37_payment_cancelled]).
 *
 * @var string $offering_name Offering name, or empty when unknown.
 * @var string $retry_url     Checkout URL for the same offering, or empty.
 *
 * @package EZ37\Payments
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="ez37-cancelled">
	<h2 class="ez37-cancelled__title"><?php esc_html_e( 'Payment cancelled', 'ez37-payments' ); ?></h2>
	<p><?php esc_html_e( 'You left the payment page before paying. Nothing was charged.', 'ez37-payments' ); ?></p>

	<?php if ( '' !== $retry_url ) : ?>
		<p>
			<a class="ez37-cancelled__retry" href="<?php echo esc_url( $retry_url ); ?>">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: offering name */
						__( 'Try again: %s', 'ez37-payments' ),
						$offering_name
					)
				);
				?>
			</a>
		</p>
	<?php endif; ?>
</div>
