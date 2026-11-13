<?php
/**
 * Validated checkout form input.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Checkout;

/**
 * Validates the three values the browser may send: offering key, name, email.
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
	 */
	private function __construct(
		public readonly string $offering_key,
		public readonly string $name,
		public readonly string $email
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

		return new self( $offering, $name, strtolower( $email ) );
	}
}
