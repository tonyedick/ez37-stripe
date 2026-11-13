<?php
/**
 * Payments screen (wp-admin): transaction list.
 *
 * @var string                     $notice   Notice text, or empty.
 * @var string                     $page_url Base URL of this screen.
 * @var string                     $mode     test | live.
 * @var list<array<string, mixed>> $rows     Transactions on this page.
 * @var int                        $total    Matching transactions.
 * @var int                        $paged    Current page.
 * @var int                        $pages    Number of pages.
 * @var string                     $search   Search term.
 * @var string                     $status   Status filter.
 *
 * @package EZ37\Payments
 */

use EZ37\Payments\Admin\PaymentsPage;
use EZ37\Payments\Offerings\Offering;

defined( 'ABSPATH' ) || exit;

$ez37_payments_filters = array(
	''                  => __( 'All', 'ez37-payments' ),
	'paid'              => __( 'Paid', 'ez37-payments' ),
	'pending'           => __( 'Pending', 'ez37-payments' ),
	'failed'            => __( 'Failed', 'ez37-payments' ),
	'expired'           => __( 'Expired', 'ez37-payments' ),
	'fulfilment_failed' => __( 'Delivery failed', 'ez37-payments' ),
	'review'            => __( 'Needs review', 'ez37-payments' ),
);
?>
<div class="wrap ez37-admin">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Payments', 'ez37-payments' ); ?></h1>
	<span class="ez37-admin__mode ez37-admin__mode--<?php echo esc_attr( $mode ); ?>"><?php echo esc_html( 'live' === $mode ? __( 'Live mode', 'ez37-payments' ) : __( 'Test mode', 'ez37-payments' ) ); ?></span>
	<hr class="wp-header-end">

	<?php if ( '' !== $notice ) : ?>
		<div class="notice notice-info"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<ul class="subsubsub">
		<?php
		$ez37_payments_links = array();
		foreach ( $ez37_payments_filters as $ez37_payments_key => $ez37_payments_label ) {
			$ez37_payments_links[] = sprintf(
				'<li><a href="%s"%s>%s</a></li>',
				esc_url( '' === $ez37_payments_key ? $page_url : add_query_arg( 'status', $ez37_payments_key, $page_url ) ),
				$status === $ez37_payments_key ? ' class="current" aria-current="page"' : '',
				esc_html( $ez37_payments_label )
			);
		}
		echo implode( ' | ', $ez37_payments_links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part escaped above.
		?>
	</ul>

	<form method="get" class="search-form">
		<input type="hidden" name="page" value="<?php echo esc_attr( PaymentsPage::SLUG ); ?>">
		<?php if ( '' !== $status ) : ?>
			<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>">
		<?php endif; ?>
		<p class="search-box">
			<label class="screen-reader-text" for="ez37-payments-search"><?php esc_html_e( 'Search payments', 'ez37-payments' ); ?></label>
			<input type="search" id="ez37-payments-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Email, name or reference', 'ez37-payments' ); ?>">
			<button type="submit" class="button"><?php esc_html_e( 'Search', 'ez37-payments' ); ?></button>
		</p>
	</form>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Reference', 'ez37-payments' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Date', 'ez37-payments' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Customer', 'ez37-payments' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Programme', 'ez37-payments' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Amount', 'ez37-payments' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Payment', 'ez37-payments' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Delivery', 'ez37-payments' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php if ( array() === $rows ) : ?>
			<tr><td colspan="7"><?php esc_html_e( 'No payments found.', 'ez37-payments' ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( $rows as $ez37_payments_row ) : ?>
			<tr>
				<td>
					<a href="<?php echo esc_url( add_query_arg( 'transaction', (int) $ez37_payments_row['id'], $page_url ) ); ?>">
						<strong><?php echo esc_html( PaymentsPage::short_reference( (string) $ez37_payments_row['public_id'] ) ); ?></strong>
					</a>
					<?php if ( 'test' === $ez37_payments_row['stripe_mode'] ) : ?>
						<span class="ez37-admin__tag"><?php esc_html_e( 'test', 'ez37-payments' ); ?></span>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( PaymentsPage::local_time( (string) $ez37_payments_row['created_at'] ) ); ?></td>
				<td>
					<?php echo esc_html( (string) $ez37_payments_row['customer_name'] ); ?><br>
					<span class="description"><?php echo esc_html( (string) $ez37_payments_row['customer_email'] ); ?></span>
				</td>
				<td><?php echo esc_html( (string) $ez37_payments_row['offering_name'] ); ?></td>
				<td><?php echo esc_html( Offering::format_amount( (int) $ez37_payments_row['amount'], (string) $ez37_payments_row['currency'] ) ); ?></td>
				<td>
					<?php echo PaymentsPage::status_badge( (string) $ez37_payments_row['payment_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_badge(). ?>
					<?php if ( ! empty( $ez37_payments_row['review_required'] ) ) : ?>
						<?php echo PaymentsPage::status_badge( 'review' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_badge(). ?>
					<?php endif; ?>
				</td>
				<td><?php echo PaymentsPage::status_badge( (string) $ez37_payments_row['fulfilment_status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in status_badge(). ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<div class="tablenav bottom">
		<div class="tablenav-pages">
			<span class="displaying-num">
				<?php
				/* translators: %s: number of payments */
				echo esc_html( sprintf( _n( '%s payment', '%s payments', $total, 'ez37-payments' ), number_format_i18n( $total ) ) );
				?>
			</span>
			<?php
			echo wp_kses_post(
				(string) paginate_links(
					array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
					)
				)
			);
			?>
		</div>
	</div>
</div>
