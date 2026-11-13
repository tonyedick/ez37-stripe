<?php
/**
 * Customer email: payment confirmation + access (decision D10).
 *
 * @var string                                                   $customer_name
 * @var string                                                   $offering_name
 * @var string                                                   $amount
 * @var string                                                   $reference
 * @var list<array{label: string, url: string, expires: string}> $grants
 * @var bool                                                     $attached
 * @var string                                                   $site_name
 *
 * @package EZ37\Payments
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f5f5;font-family:Arial,Helvetica,sans-serif;color:#222;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:24px 0;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:6px;padding:32px;">
<tr><td style="font-size:16px;line-height:1.5;">

<p style="margin:0 0 16px;">
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: customer name */
			__( 'Hello %s,', 'ez37-payments' ),
			$customer_name
		)
	);
	?>
</p>

<p style="margin:0 0 16px;font-size:20px;font-weight:bold;"><?php esc_html_e( 'Your payment is confirmed.', 'ez37-payments' ); ?></p>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 24px;font-size:15px;">
	<tr><td style="padding:2px 16px 2px 0;color:#666;"><?php esc_html_e( 'Programme', 'ez37-payments' ); ?></td><td style="padding:2px 0;"><?php echo esc_html( $offering_name ); ?></td></tr>
	<tr><td style="padding:2px 16px 2px 0;color:#666;"><?php esc_html_e( 'Amount paid', 'ez37-payments' ); ?></td><td style="padding:2px 0;"><?php echo esc_html( $amount ); ?></td></tr>
	<tr><td style="padding:2px 16px 2px 0;color:#666;"><?php esc_html_e( 'Reference', 'ez37-payments' ); ?></td><td style="padding:2px 0;"><?php echo esc_html( $reference ); ?></td></tr>
</table>

<?php foreach ( $grants as $ez37_payments_grant ) : ?>
<p style="margin:0 0 8px;font-weight:bold;"><?php echo esc_html( $ez37_payments_grant['label'] ); ?></p>
<p style="margin:0 0 8px;">
	<a href="<?php echo esc_url( $ez37_payments_grant['url'] ); ?>" style="display:inline-block;background:#1a4fa3;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:4px;font-weight:bold;"><?php esc_html_e( 'Download', 'ez37-payments' ); ?></a>
</p>
<p style="margin:0 0 20px;font-size:13px;color:#666;">
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: date and time */
			__( 'This private link works until %s. Please do not share it.', 'ez37-payments' ),
			$ez37_payments_grant['expires']
		)
	);
	?>
</p>
<?php endforeach; ?>

<?php if ( $attached ) : ?>
<p style="margin:0 0 16px;"><?php esc_html_e( 'For your convenience, the file is also attached to this email.', 'ez37-payments' ); ?></p>
<?php endif; ?>

<p style="margin:0 0 16px;"><?php esc_html_e( 'Stripe, our payment provider, sends your payment receipt in a separate email.', 'ez37-payments' ); ?></p>

<p style="margin:24px 0 0;">
	<?php esc_html_e( 'Thank you,', 'ez37-payments' ); ?><br>
	<?php echo esc_html( $site_name ); ?>
</p>

</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
