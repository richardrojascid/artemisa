@echo off
setlocal
set "PROYECTO=C:\Users\Richard Rojas\artemisa"

echo ============================================
echo  Artemisa - actualizar codigo
echo ============================================
echo.

if not "%~1"=="" set "PROYECTO=%~1"
cd /d "%PROYECTO%" 2>nul
if errorlevel 1 (
    echo Error: no existe la carpeta:
    echo   %PROYECTO%
    pause
    exit /b 1
)

echo Carpeta activa:
echo   %CD%
echo.

if not exist ".git" (
    echo Error: esta carpeta no es un repositorio git.
    echo Clona primero:
    echo   git clone https://github.com/richardrojascid/artemisa.git "%PROYECTO%"
    pause
    exit /b 1
)

git remote get-url export >nul 2>&1
if errorlevel 1 (
    echo Agregando remoto export...
    git remote add export https://github.com/richardrojascid/JudgmentOfTheFallenWing.git
)

echo Descargando cambios (rama artemisa)...
git fetch export artemisa
if errorlevel 1 (
    echo Error al hacer fetch. Revisa internet y acceso a GitHub.
    pause
    exit /b 1
)

echo.
echo Aplicando cambios...
git merge export/artemisa -m "Actualizar desde export/artemisa"
if errorlevel 1 (
    echo.
    echo Hubo un conflicto o error en merge. Revisa git status.
    pause
    exit /b 1
)

echo.
echo Ultimo commit local:
git log -1 --oneline
echo.

findstr /C:"defaultCategory" "assets\js\app.js" >nul 2>&1
if errorlevel 1 (
    echo AVISO: no se encuentra el cambio de Cafes activo en app.js
) else (
    echo OK: app.js tiene el cambio de pestaña Cafes activa
)

findstr /C:"header-hero" "index.php" >nul 2>&1
if errorlevel 1 (
    echo AVISO: no se encuentra el header con logos de fondo en index.php
) else (
    echo OK: index.php tiene header hero con logos de fondo
)

echo.
echo Siguiente paso - iniciar servidor DESDE ESTA CARPETA:
echo   scripts\serve-local.bat
echo.
echo Luego abre:
echo   http://localhost:8080/index.php
echo.
echo Si el navegador no cambia, usa Ctrl+F5
echo ============================================
pause
