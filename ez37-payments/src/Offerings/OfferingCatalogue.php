<?php
/**
 * Offering catalogue.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Offerings;

/**
 * Loads and validates offerings from config/offerings.php.
 *
 * An invalid definition is skipped (and reported by errors()), so one typo
 * can never make a wrongly priced offering purchasable.
 */
final class OfferingCatalogue {

	/**
	 * Allowed offering keys: lowercase letters, digits and hyphens, 1–64 chars.
	 */
	public const KEY_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/';

	/**
	 * Currencies the plugin can display and validate correctly.
	 * Two-decimal currencies only; extend deliberately when needed.
	 */
	public const SUPPORTED_CURRENCIES = array( 'usd' );

	/**
	 * Supported tax modes. "none" only until tax is confirmed (D7).
	 */
	public const TAX_MODES = array( 'none' );

	/**
	 * Enrolment field types the checkout form can render.
	 */
	public const FIELD_TYPES = array( 'text', 'tel', 'select', 'textarea' );

	/**
	 * Valid offerings keyed by offering key.
	 *
	 * @var array<string, Offering>
	 */
	private array $offerings = array();

	/**
	 * Problems found while loading.
	 *
	 * @var list<string>
	 */
	private array $errors = array();

	/**
	 * Build from raw definitions.
	 *
	 * @param array<mixed> $definitions Offering key => definition array.
	 */
	public function __construct( array $definitions ) {
		foreach ( $definitions as $key => $definition ) {
			$offering = $this->build( $key, $definition );

			if ( null !== $offering ) {
				$this->offerings[ $offering->key ] = $offering;
			}
		}
	}

	/**
	 * Load from a PHP config file that returns an array.
	 *
	 * @param string $path Absolute path to the config file.
	 */
	public static function from_file( string $path ): self {
		$definitions = is_readable( $path ) ? require $path : array();

		return new self( is_array( $definitions ) ? $definitions : array() );
	}

	/**
	 * Find an offering by key, active or not. Unknown or malformed keys return null.
	 *
	 * @param string $key Offering key from the request.
	 */
	public function find( string $key ): ?Offering {
		if ( 1 !== preg_match( self::KEY_PATTERN, $key ) ) {
			return null;
		}

		return $this->offerings[ $key ] ?? null;
	}

	/**
	 * All valid offerings.
	 *
	 * @return array<string, Offering>
	 */
	public function all(): array {
		return $this->offerings;
	}

	/**
	 * Problems found while loading. Never contains secrets (there are none here).
	 *
	 * @return list<string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Validate one definition and build the Offering.
	 *
	 * @param int|string $key        Array key.
	 * @param mixed      $definition Definition.
	 */
	private function build( int|string $key, mixed $definition ): ?Offering {
		$key = (string) $key;

		if ( 1 !== preg_match( self::KEY_PATTERN, $key ) ) {
			$this->errors[] = sprintf( 'Offering key "%s" is invalid (use lowercase letters, digits and hyphens).', $key );
			return null;
		}

		if ( ! is_array( $definition ) ) {
			$this->errors[] = sprintf( 'Offering "%s" must be an array.', $key );
			return null;
		}

		$problems = array();

		$name = $definition['name'] ?? null;
		if ( ! is_string( $name ) || '' === trim( $name ) || strlen( $name ) > 191 ) {
			$problems[] = 'name must be a non-empty string (max 191 characters)';
		}

		$active = $definition['active'] ?? null;
		if ( ! is_bool( $active ) ) {
			$problems[] = 'active must be true or false';
		}

		$currency = $definition['currency'] ?? null;
		if ( ! is_string( $currency ) || ! in_array( $currency, self::SUPPORTED_CURRENCIES, true ) ) {
			$problems[] = 'currency must be one of: ' . implode( ', ', self::SUPPORTED_CURRENCIES );
		}

		$free = $definition['free'] ?? false;
		if ( ! is_bool( $free ) ) {
			$problems[] = 'free must be true or false';
			$free       = false;
		}

		$amount = $definition['amount'] ?? null;
		if ( $free ) {
			// A free registration never reaches Stripe, so it must not carry a price.
			if ( 0 !== $amount ) {
				$problems[] = 'a free offering must have amount 0';
			}
		} elseif ( ! is_int( $amount ) || $amount < 1 ) {
			$problems[] = 'amount must be a positive integer in cents';
		}

		$price_ids = $definition['price_ids'] ?? null;
		if ( ! is_array( $price_ids ) ) {
			$problems[] = 'price_ids must be an array with "test" and "live" entries';
			$price_ids  = array();
		}

		$clean_price_ids = array();
		foreach ( array( 'test', 'live' ) as $mode ) {
			$id = $price_ids[ $mode ] ?? '';
			if ( ! is_string( $id ) || ( '' !== $id && 1 !== preg_match( '/^price_[A-Za-z0-9]+$/', $id ) ) ) {
				$problems[] = sprintf( 'price_ids.%s must be empty or a Stripe Price ID (price_...)', $mode );
				continue;
			}
			$clean_price_ids[ $mode ] = $id;
		}

		$tax = $definition['tax'] ?? 'none';
		if ( ! is_string( $tax ) || ! in_array( $tax, self::TAX_MODES, true ) ) {
			$problems[] = 'tax must be "none" (tax is not confirmed — decision D7)';
		}

		$fulfilment = $definition['fulfilment'] ?? array();
		if ( ! is_array( $fulfilment ) ) {
			$problems[] = 'fulfilment must be an array';
		}

		$enrolment_fields = $this->clean_enrolment_fields( $definition['enrolment'] ?? array(), $problems );

		$agreement_key = $definition['agreement'] ?? '';
		if ( ! is_string( $agreement_key ) || ( '' !== $agreement_key && 1 !== preg_match( self::KEY_PATTERN, $agreement_key ) ) ) {
			$problems[]    = 'agreement must be empty or an agreement key';
			$agreement_key = '';
		}

		if ( array() !== $problems ) {
			$this->errors[] = sprintf( 'Offering "%s" is disabled: %s.', $key, implode( '; ', $problems ) );
			return null;
		}

		return new Offering(
			$key,
			trim( (string) $name ),
			(bool) $active,
			(string) $currency,
			(int) $amount,
			$clean_price_ids,
			(string) $tax,
			(array) $fulfilment,
			$enrolment_fields,
			$agreement_key,
			$free
		);
	}

