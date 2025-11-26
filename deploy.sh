#!/usr/bin/env bash

###############################################################################
# CFP Deployment Script for COMP 353
#
# This script deploys the CFP project from the current working copy
# into the school server directories:
#   - Working / SQL:  /groups/l/lv_comp353_2
#   - Web root:       /www/groups/l/lv_comp353_2
#
# It will:
#   - Create missing directories
#   - Sync files to the correct locations
#   - Update DB credentials in src/includes/db.php
#   - Optionally rewrite /assets and /src URLs with a configurable base path
#   - Fix file permissions appropriate for Apache/PHP
#
# Run this on the school server from the root of your CFP repo:
#   chmod +x deploy.sh
#   ./deploy.sh
#
# You can override defaults via environment variables, for example:
#   WEB_BASE_PATH="/lv_comp353_2" WORK_DIR="/groups/l/lv_comp353_2" ./deploy.sh
###############################################################################

set -euo pipefail

###############################################################################
# Configuration (can be overridden via environment variables)
###############################################################################

# Where your non-web working files (SQL, scripts, docs, .md) should live
: "${WORK_DIR:=/groups/l/lv_comp353_2}"

# Apache/PHP web root for this group
: "${WEB_ROOT:=/www/groups/l/lv_comp353_2}"

# Public URL base path for this project (as seen in the browser)
# Example (recommended): /lv_comp353_2
# This will turn links like "/assets/css/main.css" into
# "/lv_comp353_2/assets/css/main.css"
: "${WEB_BASE_PATH:=/lv_comp353_2}"

# Database credentials (from assignment)
: "${DB_HOST:=lvc353.encs.concordia.ca}"
: "${DB_NAME:=lvc353_2}"
: "${DB_USER:=lvc353_2}"
: "${DB_PASS:=itchywhale23}"

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "=== CFP Deploy ==="
echo "Repo root    : ${REPO_ROOT}"
echo "Work dir     : ${WORK_DIR}"
echo "Web root     : ${WEB_ROOT}"
echo "Web base path: ${WEB_BASE_PATH}"
echo "DB host/name : ${DB_HOST}/${DB_NAME}"
echo

###############################################################################
# Helper functions
###############################################################################

ensure_dir() {
  local dir="$1"
  if [ ! -d "$dir" ]; then
    echo "Creating directory: $dir"
    mkdir -p "$dir"
  fi
}

rsync_if_exists() {
  local src="$1"
  local dst="$2"
  if [ -d "$src" ]; then
    echo "Syncing $src -> $dst"
    ensure_dir "$dst"
    rsync -a --delete "$src"/ "$dst"/
  else
    echo "Skipping (not found): $src"
  fi
}

