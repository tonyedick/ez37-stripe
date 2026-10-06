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

## Planned controls (by phase)

| Phase | Controls |
|---|---|
| 3 | Offering-key validation, server-side price, nonce + Origin check, rate limiting, honeypot, input validation, Stripe idempotency keys, restricted-key permission list |
| 4 | Signature verification (raw body, 5-min tolerance), event-ID dedupe, livemode check, session/reference/amount cross-checks |
| 5 | Atomic conditional state transitions, minimal-data status endpoint |
| 6 | Private file storage, hashed 256-bit access tokens, 7-day expiry, revocation |
| 7 | Header-injection-safe email, escaped templates |
| 8b | Capability + nonce on every admin action |
| 9 | Independent audit of all of the above |
