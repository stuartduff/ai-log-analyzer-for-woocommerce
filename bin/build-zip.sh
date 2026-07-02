#!/usr/bin/env bash
#
# Build a production-ready plugin zip in /dist.
#
# Steps:
#   1. Clean /dist.
#   2. Build JS/CSS assets (webpack production mode).
#   3. Stage files to a temp dir, excluding everything in .distignore.
#   4. Zip the staged folder into /dist/<slug>.zip.

set -euo pipefail

SLUG="ai-log-analyzer-for-woocommerce"
PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="${PLUGIN_ROOT}/dist"
STAGE_DIR="$(mktemp -d -t "${SLUG}-build.XXXXXX")"

cleanup() {
	rm -rf "${STAGE_DIR}"
}
trap cleanup EXIT

cd "${PLUGIN_ROOT}"

echo "→ Cleaning ${DIST_DIR}"
rm -rf "${DIST_DIR}"
mkdir -p "${DIST_DIR}"

echo "→ Building assets (wp-scripts build)"
npm run build --silent

echo "→ Staging plugin files"
mkdir -p "${STAGE_DIR}/${SLUG}"
rsync -a \
	--exclude-from="${PLUGIN_ROOT}/.distignore" \
	--exclude=".git" \
	--exclude=".github" \
	"${PLUGIN_ROOT}/" "${STAGE_DIR}/${SLUG}/"

echo "→ Creating ${DIST_DIR}/${SLUG}.zip"
( cd "${STAGE_DIR}" && zip -rq "${DIST_DIR}/${SLUG}.zip" "${SLUG}" )

SIZE="$(du -h "${DIST_DIR}/${SLUG}.zip" | cut -f1)"
echo "✓ Built ${DIST_DIR}/${SLUG}.zip (${SIZE})"
