# Changelog

All notable changes to the EZ37 Payments plugin.

## [0.5.0] — Phases 6–7: Fulfilment and email

### Added
- Resource registry (`config/resources.php`) and private folder setting `EZ37_PAYMENTS_PRIVATE_DIR`.
- Fulfilment job on payment (WP-Cron), atomic claim/lock, retries, hourly sweeper, permanent-failure state.
- Entitlements with hashed 256-bit tokens and 7-day expiry; secure download endpoint `?ez37_access=`.
- Combined customer email (confirmation + download link + attachment ≤ 5 MB) and internal sale notice, each sent at most once.
- Placeholder brochure in `sample-resources/` for development.

## [0.4.0] — Phase 5: Payment status

### Added
- `[ez37_payment_status]`: read-only success page status with polling (`assets/payment-status.js`).
- `GET /wp-json/ez37/v1/payment-status` (rate-limited, minimal data, no-store).
- `[ez37_payment_cancelled]`: cancel page with "Try again" link.
- Hourly reconciliation of unconfirmed transactions via the Stripe API (`ez37_payments_reconcile`).
- Privacy headers on pages with `?session_id=`; deactivation unschedules jobs.

## [0.3.0] — Phase 4: Webhooks

### Added
- `POST /wp-json/ez37/v1/stripe/webhook` with signature + timestamp verification (stripe-php SDK).
- Event processing for `checkout.session.completed`, `async_payment_succeeded`, `async_payment_failed`, `expired`.
- Idempotent event log (`ez37_stripe_events`), forward-only atomic transitions, review flag on mismatches.
- Action `ez37_payments_transaction_paid` (fired once per paid transaction) for Phase 6 fulfilment.

### Changed
- Checkout uses `allowed_payment_method_types` (Stripe API 2026-10-28 rejects `payment_method_types`).

## [0.2.0] — 2026-11-13 — Phase 3: Stripe Checkout

### Added
- Offering catalogue (`config/offerings.php`) with validation; first test offering
  "Leadership Coaching" at $1.00 (test only).
- `StripeGateway` using the official `stripe/stripe-php` SDK.
- Checkout start: `[ez37_checkout]` form → `admin-post.php` handler (Origin + nonce + honeypot +
  rate limits + input validation) → pending transaction → Stripe Checkout Session → 303 redirect.
- Stripe Price verification against config before every checkout (cached 10 minutes).
- Configurable return pages (`EZ37_PAYMENTS_SUCCESS_PATH`, `EZ37_PAYMENTS_CANCEL_PATH`).
- Admin notices for checkout switched off, invalid offerings, and offerings without a Price ID.

## [0.1.0] — 2026-10-06 — Phase 2: Foundation

### Added
- Plugin bootstrap with PHP version and dependency checks (admin notice instead of fatal error).
- `Config`: settings from `wp-config.php` constants or environment variables; strict test/live
  key separation; checkout master switch (off by default); secrets hidden from debug output.
- Admin notice listing configuration errors and warnings (setting names only, never values).
- `Logger`: redacts Stripe keys, webhook secrets, client secrets, signature headers and
  sensitive context keys; masks email addresses; prevents log injection.
- Database schema v1.0.0: `ez37_transactions`, `ez37_stripe_events`, `ez37_entitlements`,
  `ez37_email_log`, created/upgraded with `dbDelta()`.
- `PaymentStatus` and `FulfilmentStatus` state machines (forward-only transitions).
- `ez37_view_payments` capability granted to administrators on activation.
- Uninstall keeps payment tables unless `EZ37_PAYMENTS_ALLOW_DATA_DELETION` is `true`.
- Tooling: PHPUnit + Brain Monkey unit tests, PHPCS (WordPress standards), PHPStan level 6.
- Local environment (DDEV) setup script and release build script.
