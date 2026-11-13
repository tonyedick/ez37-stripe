<?php
/**
 * Transactional emails.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Email;

use EZ37\Payments\Config;
use EZ37\Payments\Frontend\Template;
use EZ37\Payments\Offerings\Offering;
use EZ37\Payments\Support\Logger;

/**
 * Sends the two emails decided in D10, through wp_mail() (so the site's SMTP
 * plugin or provider is used):
 *
 * 1. customer_fulfilment: payment confirmation + download link(s) + attachment(s).
 * 2. admin_sale_notice:   internal "new sale" notification.
 *
 * Each is sent at most once per transaction (EmailRepository). Stripe sends
 * its own receipt separately (D5). Not final, so tests can mock it.
 */
class Mailer {

	/**
	 * Constructor.
	 *
	 * @param Config          $config Configuration (admin recipient).
	 * @param EmailRepository $log    Email log.
	 * @param Logger          $logger Logger.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly EmailRepository $log,
		private readonly Logger $logger
	) {}

	/**
	 * Whether the customer email has already been sent for a transaction.
	 *
	 * @param int $transaction_id Transaction ID.
	 */
	public function customer_email_sent( int $transaction_id ): bool {
		return $this->log->was_sent( $transaction_id, EmailRepository::CUSTOMER_FULFILMENT );
	}

	/**
	 * Administrator override: let the customer email be sent again.
	 *
	 * @param int $transaction_id Transaction ID.
	 */
	public function allow_customer_resend( int $transaction_id ): void {
		$this->log->allow_resend( $transaction_id, EmailRepository::CUSTOMER_FULFILMENT );
	}

	/**
	 * Send the combined confirmation + access email.
	 *
	 * @param array<string, mixed>                                                                                     $transaction Transaction row.
	 * @param Offering                                                                                                 $offering    Offering.
	 * @param list<array{label: string, url: string, expires_at: string, file: string, attach: bool, max_attach: int}> $grants Access grants.
	 * @return bool True if sent now, false if it had already been sent.
	 *
	 * @throws \RuntimeException When sending fails.
	 */
	public function send_customer_fulfilment( array $transaction, Offering $offering, array $grants ): bool {
		$id        = (int) $transaction['id'];
		$recipient = (string) $transaction['customer_email'];

		if ( ! $this->log->begin( $id, EmailRepository::CUSTOMER_FULFILMENT, $recipient ) ) {
			return false;
		}

		$attachments = array();
		foreach ( $grants as $grant ) {
			$size = filesize( $grant['file'] );
			if ( $grant['attach'] && false !== $size && $size <= $grant['max_attach'] ) {
				$attachments[] = $grant['file'];
			} elseif ( $grant['attach'] ) {
				$this->logger->warning( 'Resource too large to attach; sent as link only.', array( 'transaction' => (string) $transaction['public_id'] ) );
			}
		}

		$subject = sprintf(
			/* translators: %s: offering name */
			__( 'Payment confirmed: %s', 'ez37-payments' ),
			$offering->name
		);

		$body = Template::render(
			'emails/customer-fulfilment.php',
			array(
				'customer_name' => (string) $transaction['customer_name'],
				'offering_name' => $offering->name,
				'amount'        => Offering::format_amount( (int) ( $transaction['amount_paid'] ?? $transaction['amount'] ), (string) $transaction['currency'] ),
				'reference'     => strtoupper( substr( (string) $transaction['public_id'], 0, 10 ) ),
				'grants'        => array_map(
					static fn ( array $grant ): array => array(
						'label'   => $grant['label'],
						'url'     => $grant['url'],
						'expires' => self::local_date( $grant['expires_at'] ),
					),
					$grants
				),
				'attached'      => array() !== $attachments,
				'site_name'     => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
			)
		);

		$error = $this->send( $recipient, $subject, $body, array( 'Content-Type: text/html; charset=UTF-8' ), $attachments );
		$this->log->finish( $id, EmailRepository::CUSTOMER_FULFILMENT, null === $error, array() !== $attachments, $error );

		if ( null !== $error ) {
			throw new \RuntimeException( 'Customer email failed: ' . $error );
		}

		return true;
	}

