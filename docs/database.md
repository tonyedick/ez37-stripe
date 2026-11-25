# Database

Schema version **1.0.0** — defined in `ez37-payments/src/Install/Schema.php`.

## Why custom tables

Payments need guarantees that `posts`/`postmeta` cannot give:

| Need | How the custom tables meet it |
|---|---|
| Never process a Stripe event twice | `UNIQUE (stripe_event_id)` — a duplicate insert fails |
| One transaction per Checkout Session | `UNIQUE (stripe_checkout_session_id)` |
| Mark paid exactly once | Conditional `UPDATE … WHERE payment_status IN (…)` on an indexed column |
| No duplicate emails or entitlements | `UNIQUE (transaction_id, email_type)`, `UNIQUE (transaction_id, resource_key)` |
| Fast admin/sweeper queries | Composite indexes on status + date |

## Conventions

- Tables use the site's table prefix (e.g. `wp_ez37_transactions`).
- **Stripe IDs, statuses, keys and hashes** use `CHARACTER SET ascii COLLATE ascii_bin`:
  Stripe IDs are case-sensitive ASCII, and 1 byte/character keeps `VARCHAR(255)` unique
  indexes within InnoDB's key-length limit.
- **Names, emails, messages** use the site's charset (normally `utf8mb4`). Email indexes use a
  191-character prefix, as WordPress core does.
- **Amounts** are unsigned integers in the smallest currency unit (cents for USD), as Stripe uses.
- **Timestamps** are `DATETIME` in **UTC**, set by application code (no database defaults).
- **No foreign keys.** `dbDelta()` does not manage them. Relationships are enforced in code.
- Schema changes bump `Schema::DB_VERSION`. `Schema::maybe_upgrade()` re-runs `dbDelta()` on the
  next request when the stored option `ez37_payments_db_version` differs. Changes are additive
  only, so a rollback to an older plugin version keeps working.

## Relationships

```
ez37_transactions (1) ──< ez37_stripe_events   (transaction_id, nullable: unknown sessions are logged too)
ez37_transactions (1) ──< ez37_entitlements    (transaction_id; one per resource)
ez37_transactions (1) ──< ez37_email_log       (transaction_id; one per email type)
```

## `ez37_transactions`

One row per checkout attempt.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned, PK | Internal only |
| `public_id` | char(26) ascii, **unique** | Random public reference; sent to Stripe as `client_reference_id` |
| `offering_key` | varchar(64) ascii | Indexed |
| `offering_name` | varchar(191) | Snapshot at purchase time |
| `stripe_price_id` | varchar(255) ascii | Snapshot |
| `amount` | bigint unsigned | Configured price, cents, before tax |
| `currency` | char(3) ascii | Lowercase ISO 4217 (`usd`) |
| `customer_name` | varchar(191) | |
| `customer_email` | varchar(254) | Indexed (191 prefix) |
| `stripe_mode` | varchar(4) ascii | `test` / `live` |
| `stripe_checkout_session_id` | varchar(255) ascii, null, **unique** | |
| `stripe_payment_intent_id` | varchar(255) ascii, null, **unique** | |
| `stripe_customer_id` | varchar(255) ascii, null | |
| `amount_subtotal` | bigint unsigned, null | From Stripe (tax readiness) |
| `amount_tax` | bigint unsigned, null | From Stripe `total_details.amount_tax`; 0 while tax is not configured |
| `amount_paid` | bigint unsigned, null | From Stripe `amount_total` |
| `payment_status` | varchar(20) ascii, default `pending` | See state machine below |
| `fulfilment_status` | varchar(20) ascii, default `not_started` | See state machine below |
| `fulfilment_attempts` | smallint unsigned, default 0 | |
| `fulfilment_locked_until` | datetime, null | Crash-safe job lock |
| `review_required` | tinyint, default 0 | Set when a webhook does not match the transaction (amount, currency, reference); such transactions are never fulfilled automatically |
| `failure_code` | varchar(64) ascii, null | Machine-readable |
| `failure_message` | varchar(500), null | Sanitised; never secrets or card data |
| `enrolment_json` | longtext, null | Schema 1.1.0. Validated enrolment answers (only the fields the offering configures), JSON |
| `agreement_key` | varchar(64) ascii, null | Schema 1.1.0. Agreement the customer accepted |
| `agreement_version` | varchar(32) ascii, null | Version label shown when they accepted |
| `agreement_hash` | char(64) ascii, null | SHA-256 of the exact agreement text (key, version, title, sections) |
| `agreement_accepted_at` | datetime, null | UTC time of acceptance (set server-side when the order is created) |
| `created_at`, `updated_at` | datetime | UTC |
| `paid_at`, `fulfilled_at` | datetime, null | UTC |

