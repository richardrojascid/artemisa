@echo off
call "%~dp0actualizar.bat" %*
if errorlevel 1 exit /b 1
echo.
echo Iniciando servidor...
call "%~dp0serve-local.bat"
