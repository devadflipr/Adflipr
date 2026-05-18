#!/bin/bash
# Bump plugin semver: includes/adflipr-version.php (canonical), adflipr.php header Version:,
# readme.txt Stable tag, package.json.
#
# Usage (from wordpress/):
#   ./scripts/bump-plugin-version.sh --patch
#   ./scripts/bump-plugin-version.sh --set 1.1.0

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$SCRIPT_DIR"

VERSION_PHP="$SCRIPT_DIR/includes/adflipr-version.php"
ADFLIPR_PHP="$SCRIPT_DIR/adflipr.php"
README_TXT="$SCRIPT_DIR/readme.txt"
PKG_JSON="$SCRIPT_DIR/package.json"

get_version_from_version_php() {
  sed -n "s/^[[:space:]]*const ADFLIPR_PLUGIN_VERSION = '\\([^']*\\)';/\\1/p" "$VERSION_PHP" | head -1
}

bump_patch() {
  local v="$1"
  local parts=()
  IFS='.' read -ra parts <<< "$v"
  while [ ${#parts[@]} -lt 3 ]; do
    parts+=(0)
  done
  local major="${parts[0]}"
  local minor="${parts[1]}"
  local patch="${parts[2]}"
  patch="${patch//[^0-9]/}"
  if [ -z "$patch" ]; then patch=0; fi
  patch=$((10#$patch + 1))
  echo "${major}.${minor}.${patch}"
}

MODE=""
NEW_VER=""

while [ $# -gt 0 ]; do
  case "$1" in
    --patch) MODE=patch ;;
    --set)
      MODE=set
      NEW_VER="$2"
      shift
      ;;
    -h|--help)
      cat <<'EOF'
Usage: ./scripts/bump-plugin-version.sh --patch
       ./scripts/bump-plugin-version.sh --set X.Y.Z

Updates: includes/adflipr-version.php, adflipr.php (Plugin header Version:), readme.txt Stable tag, package.json.
EOF
      exit 0
      ;;
    *)
      echo "Unknown option: $1"
      exit 1
      ;;
  esac
  shift
done

if [ -z "$MODE" ]; then
  echo "Specify --patch or --set X.Y.Z (use --help)"
  exit 1
fi

CURRENT="$(get_version_from_version_php)"
if [ -z "$CURRENT" ]; then
  echo "Could not read ADFLIPR_PLUGIN_VERSION from $VERSION_PHP"
  exit 1
fi

if [ "$MODE" = patch ]; then
  NEW_VER="$(bump_patch "$CURRENT")"
elif ! [[ "$NEW_VER" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "--set requires semver X.Y.Z (e.g. 1.0.2)"
  exit 1
fi

echo "Plugin version: $CURRENT -> $NEW_VER"
export AFL_VERSION="$NEW_VER"

perl -i -pe 's/(const ADFLIPR_PLUGIN_VERSION = \x27)[^\x27]+(\x27;)/$1 . $ENV{AFL_VERSION} . $2/e' "$VERSION_PHP"

perl -i -pe 's/^([\040\t]*\*[\040\t]*Version:[\040\t]*)[\d.]+$/$1 . $ENV{AFL_VERSION}/e' "$ADFLIPR_PHP"

perl -i -pe "s/^Stable tag:[[:space:]]*.*/Stable tag: $NEW_VER/" "$README_TXT"

if command -v node >/dev/null 2>&1; then
  node -e "
const fs = require('fs');
const p = '$PKG_JSON';
const j = JSON.parse(fs.readFileSync(p, 'utf8'));
j.version = '$NEW_VER';
fs.writeFileSync(p, JSON.stringify(j, null, 2) + '\n');
"
else
  perl -i -pe 's/"version":\s*"[^"]*"/"version": "'"$NEW_VER"'"/' "$PKG_JSON"
fi

echo "Updated: $VERSION_PHP, $ADFLIPR_PHP, $README_TXT, $PKG_JSON"
