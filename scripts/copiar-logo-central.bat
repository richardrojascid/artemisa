@echo off
setlocal
cd /d "%~dp0.."

set "ORIGEN=%USERPROFILE%\Downloads\artemisa_logo_central.png"
set "DESTINO=assets\images\artemisa-logo-central.png"

if not exist "%ORIGEN%" (
    echo.
    echo No se encontro el archivo:
    echo   %ORIGEN%
    echo.
    echo Copia manualmente tu logo a:
    echo   %CD%\%DESTINO%
    echo.
    pause
    exit /b 1
)

if not exist "assets\images" mkdir "assets\images"
copy /Y "%ORIGEN%" "%DESTINO%" >nul
if errorlevel 1 (
    echo Error al copiar el logo.
    pause
    exit /b 1
)

echo.
echo Logo central copiado correctamente a:
echo   %DESTINO%
echo.
echo Recarga la pagina con Ctrl+F5.
echo.
pause
