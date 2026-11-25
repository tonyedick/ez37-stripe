<?php
/**
 * Payments screen in wp-admin.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Admin;

use EZ37\Payments\Config;
use EZ37\Payments\Email\EmailRepository;
use EZ37\Payments\Frontend\Template;
use EZ37\Payments\Fulfilment\FulfilmentService;
use EZ37\Payments\Plugin;
use EZ37\Payments\Resources\EntitlementRepository;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Transactions\TransactionRepository;
use EZ37\Payments\Webhooks\EventRepository;

/**
 * Payments menu: list, detail, and three administrator actions.
 *
 * - Retry fulfilment  (failed / failed_permanent)
 * - Resend email      (new link; the old link stops working)
 * - Revoke access     (after a manual refund in Stripe, decision D12)
 *
 * Viewing and acting both require the ez37_view_payments capability
 * (administrators). Every action is a POST with its own nonce, and is logged.
 * Refunds themselves are done in the Stripe Dashboard.
 */
final class PaymentsPage {

	public const SLUG   = 'ez37-payments';
	public const ACTION = 'ez37_payments_admin';

	public const ACTIONS = array( 'retry', 'resend', 'revoke' );

	private const PER_PAGE = 25;

	/**
	 * Constructor.
	 *
	 * @param Config                $config       Configuration (mode).
	 * @param TransactionRepository $transactions Transactions.
	 * @param EventRepository       $events       Event log.
	 * @param EmailRepository       $emails       Email log.
	 * @param EntitlementRepository $entitlements Entitlements.
	 * @param FulfilmentService     $fulfilment   Fulfilment actions.
	 * @param Logger                $logger       Logger.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly TransactionRepository $transactions,
		private readonly EventRepository $events,
		private readonly EmailRepository $emails,
		private readonly EntitlementRepository $entitlements,
		private readonly FulfilmentService $fulfilment,
		private readonly Logger $logger
	) {}

	/**
	 * Register the menu and the action handler.
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_action' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Admin styles, on this screen only.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue( string $hook_suffix ): void {
		if ( 'toplevel_page_' . self::SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'ez37-payments-admin', plugins_url( 'assets/admin.css', EZ37_PAYMENTS_FILE ), array(), EZ37_PAYMENTS_VERSION );
	}

	/**
	 * Add the top-level Payments menu.
	 */
	public function add_menu(): void {
		add_menu_page(
			__( 'Payments', 'ez37-payments' ),
			__( 'Payments', 'ez37-payments' ),
			Plugin::CAPABILITY,
			self::SLUG,
			array( $this, 'render' ),
			'dashicons-money-alt',
			58
		);
	}

