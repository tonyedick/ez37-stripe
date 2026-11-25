<?php
/**
 * Validated checkout form input.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Checkout;

/**
 * Validates the values the browser may send: offering key, name, email, the
 * offering's enrolment fields and the agreement tick.
 *
 * Anything else in the request (amount, price, currency…) is ignored by
 * design: the server determines the price from the offering.
 */
final class CheckoutInput {

	public const MAX_NAME_LENGTH  = 100;
	public const MAX_EMAIL_LENGTH = 254;

	/**
	 * Constructor (use from_request()).
	 *
	 * @param string $offering_key Offering key as submitted (format checked later by the catalogue).
	 * @param string $name         Customer name.
	 * @param string $email        Customer email.
	 * @param array<string, string> $enrolment Raw enrolment field values (validated against the offering later).
	 * @param bool   $agreement_accepted Whether the agreement box was ticked.
	 * @param string $agreement_version  Agreement version the customer saw.
	 */
	private function __construct(
		public readonly string $offering_key,
		public readonly string $name,
		public readonly string $email,
		public readonly array $enrolment = array(),
		public readonly bool $agreement_accepted = false,
		public readonly string $agreement_version = ''
	) {}

	/**
	 * Validate raw request values.
	 *
	 * @param array<string, mixed> $raw Unslashed request values.
	 *
	 * @throws CheckoutException With reason INVALID_INPUT when a field is invalid.
	 */
	public static function from_request( array $raw ): self {
		$offering = is_string( $raw['offering'] ?? null ) ? strtolower( trim( $raw['offering'] ) ) : '';
		$name     = is_string( $raw['customer_name'] ?? null ) ? $raw['customer_name'] : '';
		$email    = is_string( $raw['customer_email'] ?? null ) ? $raw['customer_email'] : '';

		// Enrolment fields arrive as enrol[key]=value. Only strings are kept.
		$enrol = array();
		if ( is_array( $raw['enrol'] ?? null ) ) {
			foreach ( $raw['enrol'] as $enrol_key => $enrol_value ) {
				if ( is_string( $enrol_key ) && is_string( $enrol_value ) && strlen( $enrol_value ) <= 2100 ) {
					$enrol[ $enrol_key ] = $enrol_value;
				}
			}
		}

		// Forms that collect first and last name separately send no customer_name.
		if ( '' === $name && isset( $enrol['first_name'], $enrol['last_name'] ) ) {
			$name = $enrol['first_name'] . ' ' . $enrol['last_name'];
		}

		// Reject line breaks outright (email header injection), before any sanitising hides them.
		if ( 1 === preg_match( '/[\r\n]/', $name . $email ) ) {
			throw new CheckoutException( CheckoutException::INVALID_INPUT, 'Line break in name or email.' );
		}

		$name  = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( $name ) ) ?? '' );
		$email = trim( $email );

		if ( '' === $offering ) {
			throw new CheckoutException( CheckoutException::INVALID_INPUT, 'Missing offering.' );
		}

		$name_length = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );
		if ( $name_length < 2 || $name_length > self::MAX_NAME_LENGTH ) {
			throw new CheckoutException( CheckoutException::INVALID_INPUT, 'Invalid name length.' );
		}

		if ( strlen( $email ) > self::MAX_EMAIL_LENGTH || sanitize_email( $email ) !== $email || ! is_email( $email ) ) {
			throw new CheckoutException( CheckoutException::INVALID_INPUT, 'Invalid email.' );
		}

		$version = is_string( $raw['agreement_version'] ?? null ) ? substr( $raw['agreement_version'], 0, 32 ) : '';

		return new self(
			$offering,
			$name,
			strtolower( $email ),
			$enrol,
			'1' === ( is_string( $raw['agreement_accepted'] ?? null ) ? $raw['agreement_accepted'] : '' ),
			$version
		);
	}
}
