#!/usr/bin/env bash
#
# Creates a fresh local WordPress site for EZ37 Payments development using DDEV.
#
# - No production data is used. The site is built from scratch.
# - The plugin in ./ez37-payments is symlinked into the local site.
# - Optional version pins (match production once known):
#     WP_VERSION=6.6.2 ELEMENTOR_VERSION=3.25.0 bin/setup-local.sh
#
# Requirements on your machine: DDEV (with Docker / OrbStack / Colima).

set -euo pipefail

cd "$(dirname "$0")/.."

WP_VERSION="${WP_VERSION:-latest}"
ELEMENTOR_VERSION="${ELEMENTOR_VERSION:-}"

# DDEV expects the docroot to exist before it starts.
mkdir -p wordpress

echo "==> Starting DDEV"
ddev start

# The URL depends on the router ports configured on this machine
# (e.g. https://ez37.ddev.site:8443), so ask DDEV rather than assuming.
SITE_URL="$(ddev exec printenv DDEV_PRIMARY_URL | tr -d '\r')"

if [ ! -f wordpress/wp-load.php ]; then
	echo "==> Downloading WordPress (${WP_VERSION})"
	ddev wp core download --version="${WP_VERSION}"
fi

if ! ddev wp core is-installed >/dev/null 2>&1; then
	# Note: avoid `tr </dev/urandom | head`: with pipefail, tr's SIGPIPE
	# makes the pipeline fail and `set -e` silently stops the script.
	ADMIN_PASSWORD="$(openssl rand -hex 16)"
	echo "==> Installing WordPress"
	ddev wp core install \
		--url="${SITE_URL}" \
		--title="EZ37 Payments (local dev)" \
		--admin_user=devadmin \
		--admin_password="${ADMIN_PASSWORD}" \
		--admin_email=dev@example.test \
		--skip-email
	echo ""
	echo "    Local admin user: devadmin"
	echo "    Local admin password: ${ADMIN_PASSWORD}"
	echo "    (local throwaway site only — store it in your password manager if needed)"
	echo ""
fi

echo "==> Linking plugin into the local site"
ln -sfn ../../../ez37-payments wordpress/wp-content/plugins/ez37-payments

echo "==> Installing plugin PHP dependencies"
ddev exec "cd ez37-payments && composer install --no-interaction"

echo "==> Configuring WordPress"
ddev wp rewrite structure '/%postname%/' --hard
ddev wp option update blog_public 0

echo "==> Installing Elementor"
if [ -n "${ELEMENTOR_VERSION}" ]; then
	ddev wp plugin install elementor --version="${ELEMENTOR_VERSION}" --activate
else
	ddev wp plugin install elementor --activate
fi

echo "==> Activating EZ37 Payments"
ddev wp plugin activate ez37-payments

echo ""
echo "Done. Site: ${SITE_URL}   Mail catcher: ddev launch -m"
echo "Next: add Stripe SANDBOX settings to .ddev/.env (see .env.example), then 'ddev restart'."
