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
| `StatusTest.php` | 13 | Payment happy/delayed paths; `paid` is final; nothing returns to `pending`; no self-transitions; `expired` cannot become `paid`; `allowed_from()` sets; enum values fit columns and match schema defaults; fulfilment lifecycle; completed reopens only via administrator reset; fulfilment cannot start before payment |
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
| 2026-11-13 | 5 | Developer Mac, PHP 8.4.21, PHPUnit 10.5.66 | `composer check`: PHPCS clean, PHPStan clean, **150 tests, 447 assertions, all passing**. |
| 2026-11-13 | 5 | Local DDEV + Stripe CLI (sandbox) | **UX-01:** payment → success page shows "Payment confirmed" with offering, amount and masked email. **UX-02 / PAY-05:** back arrow on Stripe → cancel page "Nothing was charged" with "Try again" link. **Reconciliation (WH-07 backstop):** `wp cron event run ez37_payments_reconcile` found the Phase 3 transaction whose webhook was never delivered locally, fetched the session from Stripe and marked it `paid` (`{"processed":1}`). |
| 2026-11-13 | 6–7 | Developer Mac, PHP 8.4.21, PHPUnit 10.5.66 | `composer check`: PHPCS clean, PHPStan clean, all unit tests passing (fulfilment, resources/access, mailer added). |
| 2026-11-13 | 9 | Claude build sandbox, PHP 8.3.6, PHPUnit 10.5.66 | Security audit fixes SA-01…SA-07. PHPCS clean, PHPStan level 6 clean, **214 tests, 579 assertions, all passing** (new: `HardeningTest` — log redaction of tokens, private folder inside/outside WordPress incl. shared-prefix sibling, client IP default/filter/invalid; live checkout refused on http; email reserved before links, no links issued while another job sends, lock > SMTP timeout). |
| 2026-11-13 | 8 | Local DDEV + Stripe CLI + Mailpit (developer) | **Phase 8 manual test script, steps 1–9: all passed** — checkout styling and `--ez37-accent` override; `[ez37_offering_price]`; Elementor button link; double-click gives one transaction (PAY-06); Payments list, search and filters; Resend sends a new email and the old link is refused (FUL-04); Revoke refuses the link and hides Resend/Retry (SEC-10); Retry after a failed delivery (FUL-03); non-administrators refused. |
| 2026-11-13 | 8 | Claude build sandbox, PHP 8.3.6, PHPUnit 10.5.66 (developer's `vendor/`) | PHPCS clean, PHPStan level 6 clean, **203 tests, 562 assertions, all passing** (new: admin action availability, capability/nonce/state checks, retry/resend/revoke, price shortcode, admin helpers). Admin templates smoke-rendered with sample data. |
| 2026-11-13 | 6–7 | Local DDEV + Stripe CLI + Mailpit | **FUL-01, FUL-13, UX-04, UX-05:** test payment → webhook → background fulfilment → customer email "Payment confirmed: Leadership Coaching" (name, programme, $1.00 USD, reference, Download button, 7-day expiry, PDF attached) and admin email "New sale … [TEST]" to the admin address → Download button served `brochure-placeholder.pdf`. Observed: dates shown in the site timezone (local site is UTC); sender is WordPress default (`wordpress@…`), to be configured on staging/production. |

## Phase 10 — full test matrix

The formal matrix with method, expected behaviour (as built) and results is in
[test-matrix.md](test-matrix.md). The automated part runs with:

```bash
python3 bin/phase10-local.py      # from the repository root; DDEV running; test mode only
```

It writes `build/phase10-results.md`. The manual part is the "Manual test session" (M1–M14) at the
end of the matrix.

### Phase 10 results (v0.7.0, Stripe test mode, local DDEV)

| Date | Part | Result |
|---|---|---|
| 2026-11-17 | Automated (`bin/phase10-local.py`) | **61 passed, 0 failed, 0 skipped, 1 note.** All webhook, checkout-validation, payment-state, concurrency, fulfilment, download, access-control, status-endpoint, secrets and tax checks passed. SEC-14d (note): download tokens appear in web-server request logs, as every web server logs URLs; production logs must stay private or strip query strings (Phase 11 checklist). An earlier run reported SEC-14c failing; the cause was the web server's own request lines for the script's download tests, and the check was corrected to look only at what the plugin writes. |
| 2026-11-17 | Manual session M1–M14 (developer, Stripe CLI `listen`, Mailpit) | **All passed**, as reported by the developer. M1: payment confirmed, customer email with Download button and PDF attached, download works, webhook answered 200. |
| 2026-11-17 | Unit + static checks | 214 tests, 579 assertions, PHPCS and PHPStan clean (unchanged since Phase 9). |

Findings recorded during the manual session (no code defects):

- **Webhook secret for local testing.** The Stripe CLI signs forwarded events with its own `whsec_…` secret
  (`stripe listen --print-secret`), which must be the value of `EZ37_STRIPE_TEST_WEBHOOK_SECRET` in
  `.ddev/.env`. With a different secret the plugin correctly answers 400 and the success page stays on
  "Confirming your payment…" (this is the designed behaviour, not a fault). The CLI must also be logged
  into the same Stripe sandbox as the site's test key (`stripe config --list`).
- **Debug output.** DDEV enables `WP_DEBUG`, so notices from Elementor, the theme and the MCP adapter print
  on pages locally. Production must set `WP_DEBUG` and `WP_DEBUG_DISPLAY` to false (Phase 11 checklist).
- **Sender address.** Emails come from the WordPress default `wordpress@<site>`; the production From name,
  address and SPF/DKIM/DMARC are Phase 11 items.

## Phase 8 manual test script (local DDEV)

1. **Styling (UX):** open `/checkout/?offering=leadership-coaching` — card layout, test-mode banner,
   full-width button. Add to Additional CSS `:root { --ez37-accent: #b00020; }` → button turns red.
2. **Price shortcode:** add `[ez37_offering_price offering="leadership-coaching"]` to any page →
   "$1.00 USD"; with an unknown key → nothing.
3. **Elementor button:** Button widget linking to `/checkout/?offering=leadership-coaching` opens the form.
4. **Double click (PAY-06):** click "Continue to secure payment" twice quickly → one transaction.
5. **Payments list:** wp-admin → Payments shows the earlier test payments; search by email and by the
   10-character reference from an email; filters work.
6. **Resend (FUL-04):** on a delivered payment, "Resend email with a new link" → confirm → new email in
   Mailpit; the *old* link now says "not valid", the new one downloads.
7. **Revoke (SEC-10):** "Revoke access" → the new link now says "not valid"; Resend/Retry are no longer offered.
8. **Retry (FUL-03):** pick a payment with failed delivery (or temporarily rename the brochure file,
   run `ddev wp cron event run ez37_payments_fulfilment_sweep`, restore the file) → "Retry delivery now" → email arrives.
9. **Access control:** log in as an Editor (or log out) and open `wp-admin/admin.php?page=ez37-payments`
   → refused.

## Not yet tested (by design, later phases)

- Automated integration tests against a real database (Phase 5). Real `dbDelta()` was verified manually on the local DDEV site.
- Everything involving Stripe (Phases 3–4 onward).
