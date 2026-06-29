@echo off
REM Ejecutar DESPUES de crear el repo vacio en GitHub: richardrojascid/artemisa
cd /d "%~dp0.."

git remote remove artemisa 2>nul
git remote add artemisa https://github.com/richardrojascid/artemisa.git

git branch -M main
git push -u artemisa main

if errorlevel 1 (
    echo.
    echo No se pudo subir. Crea primero el repo vacio en:
    echo https://github.com/new  nombre: artemisa
    pause
    exit /b 1
)

echo.
echo Listo. Repositorio: https://github.com/richardrojascid/artemisa
pause
