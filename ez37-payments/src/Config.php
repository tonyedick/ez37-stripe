<?php
/**
 * Runtime configuration.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments;

/**
 * Reads plugin configuration from PHP constants (preferred, defined in
 * wp-config.php) or environment variables.
 *
 * Security rules enforced here:
 * - Secrets are never read from, or written to, the WordPress database.
 * - Only the credentials for the active mode (test or live) are exposed.
 * - A key whose prefix does not match the active mode is rejected, so test
 *   and live credentials cannot be mixed up.
 * - Secret values never appear in var_dump()/print_r() output.
 *
 * Instances are immutable. Use Config::from_environment() in production code;
 * tests construct the object with an explicit array of values.
 */
final class Config {

	public const MODE_TEST = 'test';
	public const MODE_LIVE = 'live';

	public const LOG_LEVELS = array( 'debug', 'info', 'warning', 'error' );

	/**
	 * Every setting the plugin reads. Nothing else is looked up.
	 */
	public const KEYS = array(
		'EZ37_STRIPE_MODE',
		'EZ37_STRIPE_TEST_SECRET_KEY',
		'EZ37_STRIPE_TEST_WEBHOOK_SECRET',
		'EZ37_STRIPE_LIVE_SECRET_KEY',
		'EZ37_STRIPE_LIVE_WEBHOOK_SECRET',
		'EZ37_PAYMENTS_CHECKOUT_ENABLED',
		'EZ37_PAYMENTS_LOG_LEVEL',
		'EZ37_PAYMENTS_ADMIN_EMAIL',
	);

	/**
	 * Raw values keyed by setting name. Missing settings are absent.
	 *
	 * @var array<string, string>
	 */
	private array $values;

	/**
	 * Configuration problems found on construction.
	 *
	 * @var list<string>
	 */
	private array $errors = array();

	/**
	 * Non-blocking configuration concerns found on construction.
	 *
	 * @var list<string>
	 */
	private array $warnings = array();

	/**
	 * Build from explicit values (used directly by tests).
	 *
	 * @param array<string, mixed> $values Setting name => value.
	 */
	public function __construct( array $values ) {
		$clean = array();

		foreach ( self::KEYS as $key ) {
			if ( ! array_key_exists( $key, $values ) || null === $values[ $key ] ) {
				continue;
			}

			$value = is_bool( $values[ $key ] ) ? ( $values[ $key ] ? '1' : '0' ) : trim( (string) $values[ $key ] );

			if ( '' !== $value ) {
				$clean[ $key ] = $value;
			}
		}

		$this->values = $clean;
		$this->validate();
	}

	/**
	 * Build from PHP constants, falling back to environment variables.
	 */
	public static function from_environment(): self {
		$values = array();

		foreach ( self::KEYS as $key ) {
			if ( defined( $key ) ) {
				$values[ $key ] = constant( $key );
				continue;
			}

			$env = getenv( $key );
			if ( false !== $env ) {
				$values[ $key ] = $env;
			}
		}

		return new self( $values );
	}

	/**
	 * Active Stripe mode. Defaults to test; an invalid value also falls back
	 * to test (and is reported by errors()), so a typo can never enable live.
	 */
	public function mode(): string {
		$mode = strtolower( $this->values['EZ37_STRIPE_MODE'] ?? self::MODE_TEST );

		return self::MODE_LIVE === $mode ? self::MODE_LIVE : self::MODE_TEST;
	}

	/**
	 * Whether live mode is active.
	 */
	public function is_live(): bool {
		return self::MODE_LIVE === $this->mode();
	}

	/**
	 * Stripe secret (or restricted) API key for the active mode, or null.
	 *
	 * Returns null if the configured key does not match the active mode.
	 */
	public function stripe_secret_key(): ?string {
		$key = $this->values[ $this->mode_key( 'SECRET_KEY' ) ] ?? null;

		return ( null !== $key && self::key_matches_mode( $key, $this->mode() ) ) ? $key : null;
	}

	/**
	 * Stripe webhook signing secret for the active mode, or null.
	 */
	public function stripe_webhook_secret(): ?string {
		$secret = $this->values[ $this->mode_key( 'WEBHOOK_SECRET' ) ] ?? null;

		return ( null !== $secret && str_starts_with( $secret, 'whsec_' ) ) ? $secret : null;
	}

	/**
	 * Master switch for accepting new checkouts. Off unless explicitly enabled.
	 */
	public function is_checkout_enabled(): bool {
		$raw = strtolower( $this->values['EZ37_PAYMENTS_CHECKOUT_ENABLED'] ?? '0' );

		return in_array( $raw, array( '1', 'true', 'yes', 'on' ), true );
	}

	/**
	 * True only when checkout is switched on AND configuration is valid.
	 */
	public function can_accept_payments(): bool {
		return $this->is_checkout_enabled() && array() === $this->errors;
	}

