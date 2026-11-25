<?php
/**
 * Enrolment field validation.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Checkout;

/**
 * Validates the extra fields an offering collects before payment (phone,
 * country, organisation…). Field definitions come from the offering
 * configuration; the browser can only supply values for those fields, and a
 * select value must be one of the configured options.
 */
final class EnrolmentForm {

	/**
	 * Validate submitted values against the configured fields.
	 *
	 * Unknown submitted keys are ignored (never stored).
	 *
	 * @param list<array<string, mixed>> $fields Field definitions (from OfferingCatalogue).
	 * @param array<string, mixed>       $raw    Submitted values, keyed by field key.
	 * @return array<string, string> Clean values for every configured field ('' when optional and empty).
	 *
	 * @throws CheckoutException INVALID_INPUT when a value is missing or invalid.
	 */
	public static function validate( array $fields, array $raw ): array {
		$clean = array();

		foreach ( $fields as $field ) {
			$key   = (string) $field['key'];
			$value = is_string( $raw[ $key ] ?? null ) ? $raw[ $key ] : '';

			if ( 'textarea' !== $field['type'] && 1 === preg_match( '/[\r\n]/', $value ) ) {
				throw new CheckoutException( CheckoutException::INVALID_INPUT, 'Line break in enrolment field ' . $key );
			}

			$value = 'textarea' === $field['type']
				? trim( sanitize_textarea_field( $value ) )
				: trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( $value ) ) ?? '' );

			if ( '' === $value ) {
				if ( $field['required'] ) {
					throw new CheckoutException( CheckoutException::INVALID_INPUT, 'Missing enrolment field ' . $key );
				}
				$clean[ $key ] = '';
				continue;
			}

			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
			if ( $length > (int) $field['max'] ) {
				throw new CheckoutException( CheckoutException::INVALID_INPUT, 'Enrolment field too long: ' . $key );
			}

			if ( 'select' === $field['type'] && ! in_array( $value, $field['options'], true ) ) {
				throw new CheckoutException( CheckoutException::INVALID_INPUT, 'Option not allowed for ' . $key );
			}

			if ( 'tel' === $field['type'] && 1 !== preg_match( '/^[0-9+()\-. ]{5,30}$/', $value ) ) {
				throw new CheckoutException( CheckoutException::INVALID_INPUT, 'Invalid phone number' );
			}

			$clean[ $key ] = $value;
		}

		return $clean;
	}
}
