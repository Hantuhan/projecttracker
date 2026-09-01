#!/usr/bin/env bash
# Create a clean zip for Hostinger upload (no git, docker, local config)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${ROOT}/projecttracker-hostinger.zip"

cd "$ROOT"
rm -f "$OUT"

zip -r "$OUT" . \
  -x ".git/*" \
  -x ".git/**/*" \
  -x "config/config.php" \
  -x "install/.installed" \
  -x "Dockerfile" \
  -x "docker-compose.yml" \
  -x "scripts/smoke.sh" \
  -x "scripts/package-hostinger.sh" \
  -x ".DS_Store" \
  -x "*.log" \
  -x "projecttracker-hostinger.zip"

echo "Created: $OUT"
echo "Upload and extract into public_html on Hostinger."
