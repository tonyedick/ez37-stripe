# Testing

The complete test matrix (CHK, PAY, WH, SEC, FUL, TAX, UX) is in
[phase-1-analysis.md §9](phase-1-analysis.md#9-testing-strategy). It is run in full in Phase 10
using Stripe test mode. This file records the automated tests that exist and their results.

## Test layers

| Layer | Tooling | Location | Status |
|---|---|---|---|
| Unit | PHPUnit 10 + Brain Monkey (WordPress functions mocked) | `ez37-payments/tests/Unit` | **In use** |
| Integration | WordPress test suite + real database | `ez37-payments/tests/Integration` | From Phase 5 |
| Sandbox end-to-end | Stripe CLI (`listen`, `trigger`, `events resend`) | Manual scripts in `docs/` | From Phase 3/4 |
| Static analysis | PHPCS (WordPress standards), PHPStan level 6 | `phpcs.xml.dist`, `phpstan.neon.dist` | **In use** |

Run everything from `ez37-payments/`:

```bash
composer install
composer check      # = lint + analyse + test
```

## Phase 2 unit tests

| File | Tests | Covers |
|---|---|---|
| `ConfigTest.php` | 17 | Default test mode; checkout off by default; valid config; restricted keys; invalid/case-insensitive mode; live mode rejects test keys; test mode never returns live keys (warning only); publishable key rejected; webhook secret missing/malformed; truthy switch values; trimming; invalid log level/email; unknown settings ignored; secrets absent from debug output and messages; env-variable loading |
| `LoggerTest.php` | 11 | Level filtering; prefix; redaction of `sk_`/`rk_`/`whsec_`, client secrets and signature headers; ordinary Stripe IDs kept; email masking; recursive sensitive-key redaction; exception summarising; no object dumps; log-injection; truncation |
| `StatusTest.php` | 13 | Payment happy/delayed paths; `paid` is final; nothing returns to `pending`; no self-transitions; `expired` cannot become `paid`; `allowed_from()` sets; enum values fit columns and match schema defaults; fulfilment lifecycle; completed cannot re-run; fulfilment cannot start before payment |
| `SchemaTest.php` | 10 | Exactly four prefixed tables; dbDelta formatting rules; all idempotency unique keys; ASCII case-sensitive Stripe ID columns; utf8mb4 prefix indexes; required data-model columns; status defaults; unsigned integer amounts; `install()` calls `dbDelta()` with 4 statements and records the version; `maybe_upgrade()` skips when current |

Test fixtures never contain real or realistic secrets: key-shaped strings are built at runtime
from a prefix plus `FAKE…`.

## Results log

| Date | Phase | Environment | Result |
|---|---|---|---|
| 2026-10-06 | 2 | Claude build sandbox, PHP 8.3.6 | `php -l` clean on all files. **51 tests, 210 assertions, all passing** — run through a minimal PHPUnit/Brain Monkey stand-in because Packagist was not reachable from the sandbox. A mutation check (three deliberately introduced bugs) was caught by the suite. |
| 2026-11-13 | 2 | Developer Mac, PHP 8.4.21, PHPUnit 10.5.66 | **51 tests, 221 assertions, all passing** (real PHPUnit). First PHPCS/PHPStan run reported formatting issues, missing doc comments and false positives (PHPCompatibility 9 does not understand enums; PHPStan treats the PHP-version guard and the uninstall opt-in constant as constant). Fixed in `02cb688`; `composer.lock` committed. |
| 2026-11-13 | 2 | Local DDEV site (WordPress 7.1.4, Elementor 4.4.0, MariaDB 10.11, PHP 8.2) | Plugin activates; config notice correctly lists missing `EZ37_STRIPE_TEST_SECRET_KEY` and `EZ37_STRIPE_TEST_WEBHOOK_SECRET` and keeps checkout disabled; 4 `ez37_` tables created by `dbDelta()`; `ez37_payments_db_version` = `1.0.0`. Setup script fixed for non-standard router ports and a silent exit (pipefail + SIGPIPE). |
| 2026-11-13 | 3 | Developer Mac, PHP 8.4.21, PHPUnit 10.5.66, stripe-php 23.0.0 | `composer check`: PHPCS clean, PHPStan level 6 clean, **97 tests, 375 assertions, all passing**. |
| 2026-11-13 | 3 | Local DDEV site + Stripe sandbox (API `2026-10-28.endive`) | **End-to-end checkout works.** Form → Price check (`GET /v1/prices` 200) → session created (`POST /v1/checkout/sessions` 200: `mode=payment`, `amount_total=100`, `currency=usd`, `adaptive_pricing.enabled=false`, `allowed_payment_method_types=[card]`, `customer_creation=always`, `invoice_creation.enabled=false`, `automatic_tax.enabled=false`, `client_reference_id` = transaction public ID) → paid with test card 4242 → redirected to `/payment-complete/`. Defects found and fixed during the run: (1) Price from a different sandbox → checkout correctly blocked with `resource_missing` (configuration error, not code); (2) `payment_method_types` rejected by API `2026-10-28.endive` → replaced with `allowed_payment_method_types`. Transaction stays `pending` by design until Phase 4 webhooks. |
| 2026-11-13 | 4 | Developer Mac, PHP 8.4.21, PHPUnit 10.5.66, stripe-php 23.0.0 | `composer check`: PHPCS clean, PHPStan clean, **130 tests, 412 assertions, all passing** (incl. real SDK signature verification: valid, wrong secret, tampered body, missing/garbage header, 10-minute-old timestamp, invalid JSON, oversized body). |
| 2026-11-13 | 4 | Local DDEV + Stripe CLI `listen` (sandbox) | **End-to-end webhook works (WH-01, PAY-01).** Test payment → `checkout.session.completed` forwarded → endpoint answered `200` → transaction `pending → paid`, `fulfilment_status = pending`, `amount_paid = 100`, `paid_at` set (UTC). A Phase 3 test payment made before the listener was running stays `pending` (event never delivered locally) — see reconciliation note in Phase 5 plan. |

## Not yet tested (by design, later phases)

- Automated integration tests against a real database (Phase 5). Real `dbDelta()` was verified manually on the local DDEV site.
- Everything involving Stripe (Phases 3–4 onward).
