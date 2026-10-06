#!/usr/bin/env bash
#
# Builds a clean, deployable plugin zip:
#   build/ez37-payments-<version>.zip
#
# Contains runtime code and production Composer dependencies only.
# Excludes tests, tooling configuration, docs and any local files.

set -euo pipefail

cd "$(dirname "$0")/.."

PLUGIN_DIR="ez37-payments"
VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "${PLUGIN_DIR}/ez37-payments.php" | head -n 1 | tr -d '\r')"

if [ -z "${VERSION}" ]; then
	echo "Could not read plugin version from ${PLUGIN_DIR}/ez37-payments.php" >&2
	exit 1
fi

BUILD_ROOT="build"
STAGE="${BUILD_ROOT}/${PLUGIN_DIR}"
ZIP="${BUILD_ROOT}/${PLUGIN_DIR}-${VERSION}.zip"

echo "==> Building ${PLUGIN_DIR} ${VERSION}"
rm -rf "${STAGE}" "${ZIP}"
mkdir -p "${STAGE}"

rsync -a \
	--exclude 'vendor/' \
	--exclude 'tests/' \
	--exclude '.phpunit.cache/' \
	--exclude '.phpstan-cache/' \
	--exclude '.phpcs-cache' \
	--exclude 'phpunit.xml.dist' \
	--exclude 'phpcs.xml.dist' \
	--exclude 'phpstan.neon.dist' \
	--exclude '.env*' \
	--exclude '*.log' \
	"${PLUGIN_DIR}/" "${STAGE}/"

echo "==> Installing production dependencies"
composer install --working-dir="${STAGE}" --no-dev --optimize-autoloader --classmap-authoritative --no-interaction

# composer.json/lock are not needed at runtime.
rm -f "${STAGE}/composer.json" "${STAGE}/composer.lock"

echo "==> Checking for secrets"
if grep -rEq '(sk|rk)_(live|test)_[A-Za-z0-9]{8,}|whsec_[A-Za-z0-9]{8,}' "${STAGE}" --exclude-dir=vendor; then
	echo "ABORT: something that looks like a Stripe secret was found in the build." >&2
	exit 1
fi

echo "==> Creating ${ZIP}"
(cd "${BUILD_ROOT}" && zip -qr "$(basename "${ZIP}")" "${PLUGIN_DIR}")

echo "Done: ${ZIP}"
