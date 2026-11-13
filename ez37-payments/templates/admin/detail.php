<?php
/**
 * Payments screen (wp-admin): one transaction.
 *
 * @var string                     $notice       Notice text, or empty.
 * @var string                     $page_url     Base URL of this screen.
 * @var string                     $mode         test | live.
 * @var array<string, mixed>       $transaction  Transaction row.
 * @var list<array<string, mixed>> $events       Stripe events for it.
 * @var list<array<string, mixed>> $emails       Email log rows.
 * @var list<array<string, mixed>> $entitlements Entitlements (no token hashes).
 * @var list<string>               $actions      Available actions.
 * @var string                     $action_url   admin-post.php URL.
 * @var string                     $action_name  admin-post action.
 *
 * @package EZ37\Payments
 */

use EZ37\Payments\Admin\PaymentsPage;
use EZ37\Payments\Offerings\Offering;

defined( 'ABSPATH' ) || exit;

$ez37_payments_t       = $transaction;
$ez37_payments_id      = (int) $ez37_payments_t['id'];
$ez37_payments_pi      = (string) ( $ez37_payments_t['stripe_payment_intent_id'] ?? '' );
$ez37_payments_buttons = array(
	'retry'  => array(
		'label'   => __( 'Retry delivery now', 'ez37-payments' ),
		'help'    => __( 'Runs fulfilment again: grants access and sends the customer email.', 'ez37-payments' ),
		'confirm' => '',
		'class'   => 'button button-primary',
	),
	'resend' => array(
		'label'   => __( 'Resend email with a new link', 'ez37-payments' ),
		'help'    => __( 'Sends the customer email again with a new download link valid for the full access period. The previous link stops working.', 'ez37-payments' ),
		'confirm' => __( 'Send the customer a new email? Their previous download link will stop working.', 'ez37-payments' ),
		'class'   => 'button',
	),
	'revoke' => array(
		'label'   => __( 'Revoke access', 'ez37-payments' ),
		'help'    => __( 'Use after refunding the payment in the Stripe Dashboard. The download link stops working immediately. This cannot be undone here.', 'ez37-payments' ),
		'confirm' => __( 'Revoke this customer’s access? Their download link will stop working. This cannot be undone.', 'ez37-payments' ),
		'class'   => 'button button-link-delete',
	),
);
?>
<div class="wrap ez37-admin">
	<h1 class="wp-heading-inline">
		<?php
		/* translators: %s: short payment reference */
		echo esc_html( sprintf( __( 'Payment %s', 'ez37-payments' ), PaymentsPage::short_reference( (string) $ez37_payments_t['public_id'] ) ) );
		?>
	</h1>
	<a class="page-title-action" href="<?php echo esc_url( $page_url ); ?>"><?php esc_html_e( 'Back to payments', 'ez37-payments' ); ?></a>
	<hr class="wp-header-end">

	<?php if ( '' !== $notice ) : ?>
		<div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<?php if ( ! empty( $ez37_payments_t['review_required'] ) ) : ?>
		<div class="notice notice-warning">
			<p><?php esc_html_e( 'This payment is flagged for review (the Stripe data did not match what we expected). Nothing was delivered automatically. Check it in the Stripe Dashboard and the server log.', 'ez37-payments' ); ?></p>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Summary', 'ez37-payments' ); ?></h2>
	<table class="widefat striped ez37-admin__summary">
		<tbody>
			<tr><th scope="row"><?php esc_html_e( 'Full reference', 'ez37-payments' ); ?></th><td><code><?php echo esc_html( (string) $ez37_payments_t['public_id'] ); ?></code></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Programme', 'ez37-payments' ); ?></th><td><?php echo esc_html( (string) $ez37_payments_t['offering_name'] ); ?> <span class="description">(<?php echo esc_html( (string) $ez37_payments_t['offering_key'] ); ?>)</span></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Customer', 'ez37-payments' ); ?></th><td><?php echo esc_html( (string) $ez37_payments_t['customer_name'] ); ?> — <a href="mailto:<?php echo esc_attr( (string) $ez37_payments_t['customer_email'] ); ?>"><?php echo esc_html( (string) $ez37_payments_t['customer_email'] ); ?></a></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Price', 'ez37-payments' ); ?></th><td><?php echo esc_html( Offering::format_amount( (int) $ez37_payments_t['amount'], (string) $ez37_payments_t['currency'] ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Amount paid (from Stripe)', 'ez37-payments' ); ?></th><td><?php echo esc_html( null === $ez37_payments_t['amount_paid'] ? '—' : Offering::format_amount( (int) $ez37_payments_t['amount_paid'], (string) $ez37_payments_t['currency'] ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Payment', 'ez37-payments' ); ?></th><td><?php echo PaymentsPage::status_badge( (string) $ez37_payments_t['payment_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_badge(). ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Delivery', 'ez37-payments' ); ?></th><td><?php echo PaymentsPage::status_badge( (string) $ez37_payments_t['fulfilment_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_badge(). ?> <span class="description"><?php /* translators: %d: number of attempts */ echo esc_html( sprintf( __( '(%d attempts)', 'ez37-payments' ), (int) $ez37_payments_t['fulfilment_attempts'] ) ); ?></span></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Created', 'ez37-payments' ); ?></th><td><?php echo esc_html( PaymentsPage::local_time( (string) $ez37_payments_t['created_at'] ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Paid', 'ez37-payments' ); ?></th><td><?php echo esc_html( PaymentsPage::local_time( $ez37_payments_t['paid_at'] ) ); ?></td></tr>
			<tr><th scope="row"><?php esc_html_e( 'Delivered', 'ez37-payments' ); ?></th><td><?php echo esc_html( PaymentsPage::local_time( $ez37_payments_t['fulfilled_at'] ) ); ?></td></tr>
			<?php if ( ! empty( $ez37_payments_t['failure_message'] ) ) : ?>
				<tr><th scope="row"><?php esc_html_e( 'Last error', 'ez37-payments' ); ?></th><td><?php echo esc_html( (string) $ez37_payments_t['failure_message'] ); ?></td></tr>
			<?php endif; ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Stripe', 'ez37-payments' ); ?></th>
				<td>
					<?php echo esc_html( 'live' === $ez37_payments_t['stripe_mode'] ? __( 'Live', 'ez37-payments' ) : __( 'Test', 'ez37-payments' ) ); ?> ·
					<?php if ( '' !== $ez37_payments_pi ) : ?>
						<a href="<?php echo esc_url( PaymentsPage::stripe_dashboard_url( $ez37_payments_pi, (string) $ez37_payments_t['stripe_mode'] ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $ez37_payments_pi ); ?></a>
					<?php else : ?>
						<?php esc_html_e( 'No PaymentIntent yet', 'ez37-payments' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Actions', 'ez37-payments' ); ?></h2>
	<?php if ( array() === $actions ) : ?>
		<p class="description"><?php esc_html_e( 'No actions are available for this payment in its current state.', 'ez37-payments' ); ?></p>
	<?php endif; ?>
	<?php foreach ( $actions as $ez37_payments_action ) : ?>
		<?php $ez37_payments_button = $ez37_payments_buttons[ $ez37_payments_action ]; ?>
		<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="ez37-admin__action"<?php echo '' === $ez37_payments_button['confirm'] ? '' : ' onsubmit="return window.confirm(' . esc_attr( (string) wp_json_encode( $ez37_payments_button['confirm'] ) ) . ');"'; ?>>
			<input type="hidden" name="action" value="<?php echo esc_attr( $action_name ); ?>">
			<input type="hidden" name="ez37_do" value="<?php echo esc_attr( $ez37_payments_action ); ?>">
			<input type="hidden" name="transaction" value="<?php echo esc_attr( (string) $ez37_payments_id ); ?>">
			<?php wp_nonce_field( PaymentsPage::nonce_action( $ez37_payments_action, $ez37_payments_id ) ); ?>
			<button type="submit" class="<?php echo esc_attr( $ez37_payments_button['class'] ); ?>"><?php echo esc_html( $ez37_payments_button['label'] ); ?></button>
			<span class="description"><?php echo esc_html( $ez37_payments_button['help'] ); ?></span>
		</form>
	<?php endforeach; ?>
	<p class="description"><?php esc_html_e( 'Refunds are made in the Stripe Dashboard. After refunding, revoke access here.', 'ez37-payments' ); ?></p>

	<h2><?php esc_html_e( 'Access', 'ez37-payments' ); ?></h2>
	<table class="widefat striped">
		<thead><tr>
			<th scope="col"><?php esc_html_e( 'Resource', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Link expires', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Downloads', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Last download', 'ez37-payments' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if ( array() === $entitlements ) : ?>
			<tr><td colspan="5"><?php esc_html_e( 'No access granted yet.', 'ez37-payments' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $entitlements as $ez37_payments_e ) : ?>
			<tr>
				<td><code><?php echo esc_html( (string) $ez37_payments_e['resource_key'] ); ?></code></td>
				<td><?php echo PaymentsPage::status_badge( (string) $ez37_payments_e['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_badge(). ?></td>
				<td><?php echo esc_html( PaymentsPage::local_time( $ez37_payments_e['expires_at'] ) ); ?></td>
				<td><?php echo esc_html( (string) (int) $ez37_payments_e['access_count'] ); ?></td>
				<td><?php echo esc_html( PaymentsPage::local_time( $ez37_payments_e['last_accessed_at'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Emails', 'ez37-payments' ); ?></h2>
	<table class="widefat striped">
		<thead><tr>
			<th scope="col"><?php esc_html_e( 'Email', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'To', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Status', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Attempts', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Sent', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Last error', 'ez37-payments' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if ( array() === $emails ) : ?>
			<tr><td colspan="6"><?php esc_html_e( 'No emails yet.', 'ez37-payments' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $emails as $ez37_payments_m ) : ?>
			<tr>
				<td><?php echo esc_html( 'customer_fulfilment' === $ez37_payments_m['email_type'] ? __( 'Customer confirmation + access', 'ez37-payments' ) : __( 'Internal sale notice', 'ez37-payments' ) ); ?></td>
				<td><?php echo esc_html( (string) $ez37_payments_m['recipient'] ); ?></td>
				<td><?php echo PaymentsPage::status_badge( (string) $ez37_payments_m['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_badge(). ?></td>
				<td><?php echo esc_html( (string) (int) $ez37_payments_m['attempts'] ); ?></td>
				<td><?php echo esc_html( PaymentsPage::local_time( $ez37_payments_m['sent_at'] ) ); ?></td>
				<td><?php echo esc_html( (string) ( $ez37_payments_m['last_error'] ?? '' ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h2><?php esc_html_e( 'Stripe events', 'ez37-payments' ); ?></h2>
	<table class="widefat striped">
		<thead><tr>
			<th scope="col"><?php esc_html_e( 'Event', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Received', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Result', 'ez37-payments' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Event ID', 'ez37-payments' ); ?></th>
		</tr></thead>
		<tbody>
		<?php if ( array() === $events ) : ?>
			<tr><td colspan="4"><?php esc_html_e( 'No Stripe events recorded (the payment may have been confirmed by reconciliation).', 'ez37-payments' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $events as $ez37_payments_ev ) : ?>
			<tr>
				<td><code><?php echo esc_html( (string) $ez37_payments_ev['event_type'] ); ?></code></td>
				<td><?php echo esc_html( PaymentsPage::local_time( (string) $ez37_payments_ev['received_at'] ) ); ?></td>
				<td><?php echo esc_html( (string) $ez37_payments_ev['processing_status'] ); ?><?php echo empty( $ez37_payments_ev['error_message'] ) ? '' : ' — ' . esc_html( (string) $ez37_payments_ev['error_message'] ); ?></td>
				<td><code><?php echo esc_html( (string) $ez37_payments_ev['stripe_event_id'] ); ?></code></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
