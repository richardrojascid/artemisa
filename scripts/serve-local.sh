#!/usr/bin/env bash
# Servidor local para probar Artemisa Comanda
set -euo pipefail
cd "$(dirname "$0")/.."

PORT="${1:-8080}"

if ! command -v php >/dev/null 2>&1; then
  echo "Error: PHP no está instalado."
  echo "En Ubuntu/Debian: sudo apt install php php-sqlite3 php-zip"
  echo "En macOS con Homebrew: brew install php"
  exit 1
fi

echo "============================================"
echo " Artemisa Salón de Té — servidor local"
echo "============================================"
echo ""
echo "  URL: http://localhost:${PORT}"
echo ""
echo "  Pasos:"
echo "  1. http://localhost:${PORT}/install.php"
echo "  2. http://localhost:${PORT}/login.php  (PIN que definas)"
echo "  3. http://localhost:${PORT}/index.php   (tomar pedidos)"
echo "  4. http://localhost:${PORT}/admin/     (reportes y menú)"
echo ""
echo "  Ctrl+C para detener"
echo "============================================"
echo ""

php -S "localhost:${PORT}" -t .
