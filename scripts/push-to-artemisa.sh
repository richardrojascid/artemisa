#!/usr/bin/env bash
# Ejecutar DESPUÉS de crear el repo vacío en GitHub: richardrojascid/artemisa
set -euo pipefail
cd "$(dirname "$0")/.."

git remote remove artemisa 2>/dev/null || true
git remote add artemisa https://github.com/richardrojascid/artemisa.git

git branch -M main
git push -u artemisa main

echo ""
echo "Listo: https://github.com/richardrojascid/artemisa"
