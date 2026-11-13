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

## Pages and scheduled job (Phase 5)

Create (or keep) three pages:

| Page | Slug | Content |
|---|---|---|
| Checkout | `checkout` | `[ez37_checkout]` |
| Payment complete | `payment-complete` | `[ez37_payment_status]` |
| Payment cancelled | `payment-cancelled` | `[ez37_payment_cancelled]` |

Exclude all three from page caching. The reconciliation job runs on WP-Cron (`ez37_payments_reconcile`,
hourly). WP-Cron only runs when the site gets traffic; on production, prefer a real server cron that
calls `wp cron event run --due-now` (or `wp-cron.php`) every 5–15 minutes and set
`DISABLE_WP_CRON` accordingly. Check with `wp cron event list | grep ez37`.

## Private resources and email (Phases 6–7)

- Create a folder **outside the web root** (e.g. one level above `public_html`), upload the resource
  files into it keeping the paths from `config/resources.php`, and set
  `define( 'EZ37_PAYMENTS_PRIVATE_DIR', '/absolute/path/to/folder' );`.
- Set `EZ37_PAYMENTS_ADMIN_EMAIL` for sale notices.
- Make sure the site sends email reliably (SMTP plugin/transactional provider with SPF/DKIM/DMARC).
- The fulfilment job and its hourly sweeper run on WP-Cron like reconciliation; see above.

## Elementor pages and admin screen (Phase 8)

- Build the programme pages in Elementor following [elementor.md](elementor.md); set brand colours via
  the `--ez37-*` CSS variables.
- After deploying a new plugin version, purge any CDN/page cache so the new `ez37-payments.css`
  (versioned by plugin version) is served.
- Check that administrators see **Payments** in wp-admin and that other roles do not.

## Security settings from the Phase 9 audit

- **Behind a CDN or reverse proxy** (Cloudflare, load balancer, Nginx proxy): tell the plugin the real
  visitor IP, otherwise all visitors share one rate limit. Use the header your proxy sets, in a small
  must-use plugin, e.g. `wp-content/mu-plugins/ez37-client-ip.php`:

  ```php
  <?php
  add_filter( 'ez37_payments_client_ip', function ( $ip ) {
  	// Cloudflare example. Only trust this header if the origin accepts traffic from Cloudflare only.
  	return isset( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : $ip;
  } );
  ```
- Exclude from page/CDN caching: `/checkout/`, `/payment-complete/`, `/payment-cancelled/`, and any URL
  whose query string contains `ez37_access`.
- The site address must be https before switching to live mode (checkout is blocked otherwise).
- The private folder must be outside the web server's document root (the plugin can only detect a
  folder inside the WordPress folder).

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
