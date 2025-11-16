#!/usr/bin/env bash

# Package CFP project into a tar.gz for submission.
# Usage:
#   bash scripts/package_submission.sh

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

OUT_FILE="cfp_submission.tar.gz"

echo "Creating ${OUT_FILE} from ${ROOT_DIR}..."

tar \
  --exclude=".git" \
  --exclude="cfp_submission.tar.gz" \
  -czf "${OUT_FILE}" .

echo "Done. Output: ${OUT_FILE}"