	/**
	 * Send the internal sale notice. Failures are logged, never fatal.
	 *
	 * @param array<string, mixed> $transaction Transaction row.
	 * @param Offering             $offering    Offering.
	 */
	public function send_admin_sale_notice( array $transaction, Offering $offering ): void {
		$recipient = $this->config->admin_email();
		if ( null === $recipient ) {
			return;
		}

		$id = (int) $transaction['id'];

		try {
			if ( ! $this->log->begin( $id, EmailRepository::ADMIN_SALE_NOTICE, $recipient ) ) {
				return;
			}
		} catch ( \RuntimeException $e ) {
			$this->logger->error( 'Could not log admin sale notice.', array( 'exception' => $e ) );
			return;
		}

		$amount  = Offering::format_amount( (int) ( $transaction['amount_paid'] ?? $transaction['amount'] ), (string) $transaction['currency'] );
		$subject = sprintf(
			/* translators: 1: offering name, 2: amount, 3: TEST or LIVE */
			__( 'New sale: %1$s (%2$s) [%3$s]', 'ez37-payments' ),
			$offering->name,
			$amount,
			strtoupper( (string) $transaction['stripe_mode'] )
		);

		$body = Template::render(
			'emails/admin-sale-notice.php',
			array(
				'offering_name'  => $offering->name,
				'amount'         => $amount,
				'customer_name'  => (string) $transaction['customer_name'],
				'customer_email' => (string) $transaction['customer_email'],
				'public_id'      => (string) $transaction['public_id'],
				'payment_intent' => (string) ( $transaction['stripe_payment_intent_id'] ?? '' ),
				'mode'           => (string) $transaction['stripe_mode'],
				'paid_at'        => self::local_date( (string) ( $transaction['paid_at'] ?? '' ) ),
			)
		);

		$error = $this->send( $recipient, $subject, $body, array( 'Content-Type: text/plain; charset=UTF-8' ), array() );
		$this->log->finish( $id, EmailRepository::ADMIN_SALE_NOTICE, null === $error, false, $error );

		if ( null !== $error ) {
			$this->logger->warning( 'Admin sale notice could not be sent.', array( 'transaction' => (string) $transaction['public_id'] ) );
		}
	}

	/**
	 * Send through wp_mail(), capturing its error message.
	 *
	 * @param string   $to          Recipient.
	 * @param string   $subject     Subject.
	 * @param string   $body        Body.
	 * @param string[] $headers     Headers.
	 * @param string[] $attachments File paths.
	 * @return string|null Error message, or null on success.
	 */
	private function send( string $to, string $subject, string $body, array $headers, array $attachments ): ?string {
		$error   = null;
		$capture = static function ( $wp_error ) use ( &$error ): void {
			$error = is_object( $wp_error ) && method_exists( $wp_error, 'get_error_message' ) ? (string) $wp_error->get_error_message() : 'wp_mail failed';
		};

		add_action( 'wp_mail_failed', $capture );
		$sent = wp_mail( $to, $subject, $body, $headers, $attachments );
		remove_action( 'wp_mail_failed', $capture );

		if ( $sent ) {
			return null;
		}

		return $error ?? 'wp_mail returned false';
	}

	/**
	 * A UTC DATETIME shown in the site's timezone and date format.
	 *
	 * @param string $utc UTC "Y-m-d H:i:s".
	 */
	private static function local_date( string $utc ): string {
		$timestamp = strtotime( $utc . ' UTC' );
		if ( '' === $utc || false === $timestamp ) {
			return '';
		}

		// "T" adds the timezone abbreviation (e.g. WAT) so times are never ambiguous.
		$format = (string) get_option( 'date_format' ) . ' ' . (string) get_option( 'time_format' ) . ' T';

		return (string) wp_date( $format, $timestamp );
	}
}
