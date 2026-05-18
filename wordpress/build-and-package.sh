#!/bin/bash
#
# One-shot: bump plugin semver (default), npm run build, create adflipr.zip.
# By default skips npm install; use --install for a full install.
#
# Usage (from wordpress/):
#   ./build-and-package.sh                    # patch bump + build + zip (default)
#   ./build-and-package.sh --no-bump          # build + zip without changing version
#   ./build-and-package.sh --version=1.2.0    # set exact version, then build + zip
#   ./build-and-package.sh --install
#   ./build-and-package.sh --help
#

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

RUN_NPM_INSTALL=false
# Default: bump patch each package so WordPress sees a new version on upload.
SKIP_VERSION_BUMP=false
BUMP_SET_VER=""

print_help() {
  cat <<'EOF'
Usage: ./build-and-package.sh [options]

  By default increments the patch version (includes/adflipr-version.php, adflipr.php header, readme.txt Stable tag,
  package.json), then runs wp-scripts build and create-plugin-zip.sh → adflipr.zip.

  By default does NOT run npm install (faster when node_modules already exists).
  Use --install when dependencies are missing or after package.json changes.

Options:
  --install          Run npm install before build (full / CI-style).
  --skip-install     Skip npm install (default; kept for scripts).
  --no-bump          Do not change plugin version (same semver as repo).
  --keep-version     Same as --no-bump.
  --bump-patch       Explicit patch bump (default behavior; optional).
  --version=X.Y.Z    Set plugin version explicitly instead of patch bump.
  -h, --help         Show this help

Examples:
  ./build-and-package.sh
  ./build-and-package.sh --no-bump
  ./build-and-package.sh --version=2.0.0 --install

Requires: node, npm, zip — scripts/bump-plugin-version.sh performs version edits.
EOF
}

ARGS=("$@")
i=0
while [ $i -lt ${#ARGS[@]} ]; do
  arg="${ARGS[$i]}"
  case "$arg" in
    --install) RUN_NPM_INSTALL=true ;;
    --skip-install) RUN_NPM_INSTALL=false ;;
    --no-bump|--keep-version) SKIP_VERSION_BUMP=true ;;
    --bump-patch) SKIP_VERSION_BUMP=false ;;
    --version=*)
      BUMP_SET_VER="${arg#*=}"
      ;;
    -h|--help)
      print_help
      exit 0
      ;;
    *)
      echo "Unknown option: $arg  (use --help)"
      exit 1
      ;;
  esac
  i=$((i + 1))
done

echo "=========================================="
echo "AdFlipr plugin — build & package"
echo "Directory: $SCRIPT_DIR"
echo "=========================================="

if [ -n "$BUMP_SET_VER" ]; then
  SKIP_VERSION_BUMP=false
fi

if [ "$SKIP_VERSION_BUMP" != true ]; then
  echo "------------------------------------------"
  echo "Updating plugin version"
  echo "------------------------------------------"
  if [ -n "$BUMP_SET_VER" ]; then
    "$SCRIPT_DIR/scripts/bump-plugin-version.sh" --set "$BUMP_SET_VER"
  else
    "$SCRIPT_DIR/scripts/bump-plugin-version.sh" --patch
  fi
else
  echo "------------------------------------------"
  echo "Skipping version bump (--no-bump / --keep-version)"
  echo "------------------------------------------"
fi

if ! command -v node >/dev/null 2>&1; then
  echo "ERROR: node is not installed or not on PATH."
  exit 1
fi
if ! command -v npm >/dev/null 2>&1; then
  echo "ERROR: npm is not installed or not on PATH."
  exit 1
fi
if ! command -v zip >/dev/null 2>&1; then
  echo "ERROR: zip is not installed (e.g. apt install zip / brew install zip)."
  exit 1
fi

echo "Node: $(node -v)  |  npm: $(npm -v)"

if [[ "$RUN_NPM_INSTALL" = true ]]; then
  echo "------------------------------------------"
  echo "Running: npm install"
  echo "------------------------------------------"
  npm install
else
  echo "------------------------------------------"
  echo "Skipping npm install (default). Use --install if node_modules is missing or stale."
  echo "------------------------------------------"
  if [[ ! -d "$SCRIPT_DIR/node_modules" ]]; then
    echo "ERROR: node_modules not found. Run: ./build-and-package.sh --install"
    exit 1
  fi
fi

echo "------------------------------------------"
echo "Running: npm run build"
echo "------------------------------------------"
npm run build

if [[ ! -d "$SCRIPT_DIR/build" ]]; then
  echo "ERROR: build/ missing after npm run build."
  exit 1
fi

if [[ ! -f "$SCRIPT_DIR/build/asset-manifest.json" ]]; then
  echo "ERROR: build/asset-manifest.json missing after npm run build."
  echo "       Ensure webpack.config.js and scripts/adflipr-build-manifest-plugin.js are present."
  exit 1
fi

echo "------------------------------------------"
echo "Running: ./create-plugin-zip.sh"
echo "------------------------------------------"
"$SCRIPT_DIR/create-plugin-zip.sh"

VER_LINE="$(grep 'const ADFLIPR_PLUGIN_VERSION' "$SCRIPT_DIR/includes/adflipr-version.php" | head -1 | sed -n "s/.*const ADFLIPR_PLUGIN_VERSION = '\\([^']*\\)';/\1/p")"
echo "=========================================="
echo "Done."
echo "  Package: $SCRIPT_DIR/adflipr.zip"
echo "  Plugin version (ADFLIPR_PLUGIN_VERSION / ADFLIPR_VERSION): ${VER_LINE:-unknown}"
echo "Upload: Plugins → Add New → Upload Plugin (WordPress should offer upgrade if version increased)."
echo "=========================================="
