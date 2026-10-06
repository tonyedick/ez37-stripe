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
| _pending_ | 2 | Developer machine, `composer check` | **To do:** run the real PHPUnit, PHPCS and PHPStan locally, commit `composer.lock`, record the result here. |

## Not yet tested (by design, later phases)

- Real `dbDelta()` against MariaDB/MySQL (Phase 2 local check via `bin/setup-local.sh`; automated in Phase 5 integration tests).
- Everything involving Stripe (Phases 3–4 onward).
