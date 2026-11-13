# Security

The full threat model (attack surfaces AS1–AS9 and planned protections) is in
[phase-1-analysis.md §8](phase-1-analysis.md#8-security-model). This document records the controls
that are **implemented**, and is extended each phase.

## Implemented in v0.1.0

### Configuration and secrets

Implemented in `src/Config.php`.

| Control | Implementation |
|---|---|
| Secrets outside code, Git and the database | Settings are read only from PHP constants (`wp-config.php`) or environment variables. `Config` never calls `get_option()`. `.env` files are git-ignored; `.env.example` holds names only. |
| Fixed allow-list of settings | Only the names in `Config::KEYS` are read. Nothing else in the environment is touched. |
| Test/live separation | Only the active mode's credentials are returned. A key whose prefix does not match the mode (`sk_test_`/`rk_test_` vs `sk_live_`/`rk_live_`) is rejected and returned as `null`. Publishable keys (`pk_`) are rejected. |
| Safe default mode | Missing or misspelt `EZ37_STRIPE_MODE` → **test** mode (and an error). A typo can never enable live mode. |
| Checkout off by default | `EZ37_PAYMENTS_CHECKOUT_ENABLED` must be explicitly truthy, **and** there must be no configuration errors, before `can_accept_payments()` returns true. |
| Live keys outside production | A live key present while in test mode raises a warning in wp-admin. |
| No secrets in debug output | `Config::__debugInfo()` redacts every `*SECRET*` setting for `var_dump()`/`print_r()`. Error and warning messages name settings but never contain values. (`var_export()` ignores `__debugInfo()`; never `var_export` the config object.) |
| Admin visibility | Configuration problems are shown only to users with `manage_options`, escaped with `esc_html()`. |
| Restricted keys supported | `rk_` keys are accepted so production can use a Stripe restricted key with minimal permissions (permissions defined in Phase 3). |

### Logging

Implemented in `src/Support/Logger.php`.

- Destination: the PHP error log (server-side). Production must have `WP_DEBUG_DISPLAY` off
  and the log outside the web root (host default on most managed hosts — verify in O4).
- Redacted wherever they appear in messages **or** context: `sk_/rk_` keys, `whsec_` secrets,
  Checkout/PaymentIntent client secrets, `Stripe-Signature` header values.
- Context keys containing `secret`, `password`, `token`, `signature`, `authorization`, `api_key`,
  `cookie` are replaced with `[redacted]`, recursively.
- Email addresses are masked (`j***@example.com`).
- Exceptions are logged as class + redacted message (no stack traces with arguments).
- Objects are never dumped; newlines are stripped (log-injection protection); lines are capped
  at 2,000 characters.

### Data protection

- Payment tables are not removed on uninstall unless explicitly allowed
  (`EZ37_PAYMENTS_ALLOW_DATA_DELETION`). See [database.md](database.md#uninstall-and-retention).
- Payment data is accessible only through code; the capability `ez37_view_payments` is
  created for the Phase 8b admin screen and granted to administrators only.
- Webhook payloads will not be stored (schema has no payload column).

### Platform

- Network-wide activation on multisite is refused.
- PHP < 8.1 or missing dependencies → admin notice and the plugin stays inert (no fatal error).
- The release build script aborts if anything resembling a Stripe secret is found in the build.

## Implemented in v0.2.0 (Phase 3 — checkout start)

| Threat | Control |
|---|---|
| Price manipulation | Form submits only offering key, name, email. Price ID, amount and currency come from `config/offerings.php`. Extra fields are ignored. |
| Misconfigured Stripe Price | Before checkout, the Price is retrieved and must be active, `one_time`, same currency, same amount, same mode as config. Mismatch → checkout blocked for that offering, error logged. |
| Offering manipulation | Key must match `^[a-z0-9](…)$` (≤64 chars), exist, be active, and have a Price ID for the current mode. |
| CSRF | WordPress nonce (`ez37_checkout`) **and** Origin/Referer host must equal the site host. |
| Bots / abuse | Honeypot field; 10 attempts / 10 minutes per IP and per email (hashed transient keys). IP is `REMOTE_ADDR` only (forwarded headers are forgeable) — revisit behind a CDN (O4). |
| Header injection | Name/email containing CR/LF are rejected before sanitising. |
| Open redirect | Success redirect only to `https://checkout.stripe.com`; error redirect only to a validated same-site referer (`wp_validate_redirect`). |
| Duplicate sessions on retry | Stripe idempotency key `ez37-checkout-<public_id>` per transaction. |
| Information leakage | Customers see generic messages; details go to the redacting log. Emails are masked in logs. |
| Enumeration | Transaction `public_id` is 104 random bits; the internal ID is never exposed. |
| Output escaping | Templates use `esc_html`, `esc_attr`, `esc_url`. |
| SQL | `$wpdb->insert()` / `update()` with explicit formats; no string-built SQL. |
| Test/live mix-up | Session created with the active mode's key only; Price `livemode` must match the mode. |

## Implemented in v0.3.0 (Phase 4 — webhooks)

| Threat | Control |
|---|---|
| Forged events | Official SDK signature check over the raw body with the active mode's `whsec_` secret. |
| Replay | 300-second timestamp tolerance (never 0) plus unique event IDs. |
| Duplicate / concurrent / out-of-order delivery | `INSERT IGNORE` on `stripe_event_id`; forward-only conditional `UPDATE`s. |
| Test event on live site (or vice versa) | Event `livemode` must equal the configured mode and the transaction's `stripe_mode`. |
| Paying for something else / less | `client_reference_id`, metadata, mode, currency, subtotal = configured price, total = subtotal + tax, no discount: else flagged for review, never paid. |
| Oversized bodies | Rejected above 512 KB before verification. |
| Data exposure | Unauthenticated payloads are never logged; event payloads are never stored. |
| SQL | Identifiers via `%i`, values via `prepare()`; column names in transitions are allow-listed by pattern. |

**Operational requirement:** the checkout page must be excluded from page caching, or visitors may
receive an expired nonce (they would see "Your session expired").

## Planned controls (by phase)

| Phase | Controls |
|---|---|
| 5 | Atomic conditional state transitions, minimal-data status endpoint |
| 6 | Private file storage, hashed 256-bit access tokens, 7-day expiry, revocation |
| 7 | Header-injection-safe email, escaped templates |
| 8b | Capability + nonce on every admin action |
| 9 | Independent audit of all of the above |
