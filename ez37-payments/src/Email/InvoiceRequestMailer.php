<?php
/**
 * Emails for an invoice request (instalments / other payment terms).
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Email;

use EZ37\Payments\Config;
use EZ37\Payments\Offerings\Offering;
use EZ37\Payments\Support\Logger;

/**
 * When a customer does not pay in full by card, nothing is charged. Two plain-text emails go out:
 * the EZ37 team gets every answer so it can issue the invoice, and the customer gets an
 * acknowledgement. Sending is best-effort: a mail failure is logged and never blocks the customer,
 * because the request itself is already stored and visible on the Payments admin page.
 */
class InvoiceRequestMailer {

	/**
	 * Constructor.
	 *
	 * @param Config $config Configuration (team recipient).
	 * @param Logger $logger Logger.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly Logger $logger
	) {}

	/**
	 * Send the team notice and the customer acknowledgement.
	 *
	 * @param Offering              $offering  Offering.
	 * @param string                $name      Customer name.
	 * @param string                $email     Customer email.
	 * @param array<string, string> $enrolment Validated enrolment answers.
	 * @param string                $reference Public transaction reference.
	 */
	public function send( Offering $offering, string $name, string $email, array $enrolment, string $reference ): void {
		$ref = strtoupper( substr( $reference, 0, 8 ) );

		$team = $this->config->admin_email();
		if ( null !== $team ) {
			$lines = array(
				'An enrolment request needs an invoice. NO payment has been taken.',
				'',
				'Programme: ' . $offering->name . ' (' . $offering->formatted_price() . ')',
				'Reference: ' . $ref,
				'Name: ' . $name,
				'Email: ' . $email,
				'',
				'Answers:',
			);
			foreach ( $enrolment as $key => $value ) {
				if ( '' !== $value ) {
					$lines[] = str_replace( '_', ' ', (string) $key ) . ': ' . str_replace( array( "\r", "\n" ), ' ', $value );
				}
			}
			$lines[] = '';
			$lines[] = 'Next step: send the customer an invoice based on the payment option above. The request is also listed on the Payments page in WordPress.';

			$this->deliver( $team, sprintf( 'Invoice request: %s [%s]', $offering->name, $ref ), implode( "\n", $lines ), array( 'Reply-To: ' . $email ) );
		}

		$body = "Hello {$name},\n\n"
			. "Thank you for enrolling in the {$offering->name}. We have received your enrolment details and your acceptance of the Enrolment Agreement.\n\n"
			. "You have not been charged. Our team will contact you shortly with an invoice based on the payment option you chose.\n\n"
			. "Your reference: {$ref}\n\n"
			. "Thank you,\nEZ37 Solutions\n";
		$this->deliver( $email, sprintf( 'We have received your enrolment: %s', $offering->name ), $body, array() );
	}

	/**
	 * Free registration: send the team notice and the customer acknowledgement. Nothing is charged.
	 *
	 * @param Offering              $offering  Offering.
	 * @param string                $name      Customer name.
	 * @param string                $email     Customer email.
	 * @param array<string, string> $enrolment Validated enrolment answers.
	 * @param string                $reference Public transaction reference.
	 */
	public function send_registration( Offering $offering, string $name, string $email, array $enrolment, string $reference ): void {
		$ref = strtoupper( substr( $reference, 0, 8 ) );

		$team = $this->config->admin_email();
		if ( null !== $team ) {
			$lines = array(
				'A new registration was received for a FREE programme. No payment is involved.',
				'',
				'Programme: ' . $offering->name,
				'Reference: ' . $ref,
				'Name: ' . $name,
				'Email: ' . $email,
				'',
				'Answers:',
			);
			foreach ( $enrolment as $key => $value ) {
				if ( '' !== $value ) {
					$lines[] = str_replace( '_', ' ', (string) $key ) . ': ' . str_replace( array( "\r", "\n" ), ' ', $value );
				}
			}
			$lines[] = '';
			$lines[] = 'The registration is also listed on the Payments page in WordPress.';

			$this->deliver( $team, sprintf( 'New registration: %s [%s]', $offering->name, $ref ), implode( "\n", $lines ), array( 'Reply-To: ' . $email ) );
		}

		$body = "Hello {$name},\n\n"
			. "Thank you for registering for {$offering->name}. We have received your details and your acceptance of the Peer-to-Peer Agreement.\n\n"
			. "There is no charge. Our team will email you shortly with the link to the list of participants so you can choose your peer coaching partner(s).\n\n"
			. "Your reference: {$ref}\n\n"
			. "Thank you,\nEZ37 Solutions\n";
		$this->deliver( $email, sprintf( 'We have received your registration: %s', $offering->name ), $body, array() );
	}

	/**
	 * Send one plain-text email, logging (never throwing) on failure.
	 *
	 * @param string   $to      Recipient.
	 * @param string   $subject Subject.
	 * @param string   $body    Body.
	 * @param string[] $extra   Extra headers.
	 */
	private function deliver( string $to, string $subject, string $body, array $extra ): void {
		$subject = str_replace( array( "\r", "\n" ), ' ', $subject );

		try {
			$sent = wp_mail( $to, $subject, $body, array_merge( array( 'Content-Type: text/plain; charset=UTF-8' ), $extra ) );
		} catch ( \Throwable $e ) {
			$sent = false;
		}

		if ( ! $sent ) {
			$this->logger->warning( 'Invoice request email could not be sent.', array( 'subject' => $subject ) );
		}
	}
}