copy_md_files() {
  local src_root="$1"
  local dst_root="$2"
  shopt -s nullglob
  local md_files=("$src_root"/*.md)
  if [ "${#md_files[@]}" -gt 0 ]; then
    echo "Copying markdown files to $dst_root"
    ensure_dir "$dst_root"
    for f in "${md_files[@]}"; do
      echo "  - $(basename "$f")"
      cp "$f" "$dst_root"/
    done
  else
    echo "No top-level .md files found in $src_root"
  fi
  shopt -u nullglob
}

###############################################################################
# 1. Create required directory structure
###############################################################################

echo "Step 1/5: Ensuring directory structure..."

ensure_dir "$WEB_ROOT"
ensure_dir "$WORK_DIR"

ensure_dir "$WEB_ROOT/src"
ensure_dir "$WEB_ROOT/assets"

ensure_dir "$WORK_DIR/db"
ensure_dir "$WORK_DIR/scripts"
ensure_dir "$WORK_DIR/docs"

echo "Directory structure OK."
echo

###############################################################################
# 2. Sync files from repo to target directories
###############################################################################

echo "Step 2/5: Syncing project files..."

# PHP backend
rsync_if_exists "${REPO_ROOT}/src"    "${WEB_ROOT}/src"

# Static assets (CSS, JS, images)
rsync_if_exists "${REPO_ROOT}/assets" "${WEB_ROOT}/assets"

# SQL schema & data
rsync_if_exists "${REPO_ROOT}/db"     "${WORK_DIR}/db"

# Utility scripts
rsync_if_exists "${REPO_ROOT}/scripts" "${WORK_DIR}/scripts"

# Documentation
rsync_if_exists "${REPO_ROOT}/docs"   "${WORK_DIR}/docs"

# Markdown references (README, INSTALL, README_DEPLOY, etc.)
copy_md_files "${REPO_ROOT}" "${WORK_DIR}"

echo "File sync complete."
echo

###############################################################################
# 3. Update PHP configuration files (DB + paths)
###############################################################################

echo "Step 3/5: Updating PHP configuration..."

DB_CONFIG_FILE="${WEB_ROOT}/src/includes/db.php"

if [ -f "$DB_CONFIG_FILE" ]; then
  echo "Updating DB credentials in $DB_CONFIG_FILE"

  # Update DB_HOST, DB_NAME, DB_USER, DB_PASS constants
  sed -i \
    -e "s/^const DB_HOST = .*/const DB_HOST = '${DB_HOST}';/" \
    -e "s/^const DB_NAME = .*/const DB_NAME = '${DB_NAME}';/" \
    -e "s/^const DB_USER = .*/const DB_USER = '${DB_USER}';/" \
    -e "s/^const DB_PASS = .*/const DB_PASS = '${DB_PASS}';/" \
    "$DB_CONFIG_FILE"
else
  echo "WARNING: DB config file not found at $DB_CONFIG_FILE"
fi

# Rewrite absolute /assets and /src URLs to include the group base path
# e.g., /assets/css/main.css -> /lv_comp353_2/assets/css/main.css
if [ -d "${WEB_ROOT}/src" ]; then
  echo "Rewriting asset and src URLs under ${WEB_ROOT}/src using base path ${WEB_BASE_PATH}"

  # Update /assets/... references in HTML/PHP
  mapfile -t asset_files < <(grep -rl '"/assets/' "${WEB_ROOT}/src" || true)
  for f in "${asset_files[@]:-}"; do
    echo "  - Adjusting assets in $(basename "$f")"
    sed -i "s#\"/assets/#\"${WEB_BASE_PATH}/assets/#g" "$f"
  done

  # Update /src/... references if any (rare)
  mapfile -t src_files < <(grep -rl '"/src/' "${WEB_ROOT}/src" || true)
  for f in "${src_files[@]:-}"; do
    echo "  - Adjusting src paths in $(basename "$f")"
    sed -i "s#\"/src/#\"${WEB_BASE_PATH}/src/#g" "$f"
  done
else
  echo "WARNING: ${WEB_ROOT}/src not found; skipping URL path rewriting."
fi

echo "PHP configuration update complete."
echo

###############################################################################
# 4. Fix file permissions for Apache/PHP
###############################################################################

echo "Step 4/5: Setting file permissions for web root..."

if [ -d "$WEB_ROOT" ]; then
  # Directories: 755
  find "$WEB_ROOT" -type d -exec chmod 755 {} \;
  # Files: 644
  find "$WEB_ROOT" -type f -exec chmod 644 {} \;
  # Ensure no group/other write on web root
  chmod -R go-w "$WEB_ROOT"
else
  echo "WARNING: Web root $WEB_ROOT does not exist; skipping permissions."
fi

echo "Permissions set (dirs 755, files 644, no group/other write on web root)."
echo

###############################################################################
# 5. Summary
###############################################################################

echo "Step 5/5: Deployment summary"
echo "----------------------------------------"
echo "Working directory : ${WORK_DIR}"
echo "  - db/           : ${WORK_DIR}/db"
echo "  - scripts/      : ${WORK_DIR}/scripts"
echo "  - docs/         : ${WORK_DIR}/docs"
echo "  - *.md          : ${WORK_DIR}/*.md"
echo
echo "Web root          : ${WEB_ROOT}"
echo "  - PHP src       : ${WEB_ROOT}/src"
echo "  - assets        : ${WEB_ROOT}/assets"
echo
echo "Database          : ${DB_USER}@${DB_HOST}/${DB_NAME}"
echo "URL base path     : ${WEB_BASE_PATH}"
echo
echo "Deployment completed successfully."
echo "You can now:"
echo "  - Import the database using:  ./import_db.sh"
echo "  - Run basic checks using:     ./test_web.sh"
echo "----------------------------------------"


