# Troubleshooting

Known problems and fixes. Add an entry whenever an issue is diagnosed.

## Admin notice: "EZ37 Payments is missing its dependencies (vendor/autoload.php)"

The plugin folder has no `vendor/` directory.
- **Development:** run `composer install` in `ez37-payments/`.
- **Staging/production:** install the zip from `bin/build-release.sh`, not a copy of the Git folder.

## Admin notice: "EZ37 Payments requires PHP 8.1 or later"

The server runs an older PHP version. Upgrade PHP in the hosting control panel. The plugin stays
inactive (no errors) until then.

## Admin notice: "EZ37 Payments configuration (test mode): … is not set"

A required setting is missing. Add it as a constant in `wp-config.php` (or to `.ddev/.env`
locally, then `ddev restart`). Checkout stays disabled until every **error** is fixed.
Warnings (for example "A live secret key is configured while in test mode") do not block
checkout but should be fixed.

## "… does not look like a test-mode secret or restricted key"

The key in the slot for the active mode has the wrong prefix — e.g. a live key in
`EZ37_STRIPE_TEST_SECRET_KEY`, or a publishable `pk_` key instead of a secret key. Copy the
**secret** or **restricted** key for the right mode from the Stripe Dashboard.

## Settings in `.ddev/.env` are not picked up

Run `ddev restart` after editing. Check from inside the container:
`ddev exec printenv EZ37_STRIPE_MODE`. Note that a constant defined in `wp-config.php`
overrides an environment variable of the same name.

## Tables were not created on activation

Check the PHP error log for `dbDelta` errors and that the database user can `CREATE`/`ALTER`.
Re-run safely with `ddev wp eval 'EZ37\Payments\Install\Schema::install();'` — `dbDelta()` is
idempotent.
