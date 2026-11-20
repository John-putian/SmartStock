@echo off
REM ===================================================================
REM  Smart Stock - database setup for XAMPP
REM  DOUBLE-CLICK this file. No administrator needed.
REM
REM  Start Apache and MySQL in the XAMPP Control Panel FIRST,
REM  then run this. It creates the aa_inventory database and
REM  loads the schema and the sample data.
REM ===================================================================
setlocal
title Smart Stock - set up the database
set MYSQL=C:\xampp\mysql\bin\mysql.exe
set HERE=%~dp0
set LOG=%HERE%setup-log.txt

if not exist "%MYSQL%" (
  echo.
  echo   Could not find MySQL at:
  echo     %MYSQL%
  echo   Is XAMPP installed somewhere else? Tell Claude the path.
  echo.
  pause & exit /b 1
)

> "%LOG%" echo ===== Smart Stock database setup  %DATE% %TIME% =====

echo.
echo  [1/4] Checking MySQL is running...
"%MYSQL%" -u root -e "SELECT 1;" >>"%LOG%" 2>&1
if errorlevel 1 (
  echo.
  echo   MySQL is not responding.
  echo   Open the XAMPP Control Panel and press Start next to MySQL,
  echo   then run this file again.
  echo.
  pause & exit /b 1
)
echo        running.

REM Refuse to replace any existing installation. Imports below contain DROP/TRUNCATE.
"%MYSQL%" -u root -N -B -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='aa_inventory';" > "%HERE%work-db-check.txt" 2>>"%LOG%"
if errorlevel 1 goto failed
set /p DB_EXISTS=<"%HERE%work-db-check.txt"
del "%HERE%work-db-check.txt"
if "%DB_EXISTS%"=="1" (
  echo Existing aa_inventory database found. Setup has stopped without changing it.
  echo Back up your database and follow the upgrade instructions in README.md.
  pause & exit /b 1
)

echo  [2/4] Creating the database...
"%MYSQL%" -u root -e "CREATE DATABASE IF NOT EXISTS aa_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >>"%LOG%" 2>&1
if errorlevel 1 goto failed
echo        aa_inventory ready.

echo  [3/4] Loading the tables...
"%MYSQL%" -u root aa_inventory < "%HERE%database\schema.sql" >>"%LOG%" 2>&1
if errorlevel 1 goto failed
echo        tables created.

echo  [4/4] Loading the sample data (this takes a moment)...
"%MYSQL%" -u root aa_inventory < "%HERE%database\seed.sql" >>"%LOG%" 2>&1
if errorlevel 1 goto failed
echo        data loaded.

echo.
"%MYSQL%" -u root aa_inventory -e "SELECT 'products' AS ^`table^`, COUNT(*) AS rows FROM products UNION ALL SELECT 'sales', COUNT(*) FROM sales UNION ALL SELECT 'sale_items', COUNT(*) FROM sale_items UNION ALL SELECT 'users', COUNT(*) FROM users;"

echo.
echo  ==================================================================
echo    DONE.  Open this in your browser:
echo.
echo        http://localhost/SmartStock/
echo.
echo    Sign in with any of these:
echo        admin     / admin123       (administrator)
echo        manager   / manager123     (manager)
echo        cashier   / cashier123     (the till)
echo        staff     / staff123       (floor staff)
echo.
echo    CHANGE THESE PASSWORDS before anyone real uses the system.
echo  ==================================================================
echo.
pause
exit /b 0

:failed
echo.
echo   Something went wrong. The last few lines of the log:
echo.
powershell -NoProfile -Command "Get-Content '%LOG%' -Tail 12"
echo.
echo   Full log: setup-log.txt
pause & exit /b 1
