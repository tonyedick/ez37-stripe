<?php
/**
 * Entitlement persistence.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Resources;

use EZ37\Payments\Install\Schema;

/**
 * All SQL for ez37_entitlements. One row per (transaction, resource): the
 * UNIQUE key makes granting idempotent. Only a SHA-256 hash of each access
 * token is stored; the raw token exists only in the customer's email.
 * Not final, so unit tests can mock it.
 */
class EntitlementRepository {

	public const ACTIVE  = 'active';
	public const REVOKED = 'revoked';

	/**
	 * Create the entitlement, or give an existing active one a new token and
	 * expiry (used when an earlier fulfilment attempt failed before the email
	 * was sent, so the old token never reached the customer).
	 *
	 * @param int    $transaction_id Transaction ID.
	 * @param string $resource_key   Resource key.
	 * @param string $email          Customer email.
	 * @param string $token_hash     SHA-256 hex of the raw token.
	 * @param string $expires_at     UTC DATETIME.
	 *
	 * @throws \RuntimeException When the entitlement exists but was revoked, or on a database error.
	 */
	public function grant( int $transaction_id, string $resource_key, string $email, string $token_hash, string $expires_at ): void {
		global $wpdb;

		$table = Schema::table( Schema::ENTITLEMENTS );
		$now   = gmdate( 'Y-m-d H:i:s' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; idempotent insert.
		$inserted = $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (transaction_id, resource_key, customer_email, access_token_hash, status, expires_at, access_count, created_at) VALUES (%d, %s, %s, %s, %s, %s, 0, %s)',
				$table,
				$transaction_id,
				$resource_key,
				$email,
				$token_hash,
				self::ACTIVE,
				$expires_at,
				$now
			)
		);

		if ( false === $inserted ) {
			throw new \RuntimeException( 'Could not create entitlement: ' . (string) $wpdb->last_error );
		}

		if ( 1 === $inserted ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET access_token_hash = %s, expires_at = %s WHERE transaction_id = %d AND resource_key = %s AND status = %s',
				$table,
				$token_hash,
				$expires_at,
				$transaction_id,
				$resource_key,
				self::ACTIVE
			)
		);

		if ( 1 !== $updated ) {
			throw new \RuntimeException( 'Entitlement exists but is not active (revoked?); not re-granted.' );
		}
	}

	/**
	 * Find an entitlement (with its transaction's payment state) by token hash.
	 *
	 * @param string $token_hash SHA-256 hex.
	 * @return array<string, mixed>|null
	 */
	public function find_by_token_hash( string $token_hash ): ?array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom tables; access checks must read fresh state.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT e.*, t.payment_status, t.review_required FROM %i e INNER JOIN %i t ON t.id = e.transaction_id WHERE e.access_token_hash = %s LIMIT 1',
				Schema::table( Schema::ENTITLEMENTS ),
				Schema::table( Schema::TRANSACTIONS ),
				$token_hash
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Count one download, atomically, respecting max_access when set.
	 *
	 * @param int $id Entitlement ID.
	 * @return bool False when the limit was already reached.
	 */
	public function record_access( int $id ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table; atomic counter.
		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET access_count = access_count + 1, last_accessed_at = %s WHERE id = %d AND (max_access IS NULL OR access_count < max_access)',
				Schema::table( Schema::ENTITLEMENTS ),
				gmdate( 'Y-m-d H:i:s' ),
				$id
			)
		);

		return 1 === $updated;
	}
}
