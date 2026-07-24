@echo off
REM ============================================================
REM  setup_db_user.bat — Re-sincroniza el usuario de MySQL con el .env
REM  Doble clic (o ejecutar desde cmd) tras reinstalar XAMPP,
REM  restaurar la BD o clonar el proyecto en otra maquina.
REM
REM  Si tu root de MySQL tiene contraseña, ejecuta desde una consola:
REM    setup_db_user.bat --admin-user=root --admin-pass=TU_PASS_ROOT
REM ============================================================
setlocal
set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" set "PHP_EXE=php"

"%PHP_EXE%" "%~dp0setup_db_user.php" %*

echo.
pause
