<?php
/**
 * Backstop for missed webhooks.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Transactions;

use EZ37\Payments\Config;
use EZ37\Payments\Stripe\GatewayException;
use EZ37\Payments\Stripe\StripeGateway;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Webhooks\EventProcessor;

/**
 * Periodically asks Stripe about transactions that are still pending after
 * a while, in case a webhook was never delivered (endpoint down, firewall,
 * listener not running).
 *
 * It never decides by itself: each session is fetched from the Stripe API
 * and passed through EventProcessor::reconcile_session(), which applies
 * exactly the same checks and forward-only transitions as a webhook.
 *
 * Runs hourly via WP-Cron (hook ez37_payments_reconcile). WP-Cron needs site
 * traffic or a real server cron; see docs/deployment.md.
 */
class Reconciler {

	public const CRON_HOOK = 'ez37_payments_reconcile';

	/**
	 * Only look at transactions at least this old (gives the webhook time to arrive).
	 */
	public const MIN_AGE_MINUTES = 15;

	/**
	 * Stop looking after this long (Checkout Sessions expire after at most 24 h).
	 */
	public const MAX_AGE_HOURS = 48;

	/**
	 * Transactions checked per run (each is one Stripe API call).
	 */
	public const BATCH_SIZE = 20;

	private const LOCK = 'ez37_payments_reconcile_lock';

	/**
	 * Constructor.
	 *
	 * @param Config                $config       Configuration.
	 * @param TransactionRepository $transactions Transactions.
	 * @param StripeGateway         $gateway      Stripe API.
	 * @param EventProcessor        $processor    Applies session state.
	 * @param Logger                $logger       Logger.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly TransactionRepository $transactions,
		private readonly StripeGateway $gateway,
		private readonly EventProcessor $processor,
		private readonly Logger $logger
	) {}

	/**
	 * Register the cron callback and make sure the event is scheduled.
	 */
	public function register(): void {
		add_action(
			self::CRON_HOOK,
			function (): void {
				$this->run();
			}
		);

		if ( false === wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Check a batch of unconfirmed transactions.
	 *
	 * @return array<string, int> Count per outcome (for logs and tests).
	 */
	public function run(): array {
		if ( null === $this->config->stripe_secret_key() ) {
			return array();
		}

		if ( false !== get_transient( self::LOCK ) ) {
			return array();
		}
		set_transient( self::LOCK, 1, 600 );

		$counts = array();

		try {
			$rows = $this->transactions->find_unconfirmed( $this->config->mode(), self::MIN_AGE_MINUTES, self::MAX_AGE_HOURS, self::BATCH_SIZE );

			foreach ( $rows as $row ) {
				$outcome            = $this->reconcile( $row );
				$counts[ $outcome ] = ( $counts[ $outcome ] ?? 0 ) + 1;
			}
		} finally {
			delete_transient( self::LOCK );
		}

		if ( array() !== $counts ) {
			$this->logger->info( 'Reconciliation run finished.', $counts );
		}

		return $counts;
	}

	/**
	 * Reconcile one transaction.
	 *
	 * @param array<string, mixed> $row Transaction row.
	 */
	private function reconcile( array $row ): string {
		$session_id = (string) $row['stripe_checkout_session_id'];

		try {
			$session = $this->gateway->retrieve_checkout_session( $session_id );
		} catch ( GatewayException $e ) {
			$this->logger->warning(
				'Reconciliation could not fetch Checkout Session.',
				array(
					'transaction' => (string) $row['public_id'],
					'exception'   => $e,
				)
			);
			return 'error';
		}

		try {
			return $this->processor->reconcile_session( $session );
		} catch ( \Throwable $e ) {
			$this->logger->error(
				'Reconciliation failed to apply session state.',
				array(
					'transaction' => (string) $row['public_id'],
					'exception'   => $e,
				)
			);
			return 'error';
		}
	}
}
