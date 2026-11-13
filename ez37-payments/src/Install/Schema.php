<?php
/**
 * Database schema.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Install;

/**
 * Creates and upgrades the plugin's custom tables with dbDelta().
 *
 * Design notes (see docs/database.md):
 * - Custom tables are used instead of posts/postmeta because payments need
 *   database-enforced uniqueness (one row per Stripe session/event/email)
 *   and atomic conditional updates.
 * - Stripe identifiers, statuses, keys and hashes use CHARACTER SET ascii
 *   COLLATE ascii_bin: Stripe IDs are case-sensitive ASCII, and 1 byte per
 *   character keeps VARCHAR(255) unique indexes within InnoDB key limits.
 * - Human text (names, emails, messages) uses the site's charset/collation.
 * - Amounts are integers in the currency's smallest unit (cents), as Stripe uses.
 * - All DATETIME values are stored in UTC by application code.
 * - No foreign keys (dbDelta does not manage them); integrity is enforced in
 *   the repository layer and documented relationships.
 * - Formatting follows dbDelta's strict rules (one column per line, two
 *   spaces after PRIMARY KEY, KEY not INDEX).
 */
final class Schema {

	/**
	 * Bump when the schema changes; maybe_upgrade() then re-runs dbDelta().
	 */
	public const DB_VERSION = '1.0.0';

	public const VERSION_OPTION = 'ez37_payments_db_version';

	public const TRANSACTIONS  = 'ez37_transactions';
	public const STRIPE_EVENTS = 'ez37_stripe_events';
	public const ENTITLEMENTS  = 'ez37_entitlements';
	public const EMAIL_LOG     = 'ez37_email_log';

	/**
	 * Unprefixed names of all plugin tables.
	 *
	 * @return list<string>
	 */
	public static function table_names(): array {
		return array( self::TRANSACTIONS, self::STRIPE_EVENTS, self::ENTITLEMENTS, self::EMAIL_LOG );
	}

	/**
	 * Full table name including the site's table prefix.
	 *
	 * @param string $table One of the class constants.
	 */
	public static function table( string $table ): string {
		global $wpdb;

		return $wpdb->prefix . $table;
	}

	/**
	 * Create or upgrade all tables and record the schema version.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::create_table_sql( $wpdb->prefix, $wpdb->get_charset_collate() ) );

		update_option( self::VERSION_OPTION, self::DB_VERSION, true );
	}

	/**
	 * Run install() only if the stored schema version differs.
	 * Called on every load; costs one autoloaded option read.
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Drop all plugin tables. Only called from uninstall.php when the site
	 * owner has explicitly opted in to deleting payment records.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		foreach ( self::table_names() as $table ) {
			// Table names are fixed constants plus the site prefix; nothing user-supplied.
			$wpdb->query( 'DROP TABLE IF EXISTS `' . esc_sql( $wpdb->prefix . $table ) . '`' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		}

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * CREATE TABLE statements in dbDelta format.
	 *
	 * Pure function (no WordPress calls) so it can be unit-tested.
	 *
	 * @param string $prefix          Table prefix, e.g. "wp_".
	 * @param string $charset_collate Result of $wpdb->get_charset_collate().
	 * @return list<string>
	 */
	public static function create_table_sql( string $prefix, string $charset_collate ): array {
		$ascii = 'CHARACTER SET ascii COLLATE ascii_bin';

		$transactions = $prefix . self::TRANSACTIONS;
		$events       = $prefix . self::STRIPE_EVENTS;
		$entitlements = $prefix . self::ENTITLEMENTS;
		$email_log    = $prefix . self::EMAIL_LOG;

		return array(
			"CREATE TABLE {$transactions} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
public_id char(26) {$ascii} NOT NULL,
offering_key varchar(64) {$ascii} NOT NULL,
offering_name varchar(191) NOT NULL,
stripe_price_id varchar(255) {$ascii} NOT NULL,
amount bigint(20) unsigned NOT NULL,
currency char(3) {$ascii} NOT NULL,
customer_name varchar(191) NOT NULL,
customer_email varchar(254) NOT NULL,
stripe_mode varchar(4) {$ascii} NOT NULL,
stripe_checkout_session_id varchar(255) {$ascii} NULL,
stripe_payment_intent_id varchar(255) {$ascii} NULL,
stripe_customer_id varchar(255) {$ascii} NULL,
amount_subtotal bigint(20) unsigned NULL,
amount_tax bigint(20) unsigned NULL,
amount_paid bigint(20) unsigned NULL,
payment_status varchar(20) {$ascii} NOT NULL DEFAULT 'pending',
fulfilment_status varchar(20) {$ascii} NOT NULL DEFAULT 'not_started',
fulfilment_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
fulfilment_locked_until datetime NULL,
review_required tinyint(1) unsigned NOT NULL DEFAULT 0,
failure_code varchar(64) {$ascii} NULL,
failure_message varchar(500) NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
paid_at datetime NULL,
fulfilled_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY public_id (public_id),
UNIQUE KEY stripe_checkout_session_id (stripe_checkout_session_id),
UNIQUE KEY stripe_payment_intent_id (stripe_payment_intent_id),
KEY payment_status_created (payment_status,created_at),
KEY fulfilment_queue (fulfilment_status,fulfilment_locked_until),
KEY customer_email (customer_email(191)),
KEY offering_key (offering_key)
) {$charset_collate};",

			"CREATE TABLE {$events} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
stripe_event_id varchar(255) {$ascii} NOT NULL,
event_type varchar(100) {$ascii} NOT NULL,
livemode tinyint(1) unsigned NOT NULL,
stripe_object_id varchar(255) {$ascii} NULL,
transaction_id bigint(20) unsigned NULL,
processing_status varchar(20) {$ascii} NOT NULL DEFAULT 'received',
error_message varchar(500) NULL,
received_at datetime NOT NULL,
processed_at datetime NULL,
PRIMARY KEY  (id),
UNIQUE KEY stripe_event_id (stripe_event_id),
KEY stripe_object_id (stripe_object_id),
KEY transaction_id (transaction_id),
KEY received_at (received_at)
) {$charset_collate};",

			"CREATE TABLE {$entitlements} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
transaction_id bigint(20) unsigned NOT NULL,
resource_key varchar(64) {$ascii} NOT NULL,
customer_email varchar(254) NOT NULL,
access_token_hash char(64) {$ascii} NOT NULL,
status varchar(20) {$ascii} NOT NULL DEFAULT 'active',
expires_at datetime NOT NULL,
access_count int(10) unsigned NOT NULL DEFAULT 0,
max_access int(10) unsigned NULL,
last_accessed_at datetime NULL,
revoked_at datetime NULL,
created_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY transaction_resource (transaction_id,resource_key),
UNIQUE KEY access_token_hash (access_token_hash),
KEY customer_email (customer_email(191)),
KEY status_expires (status,expires_at)
) {$charset_collate};",

			"CREATE TABLE {$email_log} (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
transaction_id bigint(20) unsigned NOT NULL,
email_type varchar(40) {$ascii} NOT NULL,
recipient varchar(254) NOT NULL,
status varchar(20) {$ascii} NOT NULL DEFAULT 'sending',
attachment_included tinyint(1) unsigned NOT NULL DEFAULT 0,
attempts smallint(5) unsigned NOT NULL DEFAULT 0,
last_error varchar(500) NULL,
sent_at datetime NULL,
created_at datetime NOT NULL,
updated_at datetime NOT NULL,
PRIMARY KEY  (id),
UNIQUE KEY transaction_email_type (transaction_id,email_type),
KEY status (status)
) {$charset_collate};",
		);
	}
}
