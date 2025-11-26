#!/usr/bin/env bash

###############################################################################
# CFP Database Import Script
#
# This script imports all SQL files found in:
#   /groups/l/lv_comp353_2/db
# into the MySQL database using the provided credentials.
#
# It uses the mysql CLI and runs files in a deterministic order:
#   1) reset.sql  (if present)
#   2) schema.sql (if present)
#   3) seed.sql   (if present)
#   4) any other *.sql files in alphabetical order
#
# Run this on the school server:
#   chmod +x import_db.sh
#   ./import_db.sh
#
# You can override defaults via environment variables:
#   DB_NAME=mydb DB_USER=myuser DB_PASS=mypass ./import_db.sh
###############################################################################

set -euo pipefail

###############################################################################
# Configuration (can be overridden via environment variables)
###############################################################################

: "${WORK_DIR:=/groups/l/lv_comp353_2}"

DB_DIR="${WORK_DIR}/db"

: "${DB_HOST:=lvc353.encs.concordia.ca}"
: "${DB_NAME:=lvc353_2}"
: "${DB_USER:=lvc353_2}"
: "${DB_PASS:=itchywhale23}"

MYSQL_BIN="${MYSQL_BIN:-mysql}"

echo "=== CFP Database Import ==="
echo "DB directory : ${DB_DIR}"
echo "DB host/name : ${DB_HOST}/${DB_NAME}"
echo "DB user      : ${DB_USER}"
echo

if [ ! -d "$DB_DIR" ]; then
  echo "ERROR: DB directory not found: $DB_DIR"
  echo "Make sure you ran ./deploy.sh first so SQL files are synced."
  exit 1
fi

if ! command -v "$MYSQL_BIN" >/dev/null 2>&1; then
  echo "ERROR: mysql CLI not found (looked for: $MYSQL_BIN)"
  echo "Ensure MySQL client tools are installed on the server."
  exit 1
fi

###############################################################################
# Build ordered list of SQL files
###############################################################################

SQL_FILES=()

if [ -f "${DB_DIR}/reset.sql" ]; then
  SQL_FILES+=("${DB_DIR}/reset.sql")
fi
if [ -f "${DB_DIR}/schema.sql" ]; then
  SQL_FILES+=("${DB_DIR}/schema.sql")
fi
if [ -f "${DB_DIR}/seed.sql" ]; then
  SQL_FILES+=("${DB_DIR}/seed.sql")
fi

while IFS= read -r -d '' file; do
  case "$(basename "$file")" in
    reset.sql|schema.sql|seed.sql)
      # already added explicitly
      ;;
    *)
      SQL_FILES+=("$file")
      ;;
  esac
done < <(find "$DB_DIR" -maxdepth 1 -type f -name '*.sql' -print0 | sort -z)

if [ "${#SQL_FILES[@]}" -eq 0 ]; then
  echo "No .sql files found in $DB_DIR"
  exit 0
fi

echo "The following SQL files will be imported, in order:"
for f in "${SQL_FILES[@]}"; do
  echo "  - $(basename "$f")"
done
echo

###############################################################################
# Import
###############################################################################

for f in "${SQL_FILES[@]}"; do
  echo "Running $(basename "$f") ..."
  "$MYSQL_BIN" \
    --host="$DB_HOST" \
    --user="$DB_USER" \
    --password="$DB_PASS" \
    "$DB_NAME" < "$f"
  echo "  -> OK"
done

echo
echo "Database import completed successfully."


