#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null
done < <(find . -type f -name '*.php' -not -path './dist/*' -print0)

php tests/snippets-extraction-regression.php
php tools/check-translations.php

if command -v node >/dev/null 2>&1; then
  node --check assets/js/features/snippets.js
fi

echo "Core Blueprint Snippets checks: PASS"
