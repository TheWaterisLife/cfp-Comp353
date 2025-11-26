#!/usr/bin/env bash

###############################################################################
# CFP Test Deployment Script
#
# This script performs basic checks against a deployed CFP instance:
#   - Verifies required directories exist on disk
#   - Checks that the web server can see /assets and /src/public
#   - Confirms that PHP executes by hitting a small probe script
#   - Tests database connectivity via a tiny PHP script
#
# Usage (run on the school server):
#   chmod +x test_web.sh
#   ./test_web.sh
#
# You can override defaults via environment variables:
#   WEB_ROOT_URL="https://lvc353.encs.concordia.ca/lv_comp353_2" ./test_web.sh
###############################################################################

set -euo pipefail

###############################################################################
# Configuration
###############################################################################

: "${WORK_DIR:=/groups/l/lv_comp353_2}"
: "${WEB_ROOT:=/www/groups/l/lv_comp353_2}"

# Base URL as seen from a browser, WITHOUT trailing slash.
# Example: https://lvc353.encs.concordia.ca/lv_comp353_2
: "${WEB_ROOT_URL:=https://lvc353.encs.concordia.ca/lv_comp353_2}"

# Database settings (must match deploy.sh / db.php)
: "${DB_HOST:=lvc353.encs.concordia.ca}"
: "${DB_NAME:=lvc353_2}"
: "${DB_USER:=lvc353_2}"
: "${DB_PASS:=itchywhale23}"

PHP_BIN="${PHP_BIN:-php}"
CURL_BIN="${CURL_BIN:-curl}"

echo "=== CFP Test Deployment ==="
echo "Work dir     : ${WORK_DIR}"
echo "Web root     : ${WEB_ROOT}"
echo "Web root URL : ${WEB_ROOT_URL}"
echo

###############################################################################
# 1. Check required directories exist
###############################################################################

echo "Step 1/4: Checking directory structure..."

REQUIRED_DIRS=(
  "${WEB_ROOT}"
  "${WEB_ROOT}/src"
  "${WEB_ROOT}/src/public"
  "${WEB_ROOT}/assets"
  "${WORK_DIR}"
  "${WORK_DIR}/db"
)

for d in "${REQUIRED_DIRS[@]}"; do
  if [ -d "$d" ]; then
    echo "  [OK] $d"
  else
    echo "  [MISSING] $d"
  fi
done

echo

###############################################################################
# 2. Check that the server can see /assets and PHP pages
###############################################################################

if ! command -v "$CURL_BIN" >/dev/null 2>&1; then
  echo "WARNING: curl not found; skipping HTTP checks."
else
  echo "Step 2/4: HTTP checks for assets and PHP..."

  ASSET_URL="${WEB_ROOT_URL}/assets/css/main.css"
  INDEX_URL="${WEB_ROOT_URL}/src/public/index.php"

  echo "  - GET $ASSET_URL"
  if "$CURL_BIN" -fsS -o /dev/null "$ASSET_URL"; then
    echo "    [OK] assets reachable"
  else
    echo "    [FAIL] cannot reach assets; check WEB_ROOT_URL, WEB_BASE_PATH and Apache config."
  fi

  echo "  - GET $INDEX_URL"
  if "$CURL_BIN" -fsS -o /dev/null "$INDEX_URL"; then
    echo "    [OK] index.php reachable"
  else
    echo "    [FAIL] cannot reach index.php; verify deployment and web root URL."
  fi

  echo
fi

###############################################################################
# 3. PHP execution & DB connection test
###############################################################################

if ! command -v "$PHP_BIN" >/dev/null 2>&1; then
  echo "WARNING: php binary not found; skipping PHP/DB tests."
else
  echo "Step 3/4: Testing PHP execution and DB connection..."

  TEST_PHP="${WEB_ROOT}/src/public/__cfp_test_db.php"

  cat > "$TEST_PHP" <<'PHP'
<?php
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: text/plain');

try {
    $pdo = cfp_get_pdo();
    $stmt = $pdo->query('SELECT 1 AS ok');
    $row = $stmt->fetch();
    if ($row && (int)$row['ok'] === 1) {
        echo "OK: DB connection\n";
        exit(0);
    }
    echo "FAIL: Query did not return expected result\n";
    exit(1);
} catch (Throwable $e) {
    echo "FAIL: Exception: " . $e->getMessage() . "\n";
    exit(1);
}
PHP

  chmod 644 "$TEST_PHP"

  if command -v "$CURL_BIN" >/dev/null 2>&1; then
    TEST_URL="${WEB_ROOT_URL}/src/public/__cfp_test_db.php"
    echo "  - GET $TEST_URL"
    if "$CURL_BIN" -fsS "$TEST_URL"; then
      echo "    [OK] PHP executed and DB connection succeeded (see output above)."
    else
      echo "    [FAIL] Could not execute PHP test page."
    fi
  else
    echo "  - Running PHP test script locally (without HTTP)"
    if "$PHP_BIN" "$TEST_PHP"; then
      echo "    [OK] PHP executed and DB connection succeeded."
    else
      echo "    [FAIL] PHP execution or DB connection failed."
    fi
  fi

  echo
fi

###############################################################################
# 4. Summary
###############################################################################

echo "Step 4/4: Tests complete."
echo "If any of the above steps show [FAIL] or [MISSING],"
echo "review deploy.sh configuration, Apache virtual host/root, and DB settings."


