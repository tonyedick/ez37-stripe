<?php
/**
 * Internal "new sale" notification (plain text, decision D10).
 *
 * Plain-text email: values are printed without HTML escaping on purpose,
 * but line breaks are stripped so no value can inject extra lines.
 *
 * @var string $offering_name
 * @var string $amount
 * @var string $customer_name
 * @var string $customer_email
 * @var string $public_id
 * @var string $payment_intent
 * @var string $mode
 * @var string $paid_at
 *
 * @package EZ37\Payments
 */

defined( 'ABSPATH' ) || exit;

$ez37_payments_line = static fn ( string $label, string $value ): string => $label . ': ' . str_replace( array( "\r", "\n" ), ' ', $value ) . "\n";

// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Plain-text email body; newlines stripped above.
echo __( 'A new payment has been confirmed by Stripe.', 'ez37-payments' ) . "\n\n";
echo $ez37_payments_line( __( 'Programme', 'ez37-payments' ), $offering_name );
echo $ez37_payments_line( __( 'Amount', 'ez37-payments' ), $amount );
echo $ez37_payments_line( __( 'Customer', 'ez37-payments' ), $customer_name );
echo $ez37_payments_line( __( 'Email', 'ez37-payments' ), $customer_email );
echo $ez37_payments_line( __( 'Paid at', 'ez37-payments' ), $paid_at );
echo $ez37_payments_line( __( 'Reference', 'ez37-payments' ), $public_id );
echo $ez37_payments_line( __( 'Stripe payment', 'ez37-payments' ), $payment_intent );
echo $ez37_payments_line( __( 'Mode', 'ez37-payments' ), strtoupper( $mode ) );
echo "\n" . __( 'The customer has been sent their confirmation and access email.', 'ez37-payments' ) . "\n";
// phpcs:enable
