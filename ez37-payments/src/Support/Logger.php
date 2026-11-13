<?php
/**
 * Redacting logger.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Support;

/**
 * Minimal structured logger that cannot leak secrets.
 *
 * - Writes one line per entry to the PHP error log by default (a server-side
 *   location that is not web-accessible on correctly configured hosts).
 * - Redacts Stripe secret/restricted keys, webhook secrets, client secrets
 *   and Stripe-Signature values wherever they appear in messages or context.
 * - Redacts any context value whose key names a secret (secret, password,
 *   token, signature, authorization, api_key).
 * - Masks email addresses (j***@example.com).
 *
 * The writer is injectable so tests can capture output.
 */
final class Logger {

	private const PREFIX = '[ez37-payments]';

	private const MAX_LENGTH = 2000;

	private const LEVEL_WEIGHT = array(
		'debug'   => 0,
		'info'    => 1,
		'warning' => 2,
		'error'   => 3,
	);

	/**
	 * Context keys whose values are always replaced, matched case-insensitively
	 * as substrings (so "webhook_secret" and "access_token" are both caught).
	 */
	private const SENSITIVE_KEY_PARTS = array( 'secret', 'password', 'passwd', 'token', 'signature', 'authorization', 'api_key', 'apikey', 'cookie' );

	/**
	 * Patterns for secrets that may appear anywhere in text.
	 */
	private const SECRET_PATTERNS = array(
		'/\b(sk|rk)_(live|test)_[A-Za-z0-9]+/'    => '$1_$2_[redacted]',
		'/\bwhsec_[A-Za-z0-9]+/'                  => 'whsec_[redacted]',
		'/\b(cs|pi|seti)_[A-Za-z0-9_]+_secret_[A-Za-z0-9]+/' => '[client_secret redacted]',
		'/\bt=\d+,v1=[a-f0-9]+(,v\d=[a-f0-9]+)*/' => '[stripe-signature redacted]',
	);

	private const EMAIL_PATTERN = '/([A-Za-z0-9._%+\-])[A-Za-z0-9._%+\-]*@([A-Za-z0-9.\-]+\.[A-Za-z]{2,})/';

	/**
	 * Numeric weight of the minimum level to record.
	 *
	 * @var int
	 */
	private int $min_weight;

	/**
	 * Receives each finished log line.
	 *
	 * @var callable(string): void
	 */
	private $writer;

	/**
	 * Constructor.
	 *
	 * @param string        $min_level Minimum level to record.
	 * @param callable|null $writer    Receives each line; defaults to error_log().
	 */
	public function __construct( string $min_level = 'info', ?callable $writer = null ) {
		$this->min_weight = self::LEVEL_WEIGHT[ $min_level ] ?? self::LEVEL_WEIGHT['info'];
		$this->writer     = $writer ?? static function ( string $line ): void {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Intentional: server-side log.
			error_log( $line );
		};
	}

	/**
	 * Debug-level entry.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function debug( string $message, array $context = array() ): void {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Info-level entry.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function info( string $message, array $context = array() ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Warning-level entry.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function warning( string $message, array $context = array() ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Error-level entry.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function error( string $message, array $context = array() ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Record an entry if it meets the minimum level.
	 *
	 * @param string               $level   debug|info|warning|error.
	 * @param string               $message Message.
	 * @param array<string, mixed> $context Context.
	 */
	public function log( string $level, string $message, array $context = array() ): void {
		$weight = self::LEVEL_WEIGHT[ $level ] ?? self::LEVEL_WEIGHT['error'];

		if ( $weight < $this->min_weight ) {
			return;
		}

		$line = sprintf( '%s %s: %s', self::PREFIX, strtoupper( $level ), self::redact( $message ) );

		if ( array() !== $context ) {
			$encoded = json_encode( self::redact_context( $context ), JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Must work without WordPress loaded (tests).
			$line   .= ' ' . ( false === $encoded ? '{"context":"unencodable"}' : $encoded );
		}

		// Strip newlines (log injection) and cap length.
		$line = str_replace( array( "\r", "\n" ), ' ', $line );
		if ( strlen( $line ) > self::MAX_LENGTH ) {
			$line = substr( $line, 0, self::MAX_LENGTH ) . '…[truncated]';
		}

		( $this->writer )( $line );
	}

	/**
	 * Remove secrets and mask emails in free text.
	 *
	 * @param string $text Text.
	 */
	public static function redact( string $text ): string {
		foreach ( self::SECRET_PATTERNS as $pattern => $replacement ) {
			$text = (string) preg_replace( $pattern, $replacement, $text );
		}

		return (string) preg_replace( self::EMAIL_PATTERN, '$1***@$2', $text );
	}

	/**
	 * Redact a context array recursively.
	 *
	 * @param array<mixed> $context Context.
	 * @param int          $depth   Current depth (guards against deep structures).
	 * @return array<mixed>
	 */
	public static function redact_context( array $context, int $depth = 0 ): array {
		if ( $depth > 5 ) {
			return array( '[depth limit]' );
		}

		$out = array();

		foreach ( $context as $key => $value ) {
			if ( is_string( $key ) && self::is_sensitive_key( $key ) ) {
				$out[ $key ] = '[redacted]';
				continue;
			}

			if ( is_array( $value ) ) {
				$out[ $key ] = self::redact_context( $value, $depth + 1 );
			} elseif ( is_string( $value ) ) {
				$out[ $key ] = self::redact( $value );
			} elseif ( is_scalar( $value ) || null === $value ) {
				$out[ $key ] = $value;
			} elseif ( $value instanceof \Throwable ) {
				$out[ $key ] = get_class( $value ) . ': ' . self::redact( $value->getMessage() );
			} else {
				$out[ $key ] = '[' . get_debug_type( $value ) . ']';
			}
		}

		return $out;
	}

	/**
	 * Whether a context key names a secret.
	 *
	 * @param string $key Context key.
	 */
	private static function is_sensitive_key( string $key ): bool {
		$key = strtolower( $key );

		foreach ( self::SENSITIVE_KEY_PARTS as $part ) {
			if ( str_contains( $key, $part ) ) {
				return true;
			}
		}

		return false;
	}
}
