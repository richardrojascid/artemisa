@echo off
cd /d "%~dp0.."

set PHP_EXE=php
if exist "C:\xampp\php\php.exe" set PHP_EXE=C:\xampp\php\php.exe

"%PHP_EXE%" -v >nul 2>&1
if errorlevel 1 (
    echo Error: PHP no esta instalado.
    echo Prueba con XAMPP: C:\xampp\php\php.exe
    echo.
    pause
    exit /b 1
)

set PORT=8080
if not "%~1"=="" set PORT=%~1

echo ============================================
echo  Artemisa Salon de Te - servidor local
echo ============================================
echo.
echo  Carpeta: %CD%
if exist ".git" (
    for /f "delims=" %%i in ('git log -1 --oneline 2^>nul') do echo  Git: %%i
) else (
    echo  AVISO: no es carpeta git - puede ser codigo viejo
)
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

"%PHP_EXE%" -S localhost:%PORT% -t .

