#!/usr/bin/env bash
# Importa el código desde JudgmentOfTheFallenWing/artemisa → richardrojascid/artemisa
set -euo pipefail

TARGET="${1:-../artemisa}"

if [[ ! -d "$TARGET/.git" ]]; then
  git clone https://github.com/richardrojascid/artemisa.git "$TARGET"
fi

cd "$TARGET"
git remote remove export 2>/dev/null || true
git remote add export https://github.com/richardrojascid/JudgmentOfTheFallenWing.git
git fetch export artemisa
git checkout -B main export/artemisa
git push -u origin main --force

echo ""
echo "Listo: https://github.com/richardrojascid/artemisa"
