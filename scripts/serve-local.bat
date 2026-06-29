@echo off
cd /d "%~dp0.."

where php >nul 2>&1
if errorlevel 1 (
    echo Error: PHP no esta instalado o no esta en el PATH.
    echo.
    echo Consulta PRUEBA-LOCAL.md seccion Windows para instalar PHP.
    pause
    exit /b 1
)

set PORT=8080
if not "%~1"=="" set PORT=%~1

echo ============================================
echo  Artemisa Salon de Te - servidor local
echo ============================================
echo.
echo  URL: http://localhost:%PORT%
echo.
echo  1. http://localhost:%PORT%/install.php
echo  2. http://localhost:%PORT%/login.php
echo  3. http://localhost:%PORT%/index.php
echo  4. http://localhost:%PORT%/admin/
echo.
echo  Ctrl+C para detener
echo ============================================
echo.

php -S localhost:%PORT% -t .