Indexes: `public_id` (unique), `stripe_checkout_session_id` (unique), `stripe_payment_intent_id`
(unique), `(payment_status, created_at)`, `(fulfilment_status, fulfilment_locked_until)`,
`customer_email(191)`, `offering_key`.

## `ez37_stripe_events`

One row per Stripe event received. Full payloads are **not** stored (they contain personal data).

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned, PK | |
| `stripe_event_id` | varchar(255) ascii, **unique** | Idempotency key |
| `event_type` | varchar(100) ascii | |
| `livemode` | tinyint | |
| `stripe_object_id` | varchar(255) ascii, null | e.g. Checkout Session ID; indexed |
| `transaction_id` | bigint unsigned, null | Indexed |
| `processing_status` | varchar(20) ascii, default `received` | `received`, `processed`, `ignored`, `failed` |
| `error_message` | varchar(500), null | |
| `received_at` | datetime | Indexed (for pruning) |
| `processed_at` | datetime, null | |

## `ez37_entitlements`

What a paid customer may access.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned, PK | |
| `transaction_id` | bigint unsigned | **Unique with `resource_key`** |
| `resource_key` | varchar(64) ascii | |
| `customer_email` | varchar(254) | Indexed (191 prefix) |
| `access_token_hash` | char(64) ascii, **unique** | SHA-256 of the token; the raw token exists only in the email |
| `status` | varchar(20) ascii, default `active` | `active`, `revoked`, `expired` |
| `expires_at` | datetime | Issue time + 7 days (D6) |
| `access_count` | int unsigned, default 0 | |
| `max_access` | int unsigned, null | Optional cap |
| `last_accessed_at`, `revoked_at` | datetime, null | |
| `created_at` | datetime | |

## `ez37_email_log`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned, PK | |
| `transaction_id` | bigint unsigned | **Unique with `email_type`** |
| `email_type` | varchar(40) ascii | `customer_fulfilment`, `admin_sale_notice` |
| `recipient` | varchar(254) | |
| `status` | varchar(20) ascii, default `sending` | `sending`, `sent`, `failed` |
| `attachment_included` | tinyint, default 0 | 0 if sent link-only |
| `attempts` | smallint unsigned, default 0 | |
| `last_error` | varchar(500), null | |
| `sent_at` | datetime, null | |
| `created_at`, `updated_at` | datetime | |

## State machines

Implemented in `src/Transactions/PaymentStatus.php` and `FulfilmentStatus.php`.
Repositories (Phase 5) apply transitions with a conditional `UPDATE … WHERE status IN (allowed_from())`
and treat "0 rows affected" as "already done / not allowed".

**Payment status** — forward-only:

```
pending ──▶ processing | paid | failed | expired
processing ──▶ paid | failed
paid, failed, expired: final
```

**Fulfilment status** — independent of payment status:

```
not_started ──▶ pending ──▶ in_progress ──▶ completed
                               │   ▲
                               ▼   │ (retry)
                             failed
in_progress ──▶ failed_permanent ──(admin retry)──▶ pending
```

A crashed job leaves `in_progress` with an expired `fulfilment_locked_until`; the Phase 6 sweeper
re-claims such rows (lock check in the repository query).

## Uninstall and retention

- Deactivating the plugin changes nothing in the database.
- Deleting the plugin removes the `ez37_view_payments` capability but **keeps all tables**,
  unless `define( 'EZ37_PAYMENTS_ALLOW_DATA_DELETION', true );` is present in `wp-config.php`.
- The `ez37_payments_db_version` option is kept with the tables (so a re-install does not re-run
  migrations unnecessarily) and removed only together with them.
- Retention/pruning jobs are pending the client's retention policy (open item O2).
