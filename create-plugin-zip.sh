#!/usr/bin/env bash
# Build a WordPress-installable zip: one top-level folder (adflipr-for-woocommerce/) for Plugins → Upload.
# Run from wordpress/: ./create-plugin-zip.sh
# Requires: rsync, zip, prior `npm run build`, and src/ + package.json (WordPress.org source policy).
# Exclusions: .distignore (see that file; keep in sync with packaging).

set -euo pipefail

if ! command -v zip >/dev/null 2>&1; then
  echo "Missing 'zip'. Install it (e.g. brew install zip) and retry."
  exit 1
fi

if ! command -v rsync >/dev/null 2>&1; then
  echo "Missing 'rsync'. Install it and retry."
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

ZIP_FILE="${SCRIPT_DIR}/adflipr-for-woocommerce.zip"
PLUGIN_SLUG="adflipr-for-woocommerce"
DISTIGNORE="${SCRIPT_DIR}/.distignore"

if [ ! -d build ]; then
  echo "Missing build/. Run: npm run build  (or ./build-and-package.sh)"
  exit 1
fi

if [ ! -f build/asset-manifest.json ]; then
  echo "Missing build/asset-manifest.json. Run: npm run build  (content-hashed assets required)"
  exit 1
fi

for required in adflipr.php readme.txt includes src package.json webpack.config.js; do
  if [ ! -e "$required" ]; then
    echo "Missing required path: $required"
    exit 1
  fi
done

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

rsync_excludes=(--exclude "$PLUGIN_SLUG/" --exclude 'adflipr-for-woocommerce.zip' --exclude 'adflipr.zip' --exclude '.distignore')
if [ -f "$DISTIGNORE" ]; then
  while IFS= read -r line || [ -n "$line" ]; do
    line="${line%%#*}"
    line="$(echo "$line" | tr -d '[:space:]')"
    [ -z "$line" ] && continue
    rsync_excludes+=(--exclude "$line")
  done < "$DISTIGNORE"
fi

rsync -a "${rsync_excludes[@]}" ./ "$STAGE/$PLUGIN_SLUG/"

find "$STAGE" -name '.DS_Store' -type f -delete 2>/dev/null || true

rm -f "$ZIP_FILE"
( cd "$STAGE" && zip -rq "$ZIP_FILE" "$PLUGIN_SLUG" )

echo "Created: $ZIP_FILE"
echo "Install folder: $PLUGIN_SLUG/"
