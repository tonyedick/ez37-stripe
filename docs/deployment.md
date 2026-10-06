# Deployment

The overall strategy (local → staging → production, go-live and rollback) is in
[phase-1-analysis.md §11](phase-1-analysis.md#11-deployment-strategy). The full production
checklist is written in Phase 11. This document covers what exists today.

## Environments

| Environment | Stripe | Webhooks | Email | Who |
|---|---|---|---|---|
| Local (DDEV) | Sandbox keys | Stripe CLI `listen` | Mailpit (captured) | Developer |
| Staging | Sandbox keys | Registered sandbox endpoint | Real provider, test inboxes | Developer |
| Production | Live keys (added by you/client, never shared) | Registered live endpoint | Real provider | Developer, with client approval |

## Local environment

```bash
bin/setup-local.sh
```

Creates `https://ez37.ddev.site` from scratch (no production data): WordPress, Elementor,
`/%postname%/` permalinks, search engines discouraged, and this plugin symlinked and activated.
Pin versions to match production with `WP_VERSION=… ELEMENTOR_VERSION=… bin/setup-local.sh`.

Local Stripe sandbox settings go in `.ddev/.env` (git-ignored; DDEV loads it into the web
container), then `ddev restart`.

**Phase 2 local acceptance check** (after `bin/setup-local.sh`):

```bash
ddev wp plugin list --name=ez37-payments           # status: active
ddev wp db query "SHOW TABLES LIKE '%ez37_%'"     # 4 tables
ddev wp option get ez37_payments_db_version         # 1.0.0
ddev wp db query "SHOW CREATE TABLE wp_ez37_transactions\G"
ddev wp plugin deactivate ez37-payments && ddev wp plugin activate ez37-payments   # no errors, no duplicate tables
```

## Release build

```bash
bin/build-release.sh      # → build/ez37-payments-<version>.zip
```

The zip contains runtime code plus production Composer dependencies only (no tests, tooling
config, docs or `.env` files). The script aborts if anything resembling a Stripe secret is found.

## Install, deactivate, uninstall behaviour (v0.1.0)

| Action | Effect |
|---|---|
| Activate | Creates/upgrades the four tables; stores schema version; grants `ez37_view_payments` to administrators. Refuses network-wide activation on multisite. |
| Plugin update | Schema upgraded automatically on the next request if `DB_VERSION` changed. |
| Deactivate | No data changes. |
| Delete (uninstall) | Removes the capability. **Tables kept** unless `EZ37_PAYMENTS_ALLOW_DATA_DELETION` is `true`. |

## Configuration on staging/production

Add constants to `wp-config.php` **above** the "That's all, stop editing!" line. Example for
staging (values are placeholders):

```php
define( 'EZ37_STRIPE_MODE', 'test' );
define( 'EZ37_STRIPE_TEST_SECRET_KEY', '…' );       // from the Stripe sandbox
define( 'EZ37_STRIPE_TEST_WEBHOOK_SECRET', '…' );   // from the staging webhook endpoint
define( 'EZ37_PAYMENTS_CHECKOUT_ENABLED', false );  // enable only after testing
```

Never commit `wp-config.php`. Never put live keys on staging.