	/**
	 * Minimum log level.
	 */
	public function log_level(): string {
		$level = strtolower( $this->values['EZ37_PAYMENTS_LOG_LEVEL'] ?? 'info' );

		return in_array( $level, self::LOG_LEVELS, true ) ? $level : 'info';
	}

	/**
	 * Recipient for internal sale notifications, or null if not set/invalid.
	 */
	public function admin_email(): ?string {
		$email = $this->values['EZ37_PAYMENTS_ADMIN_EMAIL'] ?? null;

		return ( null !== $email && false !== filter_var( $email, FILTER_VALIDATE_EMAIL ) ) ? $email : null;
	}

	/**
	 * Human-readable configuration problems. Never contains secret values.
	 *
	 * @return list<string>
	 */
	public function errors(): array {
		return $this->errors;
	}

	/**
	 * Non-blocking configuration concerns. Never contains secret values.
	 *
	 * @return list<string>
	 */
	public function warnings(): array {
		return $this->warnings;
	}

	/**
	 * Whether a Stripe key belongs to the given mode.
	 *
	 * Accepts standard secret keys (sk_) and restricted keys (rk_).
	 * Publishable keys (pk_) are rejected: they are not secret keys.
	 *
	 * @param string $key  Stripe API key.
	 * @param string $mode Config::MODE_TEST or Config::MODE_LIVE.
	 */
	public static function key_matches_mode( string $key, string $mode ): bool {
		return str_starts_with( $key, 'sk_' . $mode . '_' ) || str_starts_with( $key, 'rk_' . $mode . '_' );
	}

	/**
	 * Hide secrets from var_dump()/print_r() and debugging tools.
	 *
	 * @return array<string, string>
	 */
	public function __debugInfo(): array {
		$out = array();

		foreach ( $this->values as $key => $value ) {
			$out[ $key ] = ( str_contains( $key, 'SECRET' ) ) ? '[redacted]' : $value;
		}

		return $out;
	}

	/**
	 * Collect configuration problems.
	 */
	private function validate(): void {
		$raw_mode = strtolower( $this->values['EZ37_STRIPE_MODE'] ?? self::MODE_TEST );
		if ( ! in_array( $raw_mode, array( self::MODE_TEST, self::MODE_LIVE ), true ) ) {
			$this->errors[] = 'EZ37_STRIPE_MODE must be "test" or "live". Falling back to test mode.';
		}

		$mode       = $this->mode();
		$key_name   = $this->mode_key( 'SECRET_KEY' );
		$hook_name  = $this->mode_key( 'WEBHOOK_SECRET' );
		$secret_key = $this->values[ $key_name ] ?? null;
		$hook_value = $this->values[ $hook_name ] ?? null;

		if ( null === $secret_key ) {
			$this->errors[] = sprintf( '%s is not set.', $key_name );
		} elseif ( ! self::key_matches_mode( $secret_key, $mode ) ) {
			$this->errors[] = sprintf( '%s does not look like a %s-mode secret or restricted key (expected sk_%s_ or rk_%s_).', $key_name, $mode, $mode, $mode );
		}

		if ( null === $hook_value ) {
			$this->errors[] = sprintf( '%s is not set.', $hook_name );
		} elseif ( ! str_starts_with( $hook_value, 'whsec_' ) ) {
			$this->errors[] = sprintf( '%s does not look like a webhook signing secret (expected whsec_).', $hook_name );
		}

		if ( self::MODE_TEST === $mode && isset( $this->values['EZ37_STRIPE_LIVE_SECRET_KEY'] ) ) {
			// Not blocking (production is first installed in test mode), but
			// live keys should never exist on development or staging.
			$this->warnings[] = 'A live secret key is configured while in test mode. Remove live keys from non-production environments.';
		}

		$raw_level = $this->values['EZ37_PAYMENTS_LOG_LEVEL'] ?? null;
		if ( null !== $raw_level && ! in_array( strtolower( $raw_level ), self::LOG_LEVELS, true ) ) {
			$this->errors[] = 'EZ37_PAYMENTS_LOG_LEVEL must be one of: debug, info, warning, error.';
		}

		if ( isset( $this->values['EZ37_PAYMENTS_ADMIN_EMAIL'] ) && null === $this->admin_email() ) {
			$this->errors[] = 'EZ37_PAYMENTS_ADMIN_EMAIL is not a valid email address.';
		}
	}

	/**
	 * Setting name for the active mode, e.g. EZ37_STRIPE_TEST_SECRET_KEY.
	 *
	 * @param string $suffix SECRET_KEY or WEBHOOK_SECRET.
	 */
	private function mode_key( string $suffix ): string {
		return sprintf( 'EZ37_STRIPE_%s_%s', strtoupper( $this->mode() ), $suffix );
	}
}