	/**
	 * Validate the optional enrolment field definitions.
	 *
	 * Each field: key (a-z, 0-9, underscore), label, type (see FIELD_TYPES),
	 * required (bool), optional max (length), options (select), autocomplete.
	 *
	 * @param mixed        $raw      Definition value.
	 * @param list<string> $problems Problems list, appended to.
	 * @return list<array<string, mixed>>
	 */
	private function clean_enrolment_fields( mixed $raw, array &$problems ): array {
		if ( ! is_array( $raw ) ) {
			$problems[] = 'enrolment must be a list of fields';
			return array();
		}

		$fields = array();
		$seen   = array();

		foreach ( $raw as $field ) {
			$key   = is_array( $field ) ? ( $field['key'] ?? null ) : null;
			$label = is_array( $field ) ? ( $field['label'] ?? null ) : null;
			$type  = is_array( $field ) ? ( $field['type'] ?? 'text' ) : null;

			if ( ! is_string( $key ) || 1 !== preg_match( '/^[a-z][a-z0-9_]{0,31}$/', $key ) || isset( $seen[ $key ] ) ) {
				$problems[] = 'enrolment field keys must be unique, lowercase letters, digits and underscores';
				return array();
			}
			if ( ! is_string( $label ) || '' === trim( $label ) || ! is_string( $type ) || ! in_array( $type, self::FIELD_TYPES, true ) ) {
				$problems[] = sprintf( 'enrolment field "%s" needs a label and a valid type', $key );
				return array();
			}

			$options = array();
			if ( 'select' === $type ) {
				$options = $field['options'] ?? null;
				if ( ! is_array( $options ) || array() === $options || array_filter( $options, 'is_string' ) !== $options ) {
					$problems[] = sprintf( 'enrolment field "%s" needs a list of option strings', $key );
					return array();
				}
				$options = array_values( $options );
			}

			$max = $field['max'] ?? ( 'textarea' === $type ? 1000 : 120 );
			if ( ! is_int( $max ) || $max < 1 || $max > 2000 ) {
				$problems[] = sprintf( 'enrolment field "%s" has an invalid max', $key );
				return array();
			}

			$seen[ $key ] = true;
			$fields[]     = array(
				'key'          => $key,
				'label'        => trim( $label ),
				'type'         => $type,
				'required'     => true === ( $field['required'] ?? false ),
				'options'      => $options,
				'max'          => $max,
				'autocomplete' => is_string( $field['autocomplete'] ?? null ) ? $field['autocomplete'] : '',
				'half'         => true === ( $field['half'] ?? false ),
			);
		}

		return $fields;
	}
}
