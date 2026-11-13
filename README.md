# EZ37 Payments

Custom Stripe Checkout payment module for the EZ37 Solutions WordPress site.
WordPress plugin, no WooCommerce. Elementor is the presentation layer only.

> **Status: Phase 2 (Foundation) complete — v0.1.0.**
> The plugin activates, creates its database tables and validates its configuration.
> It does **not** take payments yet. Checkout arrives in Phase 3.

## Repository layout

```
ez37-stripe/
├── .ddev/config.yaml       Local environment (DDEV)
├── bin/
│   ├── setup-local.sh      Builds a fresh local WordPress site (no production data)
│   └── build-release.sh    Builds the deployable plugin zip
├── docs/                   Project documentation (not deployed)
├── ez37-payments/          The WordPress plugin — the only thing deployed
├── .env.example            Configuration variable names (no values)
└── CHANGELOG.md
```

## Quick start (local development)

Requirements: [DDEV](https://ddev.readthedocs.io/) with a Docker provider, Git.

```bash
bin/setup-local.sh          # creates https://ez37.ddev.site with Elementor + this plugin
```

Then put **Stripe sandbox** settings in `.ddev/.env` (git-ignored — see `.env.example`
for names) and run `ddev restart`. Never use live keys locally.

Run the quality checks:

```bash
cd ez37-payments
composer install
composer test       # PHPUnit unit tests
composer lint       # WordPress Coding Standards
composer analyse    # PHPStan level 6
composer check      # all three
```

## Configuration

All settings are PHP constants in `wp-config.php` (preferred on staging/production)
or environment variables. Secrets are never stored in the WordPress database or in Git.
See [docs/security.md](docs/security.md#configuration-and-secrets).

| Setting | Purpose | Default |
|---|---|---|
| `EZ37_STRIPE_MODE` | `test` or `live` | `test` |
| `EZ37_STRIPE_TEST_SECRET_KEY` / `EZ37_STRIPE_TEST_WEBHOOK_SECRET` | Sandbox credentials | — |
| `EZ37_STRIPE_LIVE_SECRET_KEY` / `EZ37_STRIPE_LIVE_WEBHOOK_SECRET` | Live credentials (production only) | — |
| `EZ37_PAYMENTS_CHECKOUT_ENABLED` | Master switch for new checkouts | off |
| `EZ37_PAYMENTS_LOG_LEVEL` | `debug`, `info`, `warning`, `error` | `info` |
| `EZ37_PAYMENTS_ADMIN_EMAIL` | Internal sale notifications (Phase 7) | — |

## Documentation

| Document | Contents |
|---|---|
| [phase-1-analysis.md](docs/phase-1-analysis.md) | Approved design, decisions D1–D12, full test matrix |
| [requirements.md](docs/requirements.md) | Business and technical requirements, confirmed decisions |
| [architecture.md](docs/architecture.md) | Components — built vs planned |
| [database.md](docs/database.md) | Tables, columns, constraints, state machines |
| [security.md](docs/security.md) | Secrets handling, logging, implemented controls |
| [testing.md](docs/testing.md) | Test approach and results per phase |
| [deployment.md](docs/deployment.md) | Environments, release build, install/uninstall behaviour |
| [payment-flow.md](docs/payment-flow.md) | Checkout, return pages, reconciliation |
| [webhook-events.md](docs/webhook-events.md) | Stripe events handled and how |
| [fulfilment.md](docs/fulfilment.md) | Resources, entitlements, emails, downloads, admin actions |
| [elementor.md](docs/elementor.md) | Buttons, price shortcode, pages, styling |
| [troubleshooting.md](docs/troubleshooting.md) | Known issues and fixes |

## Confidentiality

Client project under NDA. This repository must not contain production credentials,
production data or customer information.
