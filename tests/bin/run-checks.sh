#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

command -v php >/dev/null 2>&1 || { echo "PHP is required." >&2; exit 1; }
command -v node >/dev/null 2>&1 || { echo "Node.js is required." >&2; exit 1; }

while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done < <(find . -type f -name '*.php' -not -path './dist/*' -not -path './vendor/*' -print0)

php tests/snippets-extraction-regression.php
php tests/release-tooling-regression.php
php tools/check-translations.php
node --check assets/js/features/snippets.js

echo "Core Blueprint Snippets checks: PASS"
