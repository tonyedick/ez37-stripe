<?php
/**
 * Resource registry.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Resources;

/**
 * Loads and validates config/resources.php. Invalid entries are skipped and
 * reported, so a typo can never expose an unintended file.
 */
final class ResourceCatalogue {

	public const KEY_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/';

	/**
	 * Relative path: segments of letters, digits, dot, dash, underscore; no "..".
	 */
	public const PATH_PATTERN = '#^[A-Za-z0-9_-][A-Za-z0-9._-]*(/[A-Za-z0-9_-][A-Za-z0-9._-]*)*$#';

	/**
	 * Valid resources by key.
	 *
	 * @var array<string, PaidResource>
	 */
	private array $resources = array();

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
			$resource = $this->build( (string) $key, $definition );
			if ( null !== $resource ) {
				$this->resources[ $resource->key ] = $resource;
			}
		}
	}

	/**
	 * Load from a PHP config file returning an array.
	 *
	 * @param string $path Absolute path.
	 */
	public static function from_file( string $path ): self {
		$definitions = is_readable( $path ) ? require $path : array();

		return new self( is_array( $definitions ) ? $definitions : array() );
	}

	/**
	 * Find a resource by key.
	 *
	 * @param string $key Resource key.
	 */
	public function find( string $key ): ?PaidResource {
		return $this->resources[ $key ] ?? null;
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
	 * @param string $key        Key.
	 * @param mixed  $definition Definition.
	 */
	private function build( string $key, mixed $definition ): ?PaidResource {
		$problems = array();

		if ( 1 !== preg_match( self::KEY_PATTERN, $key ) ) {
			$problems[] = 'invalid key';
		}
		if ( ! is_array( $definition ) ) {
			$this->errors[] = sprintf( 'Resource "%s" is disabled: definition must be an array.', $key );
			return null;
		}
		if ( 'private_file' !== ( $definition['type'] ?? null ) ) {
			$problems[] = 'type must be "private_file"';
		}

		$label = $definition['label'] ?? null;
		if ( ! is_string( $label ) || '' === trim( $label ) ) {
			$problems[] = 'label must be a non-empty string';
		}

		$path = $definition['path'] ?? null;
		if ( ! is_string( $path ) || 1 !== preg_match( self::PATH_PATTERN, $path ) || str_contains( $path, '..' ) ) {
			$problems[] = 'path must be a simple relative path inside the private folder';
		}

		$attach      = $definition['attach'] ?? false;
		$max_attach  = $definition['max_attach'] ?? 0;
		$access_days = $definition['access_days'] ?? null;

		if ( ! is_bool( $attach ) ) {
			$problems[] = 'attach must be true or false';
		}
		if ( ! is_int( $max_attach ) || $max_attach < 0 ) {
			$problems[] = 'max_attach must be a non-negative integer (bytes)';
		}
		if ( ! is_int( $access_days ) || $access_days < 1 || $access_days > 3650 ) {
			$problems[] = 'access_days must be an integer between 1 and 3650';
		}

		if ( array() !== $problems ) {
			$this->errors[] = sprintf( 'Resource "%s" is disabled: %s.', $key, implode( '; ', $problems ) );
			return null;
		}

		return new PaidResource( $key, trim( (string) $label ), (string) $path, (bool) $attach, (int) $max_attach, (int) $access_days );
	}
}