	/**
	 * Render the list or a single transaction.
	 */
	public function render(): void {
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to view payments.', 'ez37-payments' ), '', array( 'response' => 403 ) );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation parameters.
		$id     = isset( $_GET['transaction'] ) ? absint( $_GET['transaction'] ) : 0;
		$notice = isset( $_GET['ez37_notice'] ) ? sanitize_key( wp_unslash( $_GET['ez37_notice'] ) ) : '';
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$base_vars = array(
			'notice'   => self::notice_message( $notice ),
			'page_url' => admin_url( 'admin.php?page=' . self::SLUG ),
			'mode'     => $this->config->mode(),
		);

		if ( $id > 0 ) {
			$transaction = $this->transactions->find_by_id( $id );
			if ( null === $transaction ) {
				wp_die( esc_html__( 'Transaction not found.', 'ez37-payments' ), '', array( 'response' => 404 ) );
			}

			$entitlements = $this->entitlements->for_transaction( $id );

			$html = Template::render(
				'admin/detail.php',
				$base_vars + array(
					'transaction'  => $transaction,
					'events'       => $this->events->for_transaction( $id ),
					'emails'       => $this->emails->for_transaction( $id ),
					'entitlements' => $entitlements,
					'actions'      => self::available_actions( $transaction, $entitlements ),
					'action_url'   => admin_url( 'admin-post.php' ),
					'action_name'  => self::ACTION,
				)
			);
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes its output.
			return;
		}//end if

		$result = $this->transactions->search( $search, $status, self::PER_PAGE, ( $paged - 1 ) * self::PER_PAGE );

		$html = Template::render(
			'admin/list.php',
			$base_vars + array(
				'rows'   => $result['rows'],
				'total'  => $result['total'],
				'paged'  => $paged,
				'pages'  => max( 1, (int) ceil( $result['total'] / self::PER_PAGE ) ),
				'search' => $search,
				'status' => $status,
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Template escapes its output.
	}

	/**
	 * Handle an administrator action (admin-post.php).
	 */
	public function handle_action(): void {
		if ( ! current_user_can( Plugin::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to change payments.', 'ez37-payments' ), '', array( 'response' => 403 ) );
		}

		$id   = isset( $_POST['transaction'] ) ? absint( $_POST['transaction'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below with an action- and ID-specific nonce.
		$todo = isset( $_POST['ez37_do'] ) ? sanitize_key( wp_unslash( $_POST['ez37_do'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified below.

		if ( 0 === $id || ! in_array( $todo, self::ACTIONS, true ) ) {
			wp_die( esc_html__( 'Invalid request.', 'ez37-payments' ), '', array( 'response' => 400 ) );
		}

		check_admin_referer( self::nonce_action( $todo, $id ) );

		$transaction  = $this->transactions->find_by_id( $id );
		$entitlements = $this->entitlements->for_transaction( $id );

		if ( null === $transaction || ! in_array( $todo, self::available_actions( $transaction, $entitlements ), true ) ) {
			$this->redirect( $id, 'not_allowed' );
		}

		$notice = match ( $todo ) {
			'retry'  => $this->fulfilment->retry( $id ) ? 'retried' : 'retry_failed',
			'resend' => $this->fulfilment->resend( $id ) ? 'resent' : 'resend_failed',
			'revoke' => $this->entitlements->revoke_for_transaction( $id ) > 0 ? 'revoked' : 'nothing_revoked',
		};

		$this->logger->info(
			'Administrator action on payment.',
			array(
				'action'      => $todo,
				'transaction' => (string) $transaction['public_id'],
				'user'        => get_current_user_id(),
				'result'      => $notice,
			)
		);

		$this->redirect( $id, $notice );
	}

	/**
	 * Which actions make sense for a transaction right now.
	 *
	 * @param array<string, mixed>       $transaction  Transaction row.
	 * @param list<array<string, mixed>> $entitlements Its entitlements.
	 * @return list<string>
	 */
	public static function available_actions( array $transaction, array $entitlements ): array {
		if ( 'paid' !== $transaction['payment_status'] || ! empty( $transaction['review_required'] ) ) {
			return array();
		}

		$active  = array_filter( $entitlements, static fn ( array $e ): bool => EntitlementRepository::ACTIVE === $e['status'] );
		$revoked = count( $active ) < count( $entitlements );
		$actions = array();

		if ( in_array( $transaction['fulfilment_status'], array( 'failed', 'failed_permanent' ), true ) && ! $revoked ) {
			$actions[] = 'retry';
		}
		if ( 'completed' === $transaction['fulfilment_status'] && ! $revoked ) {
			$actions[] = 'resend';
		}
		if ( array() !== $active ) {
			$actions[] = 'revoke';
		}

		return $actions;
	}

	/**
	 * Nonce action string for one action on one transaction.
	 *
	 * @param string $todo Action.
	 * @param int    $id   Transaction ID.
	 */
	public static function nonce_action( string $todo, int $id ): string {
		return self::ACTION . '_' . $todo . '_' . $id;
	}

	/**
	 * Message for a notice code.
	 *
	 * @param string $code Notice code.
	 */
	public static function notice_message( string $code ): string {
		return match ( $code ) {
			'retried'         => __( 'Fulfilment completed: the customer email was sent.', 'ez37-payments' ),
			'retry_failed'    => __( 'Fulfilment failed again. Check the error below and the server log.', 'ez37-payments' ),
			'resent'          => __( 'The customer email was sent again with a new download link. The previous link no longer works.', 'ez37-payments' ),
			'resend_failed'   => __( 'The email could not be resent. Check the error below and the server log.', 'ez37-payments' ),
			'revoked'         => __( 'Access revoked. The customer’s download link no longer works.', 'ez37-payments' ),
			'nothing_revoked' => __( 'There was no active access to revoke.', 'ez37-payments' ),
			'not_allowed'     => __( 'That action is not available for this payment.', 'ez37-payments' ),
			default           => '',
		};
	}

	/**
	 * Short customer-facing reference (the one shown in emails).
	 *
	 * @param string $public_id Full public ID.
	 */
	public static function short_reference( string $public_id ): string {
		return strtoupper( substr( $public_id, 0, 10 ) );
	}

	/**
	 * A stored UTC datetime in the site timezone, or a dash.
	 *
	 * @param mixed $utc "Y-m-d H:i:s" in UTC, or null.
	 */
	public static function local_time( mixed $utc ): string {
		$timestamp = is_string( $utc ) && '' !== $utc ? strtotime( $utc . ' UTC' ) : false;
		if ( false === $timestamp ) {
			return '—';
		}

		return (string) wp_date( (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) . ' T', $timestamp );
	}

	/**
	 * Small coloured label for a status value (escaped HTML).
	 *
	 * @param string $status Status value.
	 */
	public static function status_badge( string $status ): string {
		$labels = array(
			'pending'          => __( 'Pending', 'ez37-payments' ),
			'processing'       => __( 'Processing', 'ez37-payments' ),
			'paid'             => __( 'Paid', 'ez37-payments' ),
			'failed'           => __( 'Failed', 'ez37-payments' ),
			'expired'          => __( 'Expired', 'ez37-payments' ),
			'review'           => __( 'Needs review', 'ez37-payments' ),
			'not_started'      => __( 'Not started', 'ez37-payments' ),
			'in_progress'      => __( 'In progress', 'ez37-payments' ),
			'invoice_requested' => __( 'Invoice requested', 'ez37-payments' ),
			'registered'       => __( 'Registered (free)', 'ez37-payments' ),
			'completed'        => __( 'Delivered', 'ez37-payments' ),
			'failed_permanent' => __( 'Failed — action needed', 'ez37-payments' ),
			'active'           => __( 'Active', 'ez37-payments' ),
			'revoked'          => __( 'Revoked', 'ez37-payments' ),
			'sent'             => __( 'Sent', 'ez37-payments' ),
			'sending'          => __( 'Sending', 'ez37-payments' ),
		);

		$tone = match ( $status ) {
			'paid', 'completed', 'active', 'sent'                => 'good',
			'failed', 'failed_permanent', 'review', 'revoked'    => 'bad',
			'pending', 'processing', 'in_progress', 'sending', 'invoice_requested' => 'wait',
			'registered'                                         => 'good',
			default                                              => 'neutral',
		};

		return sprintf(
			'<span class="ez37-badge ez37-badge--%s">%s</span>',
			esc_attr( $tone ),
			esc_html( $labels[ $status ] ?? $status )
		);
	}

	/**
	 * Link to the payment in the Stripe Dashboard (where refunds are made).
	 *
	 * @param string $payment_intent_id PaymentIntent ID.
	 * @param string $mode              test | live.
	 */
	public static function stripe_dashboard_url( string $payment_intent_id, string $mode ): string {
		return 'https://dashboard.stripe.com/' . ( 'live' === $mode ? '' : 'test/' ) . 'payments/' . rawurlencode( $payment_intent_id );
	}

	/**
	 * Back to the transaction with a notice.
	 *
	 * @param int    $id     Transaction ID.
	 * @param string $notice Notice code.
	 */
	private function redirect( int $id, string $notice ): never {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => self::SLUG,
					'transaction' => $id,
					'ez37_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
