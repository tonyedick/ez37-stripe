# Changelog

All notable changes to the EZ37 Payments plugin.

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
