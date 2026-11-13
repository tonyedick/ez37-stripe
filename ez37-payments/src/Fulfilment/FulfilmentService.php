<?php
/**
 * Delivers what a customer paid for.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Fulfilment;

use EZ37\Payments\Config;
use EZ37\Payments\Email\Mailer;
use EZ37\Payments\Offerings\OfferingCatalogue;
use EZ37\Payments\Resources\AccessController;
use EZ37\Payments\Resources\EntitlementRepository;
use EZ37\Payments\Resources\ResourceCatalogue;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Transactions\TransactionRepository;

/**
 * PAYMENT → ENTITLEMENT → RESOURCE, independent of how the payment arrived.
 *
 * Triggered by the ez37_payments_transaction_paid action (scheduled as a
 * background job so the webhook answers quickly) and by an hourly sweeper
 * that retries failures and recovers crashed jobs.
 *
 * Guarantees:
 * - Only one job can work on a transaction at a time (atomic claim + lock).
 * - The customer email is sent at most once (email log); if it was already
 *   sent, the job completes without issuing new links.
 * - A failure (missing file, email error) marks fulfilment failed and is
 *   retried; the payment stays paid. After MAX_ATTEMPTS it becomes
 *   failed_permanent for an administrator.
 */
class FulfilmentService {

	public const JOB_HOOK   = 'ez37_payments_fulfil';
	public const SWEEP_HOOK = 'ez37_payments_fulfilment_sweep';

	public const MAX_ATTEMPTS = 5;
	/**
	 * How long a claimed job is protected from takeover. Longer than PHPMailer's
	 * default 300-second SMTP timeout, so a slow but working job is not
	 * mistaken for a crashed one.
	 */
	public const LOCK_SECONDS = 900;

	/**
	 * Constructor.
	 *
	 * @param Config                $config       Configuration.
	 * @param TransactionRepository $transactions Transactions.
	 * @param OfferingCatalogue     $offerings    Offerings.
	 * @param ResourceCatalogue     $resources    Resources.
	 * @param EntitlementRepository $entitlements Entitlements.
	 * @param Mailer                $mailer       Emails.
	 * @param Logger                $logger       Logger.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly TransactionRepository $transactions,
		private readonly OfferingCatalogue $offerings,
		private readonly ResourceCatalogue $resources,
		private readonly EntitlementRepository $entitlements,
		private readonly Mailer $mailer,
		private readonly Logger $logger
	) {}

	/**
	 * Hook into WordPress: queue a job when a payment is confirmed, run jobs, sweep hourly.
	 */
	public function register(): void {
		add_action( 'ez37_payments_transaction_paid', array( $this, 'queue' ) );
		add_action(
			self::JOB_HOOK,
			function ( $id ): void {
				$this->fulfil( (int) $id );
			}
		);
		add_action(
			self::SWEEP_HOOK,
			function (): void {
				$this->sweep();
			}
		);

		if ( false === wp_next_scheduled( self::SWEEP_HOOK ) ) {
			wp_schedule_event( time() + 600, 'hourly', self::SWEEP_HOOK );
		}
	}

	/**
	 * Schedule an immediate background job for a newly paid transaction.
	 *
	 * @param int $id Transaction ID.
	 */
	public function queue( int $id ): void {
		wp_schedule_single_event( time(), self::JOB_HOOK, array( (int) $id ) );
	}

	/**
	 * Retry due fulfilments (new, failed, or crashed).
	 *
	 * @return int Number of transactions completed.
	 */
	public function sweep(): int {
		$done = 0;

		foreach ( $this->transactions->find_fulfilment_due( self::MAX_ATTEMPTS, 20 ) as $id ) {
			if ( $this->fulfil( $id ) ) {
				++$done;
			}
		}

		return $done;
	}

	/**
	 * Administrator action: retry a failed fulfilment now.
	 *
	 * @param int $id Transaction ID.
	 * @return bool Whether fulfilment completed.
	 */
	public function retry( int $id ): bool {
		return $this->transactions->reset_fulfilment( $id ) && $this->fulfil( $id );
	}

	/**
	 * Administrator action: send the customer email again with a fresh link
	 * (new token, new expiry). The previous link stops working.
	 *
	 * @param int $id Transaction ID.
	 * @return bool Whether the email was sent.
	 */
	public function resend( int $id ): bool {
		if ( ! $this->transactions->reset_fulfilment( $id ) ) {
			return false;
		}

		$this->mailer->allow_customer_resend( $id );

		return $this->fulfil( $id );
	}

