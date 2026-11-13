# Architecture

The target architecture is described in [phase-1-analysis.md §4](phase-1-analysis.md#4-proposed-architecture).
This document records **what is actually built**, and is updated at the end of every phase.

## Current state — v0.7.0 (Phase 9)

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
| `EZ37\Payments\Support\ClientIp` | Visitor IP for rate limiting; `REMOTE_ADDR` unless the deployer supplies the real IP via `ez37_payments_client_ip`. |
| `EZ37\Payments\Install\Schema` | Table definitions, `dbDelta()` install/upgrade, schema versioning, opt-in table removal. |
| `EZ37\Payments\Install\Activator` | Activation: schema + capability. |
| `EZ37\Payments\Transactions\PaymentStatus` | Payment state machine (enum). |
| `EZ37\Payments\Transactions\FulfilmentStatus` | Fulfilment state machine (enum). |
| `EZ37\Payments\Offerings\Offering` / `OfferingCatalogue` | Offerings from `config/offerings.php`, validated; invalid ones skipped and reported. |
| `EZ37\Payments\Stripe\StripeGateway` | The only class using the Stripe SDK (`stripe/stripe-php`): create Checkout Session, retrieve Price. Converts SDK errors to `GatewayException`. |
| `EZ37\Payments\Checkout\CheckoutInput` | Validates offering key, name, email. Ignores everything else. |
| `EZ37\Payments\Checkout\CheckoutService` | Price verification, pending transaction, Checkout Session. Never marks anything paid. |
| `EZ37\Payments\Checkout\CheckoutRequestHandler` | `admin-post.php?action=ez37_checkout`: Origin, nonce, honeypot, rate limits, then 303 to Stripe. |
| `EZ37\Payments\Checkout\RateLimiter` | Transient-based throttling (hashed keys). |
| `EZ37\Payments\Transactions\TransactionRepository` | SQL for `ez37_transactions` (create pending, attach session, mark failed). |
| `EZ37\Payments\Frontend\CheckoutShortcode` | `[ez37_checkout]` form (templates/checkout-form.php). |
| `EZ37\Payments\Webhooks\WebhookController` | `POST /wp-json/ez37/v1/stripe/webhook`: size limit, signature + timestamp verification (SDK), 200/400/413/500. |
| `EZ37\Payments\Webhooks\EventProcessor` | The only code that marks a transaction paid. Dedupe, livemode check, session/transaction cross-checks, forward-only transitions, fires `ez37_payments_transaction_paid`. |
| `EZ37\Payments\Transactions\Reconciler` | Hourly WP-Cron backstop: fetches unconfirmed sessions from Stripe and applies them through `EventProcessor::reconcile_session()`. |
| `EZ37\Payments\Frontend\StatusService` | Read-only, minimal-data status lookup by Checkout Session ID. |
| `EZ37\Payments\Frontend\StatusController` | `GET /wp-json/ez37/v1/payment-status` (rate-limited, no-store). |
| `EZ37\Payments\Frontend\ReturnPageShortcodes` | `[ez37_payment_status]`, `[ez37_payment_cancelled]`, privacy headers. |
| `EZ37\Payments\Frontend\Template` | Renders `templates/*.php`. |
| `EZ37\Payments\Fulfilment\FulfilmentService` | Queue on payment, claim/lock, grant entitlements, send emails, retry/sweep. |
| `EZ37\Payments\Resources\PaidResource` / `ResourceCatalogue` | Resources from `config/resources.php`; path-traversal-safe file resolution. |
| `EZ37\Payments\Resources\EntitlementRepository` | SQL for `ez37_entitlements` (idempotent grant, token-hash lookup, atomic access count). |
| `EZ37\Payments\Resources\AccessController` | `?ez37_access=<token>` secure download. |
| `EZ37\Payments\Email\Mailer` / `EmailRepository` | Customer and admin emails via `wp_mail()`, at most once each (`ez37_email_log`); administrator resend override. |
| `EZ37\Payments\Frontend\Styles` | Registers `assets/ez37-payments.css`; shortcodes enqueue it (filter `ez37_payments_load_styles`). |
| `EZ37\Payments\Admin\PaymentsPage` | wp-admin **Payments** screen (list, detail) and the retry / resend / revoke actions (`admin-post.php?action=ez37_payments_admin`). Loaded only in wp-admin. |

**Deviation from the Phase 1 plan:** Phases 6 (fulfilment) and 7 (email) were built together, because
the single combined customer email (D10) is the delivery itself.
| `EZ37\Payments\Webhooks\EventRepository` | SQL for `ez37_stripe_events` (`INSERT IGNORE` on the unique event ID). |

**Deviation from the Phase 1 plan:** the checkout form posts to `admin-post.php` instead of a REST
route. A plain HTML form then works without JavaScript inside any Elementor layout, and the
handler can redirect straight to Stripe (303) or back with an error code. The webhook (Phase 4)
will still be a REST route.

**Elementor integration** needs no Elementor-specific code: buttons link to `/checkout/?offering=<key>`
and pages use the shortcodes (`[ez37_checkout]`, `[ez37_offering_price]`, `[ez37_payment_status]`,
`[ez37_payment_cancelled]`) in Shortcode widgets. See [elementor.md](elementor.md). This keeps the
payment engine independent of Elementor, as required.

### Planned (not yet built)

Nothing further is planned for the plugin's feature set before launch. Phases 9–11 are the security
audit, full test matrix and deployment preparation.

## Conventions

- PHP 8.1+, `declare(strict_types=1)`, namespace `EZ37\Payments`, PSR-4 autoloading via Composer
  (`src/` maps to the namespace). File names match class names; the WordPress `class-*.php`
  file-naming sniff is disabled for this reason.
- WordPress Coding Standards otherwise apply (tabs, snake_case methods, escaping, `$wpdb->prepare()`).
- Classes are `final` unless there is a reason to extend them.
- WordPress functions are only called from classes that need them; pure logic (e.g.
  `Schema::create_table_sql()`, `Config`, `Logger`, the status enums) has no WordPress dependency
  so it can be unit-tested directly.
