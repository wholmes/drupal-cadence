#!/usr/bin/env bash
# Sync the Cadence plugin from this repo (source of truth) into the Drupal site
# at install-dir so the site uses the latest code. Run from repo root after
# completing a task.
set -e
REPO_ROOT="$(cd "$(dirname "$0")" && pwd)"
DEST="${REPO_ROOT}/install-dir/web/modules/custom/cadence"

if [[ ! -d "$REPO_ROOT/install-dir/web" ]]; then
  echo "Error: install-dir/web not found. Create install-dir with a Drupal site first."
  exit 1
fi

mkdir -p "$DEST"
rsync -a --delete \
  --exclude='install-dir' \
  --exclude='.git' \
  --exclude='.DS_Store' \
  "$REPO_ROOT/" "$DEST/"

echo "Done. Plugin synced to install-dir/web/modules/custom/cadence/"
