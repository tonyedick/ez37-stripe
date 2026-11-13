<?php
/**
 * Visitor IP address for rate limiting.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Support;

/**
 * The visitor's IP address, used only as a rate-limiting key (it is hashed
 * before storage and never logged).
 *
 * By default this is REMOTE_ADDR, because forwarded headers can be forged by
 * anyone. Behind a CDN or reverse proxy, REMOTE_ADDR is the proxy, so every
 * visitor would share one rate limit. In that case the deployer supplies the
 * real address with the ez37_payments_client_ip filter, reading the header
 * that THEIR proxy sets (and only trusting it from the proxy's addresses),
 * for example in a small mu-plugin:
 *
 *     add_filter( 'ez37_payments_client_ip', function ( $ip ) {
 *         return $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $ip; // Cloudflare
 *     } );
 *
 * The filtered value must be a valid IP address, or "unknown" is used.
 */
final class ClientIp {

	/**
	 * Current visitor IP, or "unknown".
	 */
	public static function get(): string {
		$remote = $_SERVER['REMOTE_ADDR'] ?? ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Validated below.
		$remote = is_string( $remote ) ? $remote : '';

		$ip = function_exists( 'apply_filters' ) ? apply_filters( 'ez37_payments_client_ip', $remote ) : $remote;

		return is_string( $ip ) && false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : 'unknown';
	}
}