	/**
	 * Fulfil one transaction.
	 *
	 * @param int $id Transaction ID.
	 * @return bool True when fulfilment completed in this call.
	 */
	public function fulfil( int $id ): bool {
		if ( ! $this->transactions->claim_fulfilment( $id, self::LOCK_SECONDS ) ) {
			return false;
		}

		$transaction = $this->transactions->find_by_id( $id );
		if ( null === $transaction ) {
			$this->transactions->fail_fulfilment( $id, true, 'Transaction disappeared after claim.' );
			return false;
		}

		$attempts = (int) $transaction['fulfilment_attempts'];

		try {
			$this->deliver( $transaction );
			$this->transactions->complete_fulfilment( $id );

			$this->logger->info( 'Fulfilment completed.', array( 'transaction' => (string) $transaction['public_id'] ) );

			return true;
		} catch ( \Throwable $e ) {
			$permanent = $attempts >= self::MAX_ATTEMPTS;
			$this->transactions->fail_fulfilment( $id, $permanent, $e->getMessage() );

			$this->logger->error(
				$permanent ? 'Fulfilment failed permanently; needs an administrator.' : 'Fulfilment failed; will retry.',
				array(
					'transaction' => (string) $transaction['public_id'],
					'attempt'     => $attempts,
					'exception'   => $e,
				)
			);

			return false;
		}//end try
	}

	/**
	 * Grant access and send the customer and admin emails.
	 *
	 * @param array<string, mixed> $transaction Transaction row (claimed).
	 *
	 * @throws \RuntimeException When anything needed for delivery is missing or fails.
	 * @throws \Throwable         Rethrown from granting access, after the email reservation is released.
	 */
	private function deliver( array $transaction ): void {
		$id       = (int) $transaction['id'];
		$offering = $this->offerings->find( (string) $transaction['offering_key'] );

		if ( null === $offering ) {
			throw new \RuntimeException( 'Offering is no longer in config/offerings.php.' );
		}

		// Email already went out on an earlier attempt: do not issue new links.
		if ( $this->mailer->customer_email_sent( $id ) ) {
			$this->mailer->send_admin_sale_notice( $transaction, $offering );
			return;
		}

		$handler = $offering->fulfilment['handler'] ?? null;
		if ( 'resource_access' !== $handler ) {
			throw new \RuntimeException( 'Unsupported fulfilment handler for offering ' . $offering->key . '.' );
		}

		$keys = $offering->fulfilment['resources'] ?? array();
		if ( ! is_array( $keys ) || array() === $keys ) {
			throw new \RuntimeException( 'Offering ' . $offering->key . ' has no resources configured.' );
		}

		// Reserve the customer email BEFORE creating links: if another job is
		// still sending (e.g. a slow SMTP server outlived the lock), new links
		// here would silently invalidate the ones in that email.
		if ( ! $this->mailer->reserve_customer_email( $transaction ) ) {
			throw new \RuntimeException( 'Customer email is being sent by another job; will retry.' );
		}

		try {
			$grants = $this->grant_access( $transaction, $keys );
		} catch ( \Throwable $e ) {
			$this->mailer->release_customer_email( $id, $e->getMessage() );
			throw $e;
		}

		$this->mailer->send_customer_fulfilment( $transaction, $offering, $grants );
		$this->mailer->send_admin_sale_notice( $transaction, $offering );
	}

	/**
	 * Create (or refresh) the entitlement and access link for each resource.
	 *
	 * @param array<string, mixed> $transaction Transaction row.
	 * @param array<mixed>         $keys        Resource keys from the offering.
	 * @return list<array{label: string, url: string, expires_at: string, file: string, attach: bool, max_attach: int}>
	 *
	 * @throws \RuntimeException When a resource is unavailable or cannot be granted.
	 */
	private function grant_access( array $transaction, array $keys ): array {
		$id = (int) $transaction['id'];

		$private_dir = $this->config->private_dir();
		$grants      = array();

		foreach ( $keys as $key ) {
			$resource = $this->resources->find( (string) $key );
			$file     = null === $resource ? null : $resource->file_path( $private_dir );

			if ( null === $resource || null === $file ) {
				throw new \RuntimeException( 'Resource unavailable: ' . (string) $key . '.' );
			}

			$token      = AccessController::new_token();
			$expires_at = gmdate( 'Y-m-d H:i:s', time() + $resource->access_days * DAY_IN_SECONDS );

			$this->entitlements->grant( $id, $resource->key, (string) $transaction['customer_email'], $token['hash'], $expires_at );

			$grants[] = array(
				'label'      => $resource->label,
				'url'        => AccessController::url( $token['token'] ),
				'expires_at' => $expires_at,
				'file'       => $file,
				'attach'     => $resource->attach,
				'max_attach' => $resource->max_attach,
			);
		}//end foreach

		return $grants;
	}
}
