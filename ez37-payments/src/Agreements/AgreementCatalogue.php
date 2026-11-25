<?php
/**
 * Agreement catalogue.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Agreements;

/**
 * Loads and validates agreements from config/agreements.php.
 *
 * An invalid definition is skipped (and reported by errors()), so an offering
 * that points at it cannot be bought until the typo is fixed.
 */
final class AgreementCatalogue {

	public const KEY_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/';

	/**
	 * Valid agreements keyed by key.
	 *
	 * @var array<string, Agreement>
	 */
	private array $agreements = array();

	/**
	 * Problems found while loading.
	 *
	 * @var list<string>
	 */
	private array $errors = array();

	/**
	 * Build from raw definitions.
	 *
	 * @param array<mixed> $definitions Key => definition.
	 */
	public function __construct( array $definitions ) {
		foreach ( $definitions as $key => $definition ) {
			$agreement = $this->build( (string) $key, $definition );

			if ( null !== $agreement ) {
				$this->agreements[ $agreement->key ] = $agreement;
			}
		}
	}

	/**
	 * Load from a PHP config file that returns an array.
	 *
	 * @param string $path Absolute path.
	 */
	public static function from_file( string $path ): self {
		$definitions = is_readable( $path ) ? require $path : array();

		return new self( is_array( $definitions ) ? $definitions : array() );
	}

	/**
	 * Find an agreement. Unknown or malformed keys return null.
	 *
	 * @param string $key Agreement key.
	 */
	public function find( string $key ): ?Agreement {
		if ( 1 !== preg_match( self::KEY_PATTERN, $key ) ) {
			return null;
		}

		return $this->agreements[ $key ] ?? null;
	}

	/**
	 * Problems found while loading.
	 *
	 * @return list<string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Validate one definition.
	 *
	 * @param string $key        Array key.
	 * @param mixed  $definition Definition.
	 */
	private function build( string $key, mixed $definition ): ?Agreement {
		if ( 1 !== preg_match( self::KEY_PATTERN, $key ) || ! is_array( $definition ) ) {
			$this->errors[] = sprintf( 'Agreement "%s" is invalid (bad key or not an array).', $key );
			return null;
		}

		$problems = array();

		$version = $definition['version'] ?? null;
		if ( ! is_string( $version ) || 1 !== preg_match( '/^[A-Za-z0-9._-]{1,32}$/', $version ) ) {
			$problems[] = 'version must be 1–32 letters, digits, dots, dashes or underscores';
		}

		$title = $definition['title'] ?? null;
		if ( ! is_string( $title ) || '' === trim( $title ) || strlen( $title ) > 191 ) {
			$problems[] = 'title must be a non-empty string';
		}

		$label = $definition['label'] ?? null;
		if ( ! is_string( $label ) || '' === trim( $label ) ) {
			$problems[] = 'label must be a non-empty string';
		}

		$draft = $definition['draft'] ?? false;
		if ( ! is_bool( $draft ) ) {
			$problems[] = 'draft must be true or false';
		}

		$sections = array();
		$raw      = $definition['sections'] ?? null;
		if ( ! is_array( $raw ) || array() === $raw ) {
			$problems[] = 'sections must be a non-empty list';
		} else {
			foreach ( $raw as $section ) {
				$heading    = is_array( $section ) ? ( $section['heading'] ?? null ) : null;
				$paragraphs = is_array( $section ) ? ( $section['paragraphs'] ?? null ) : null;

				if ( ! is_string( $heading ) || ! is_array( $paragraphs ) || array() === $paragraphs || array_filter( $paragraphs, 'is_string' ) !== $paragraphs ) {
					$problems[] = 'each section needs a heading and a list of paragraph strings';
					break;
				}

				$sections[] = array(
					'heading'    => $heading,
					'paragraphs' => array_values( $paragraphs ),
				);
			}
		}

		if ( array() !== $problems ) {
			$this->errors[] = sprintf( 'Agreement "%s" is disabled: %s.', $key, implode( '; ', $problems ) );
			return null;
		}

		return new Agreement( $key, (string) $version, trim( (string) $title ), trim( (string) $label ), (bool) $draft, $sections );
	}
}
