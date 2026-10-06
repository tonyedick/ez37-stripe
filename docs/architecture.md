# Architecture

The target architecture is described in [phase-1-analysis.md §4](phase-1-analysis.md#4-proposed-architecture).
This document records **what is actually built**, and is updated at the end of every phase.

## Current state — v0.1.0 (Phase 2)

```
ez37-payments.php ── PHP/dependency checks ── activation hook ── plugins_loaded
        │                                          │                  │
        │                                          ▼                  ▼
        │                              Install\Activator::activate   Plugin::boot
        │                                 ├─ refuses network-wide      ├─ Config::from_environment()
        │                                 ├─ Schema::install()         ├─ new Logger(level)
        │                                 └─ add ez37_view_payments    ├─ Schema::maybe_upgrade()
        │                                    cap to administrators     └─ admin notice: config problems
        ▼
uninstall.php ── removes capability; drops tables ONLY if EZ37_PAYMENTS_ALLOW_DATA_DELETION === true
```

### Built

| Class | Responsibility |
|---|---|
| `EZ37\Payments\Plugin` | Composition root: creates `Config` and `Logger` once, registers hooks. No business logic. |
| `EZ37\Payments\Config` | Reads settings from constants/env; validates mode/key prefixes; exposes only active-mode credentials. |
| `EZ37\Payments\Support\Logger` | Redacting logger writing to the PHP error log. |
| `EZ37\Payments\Install\Schema` | Table definitions, `dbDelta()` install/upgrade, schema versioning, opt-in table removal. |
| `EZ37\Payments\Install\Activator` | Activation: schema + capability. |
| `EZ37\Payments\Transactions\PaymentStatus` | Payment state machine (enum). |
| `EZ37\Payments\Transactions\FulfilmentStatus` | Fulfilment state machine (enum). |

### Planned (not yet built)

| Phase | Components |
|---|---|
| 3 | `Offerings\Offering`, `Offerings\OfferingCatalogue`, `config/offerings.php`, `Stripe\StripeGateway` (adds `stripe/stripe-php`), `Checkout\CheckoutService`, `Checkout\CheckoutController` |
| 4 | `Webhooks\WebhookController`, `Webhooks\EventProcessor` |
| 5 | `Transactions\TransactionRepository`, `Frontend\StatusController`, success/cancel shortcodes |
| 6 | `Fulfilment\*`, `Resources\EntitlementRepository`, `Resources\AccessController` |
| 7 | `Email\Mailer`, templates |
| 8 | `Frontend\Shortcodes` (Elementor integration) |
| 8b | `Admin\TransactionsPage` |

## Conventions

- PHP 8.1+, `declare(strict_types=1)`, namespace `EZ37\Payments`, PSR-4 autoloading via Composer
  (`src/` maps to the namespace). File names match class names; the WordPress `class-*.php`
  file-naming sniff is disabled for this reason.
- WordPress Coding Standards otherwise apply (tabs, snake_case methods, escaping, `$wpdb->prepare()`).
- Classes are `final` unless there is a reason to extend them.
- WordPress functions are only called from classes that need them; pure logic (e.g.
  `Schema::create_table_sql()`, `Config`, `Logger`, the status enums) has no WordPress dependency
  so it can be unit-tested directly.
