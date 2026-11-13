<?php
/**
 * Secure download endpoint.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Resources;

use EZ37\Payments\Checkout\RateLimiter;
use EZ37\Payments\Config;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Transactions\PaymentStatus;

/**
 * Serves paid files at https://<site>/?ez37_access=<token>.
 *
 * The token is 256 random bits; only its SHA-256 hash is stored. A file is
 * streamed only when the entitlement is active and not expired, its
 * transaction is paid and not flagged for review, and the file resolves
 * inside the private folder. Every failure looks the same to the visitor
 * except "expired", which gets a helpful message.
 */
final class AccessController {

	public const QUERY_VAR = 'ez37_access';

	public const TOKEN_PATTERN = '/^[a-f0-9]{64}$/';

	/**
	 * Constructor.
	 *
	 * @param Config                $config       Configuration (private folder).
	 * @param EntitlementRepository $entitlements Entitlements.
	 * @param ResourceCatalogue     $resources    Resources.
	 * @param RateLimiter           $rate_limiter Guessing defence.
	 * @param Logger                $logger       Logger.
	 */
	public function __construct(
		private readonly Config $config,
		private readonly EntitlementRepository $entitlements,
		private readonly ResourceCatalogue $resources,
		private readonly RateLimiter $rate_limiter,
		private readonly Logger $logger
	) {}

	/**
	 * Register the request handler.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_serve' ), 0 );
	}

	/**
	 * Download URL for a raw token.
	 *
	 * @param string $token Raw token.
	 */
	public static function url( string $token ): string {
		return add_query_arg( self::QUERY_VAR, $token, home_url( '/' ) );
	}

	/**
	 * A new random token and its hash.
	 *
	 * @return array{token: string, hash: string}
	 */
	public static function new_token(): array {
		$token = bin2hex( random_bytes( 32 ) );

		return array(
			'token' => $token,
			'hash'  => hash( 'sha256', $token ),
		);
	}

	/**
	 * Serve the file if this request carries an access token. Never returns when it does.
	 */
	public function maybe_serve(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token is the credential.
		if ( ! isset( $_GET[ self::QUERY_VAR ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The token is the credential.
		$token = sanitize_text_field( wp_unslash( $_GET[ self::QUERY_VAR ] ) );
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';

		if ( ! $this->rate_limiter->attempt( 'access', $ip ) ) {
			$this->deny( 429, __( 'Too many attempts. Please wait a few minutes and try again.', 'ez37-payments' ) );
		}

		$decision = $this->authorise( $token, gmdate( 'Y-m-d H:i:s' ) );

		if ( 'ok' !== $decision['result'] ) {
			$this->logger->info( 'Resource access refused.', array( 'reason' => $decision['result'] ) );

			if ( 'expired' === $decision['result'] ) {
				$this->deny( 410, __( 'This download link has expired. Please contact us and we will send you a new one.', 'ez37-payments' ) );
			}
			$this->deny( 404, __( 'This download link is not valid. Please check the link in your email, or contact us.', 'ez37-payments' ) );
		}

		$this->stream( (string) $decision['file'], (string) $decision['name'] );
	}

	/**
	 * Decide whether a token grants access right now.
	 *
	 * @param string $token Raw token from the URL.
	 * @param string $now   Current UTC DATETIME.
	 * @return array{result: string, file?: string, name?: string}
	 */
	public function authorise( string $token, string $now ): array {
		if ( 1 !== preg_match( self::TOKEN_PATTERN, $token ) ) {
			return array( 'result' => 'invalid' );
		}

		$row = $this->entitlements->find_by_token_hash( hash( 'sha256', $token ) );

		if ( null === $row || EntitlementRepository::ACTIVE !== $row['status'] ) {
			return array( 'result' => 'invalid' );
		}

		if ( PaymentStatus::Paid->value !== $row['payment_status'] || ! empty( $row['review_required'] ) ) {
			return array( 'result' => 'not_paid' );
		}

		if ( (string) $row['expires_at'] <= $now ) {
			return array( 'result' => 'expired' );
		}

		$resource = $this->resources->find( (string) $row['resource_key'] );
		$file     = null === $resource ? null : $resource->file_path( $this->config->private_dir() );

		if ( null === $resource || null === $file ) {
			$this->logger->error( 'Paid resource file is missing.', array( 'resource' => (string) $row['resource_key'] ) );
			return array( 'result' => 'unavailable' );
		}

		if ( ! $this->entitlements->record_access( (int) $row['id'] ) ) {
			return array( 'result' => 'limit_reached' );
		}

		return array(
			'result' => 'ok',
			'file'   => $file,
			'name'   => $resource->download_name(),
		);
	}

	/**
	 * Send the file.
	 *
	 * @param string $file Absolute path (already validated).
	 * @param string $name Download file name.
	 */
	private function stream( string $file, string $name ): never {
		$type = wp_check_filetype( $file );

		nocache_headers();
		header( 'Content-Type: ' . ( is_string( $type['type'] ) && '' !== $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $name ) . '"' );
		header( 'Content-Length: ' . (string) filesize( $file ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a validated private file.
		readfile( $file );
		exit;
	}

	/**
	 * Show a simple message page and stop.
	 *
	 * @param int    $status  HTTP status.
	 * @param string $message Message.
	 */
	private function deny( int $status, string $message ): never {
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'Referrer-Policy: no-referrer' );
		wp_die( esc_html( $message ), esc_html__( 'Download', 'ez37-payments' ), array( 'response' => (int) $status ) );
	}
}
