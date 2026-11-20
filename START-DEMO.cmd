@echo off
REM ===========================================================
REM  Smart Stock - start the demonstration
REM
REM  Double-click this file. It starts Apache and MySQL if they
REM  are not already running, then opens four Chrome windows,
REM  one for each role. Each window keeps its own sign-in,
REM  because the session cookie is tied to the address used.
REM
REM  On each sign-in page, press the role button you want.
REM ===========================================================
title Smart Stock - demo

echo.
echo   Smart Stock - starting the demonstration
echo   ----------------------------------------

REM --- Apache and MySQL -------------------------------------
tasklist /FI "IMAGENAME eq httpd.exe" 2>NUL | find /I "httpd.exe" >NUL
if errorlevel 1 (
    echo   Starting Apache and MySQL...
    start "" "C:\xampp\xampp_start.exe"
    timeout /t 8 /nobreak >NUL
) else (
    echo   Apache is already running.
)

REM --- one Chrome window per role ---------------------------
set CHROME="C:\Program Files\Google\Chrome\Application\chrome.exe"
if not exist %CHROME% set CHROME="C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"

echo   Opening four windows, one per role...
start "" %CHROME% --new-window "http://localhost/SmartStock/"
timeout /t 2 /nobreak >NUL
start "" %CHROME% --new-window "http://127.0.0.1/SmartStock/"
timeout /t 2 /nobreak >NUL
start "" %CHROME% --new-window "http://[::1]/SmartStock/"
timeout /t 2 /nobreak >NUL
start "" %CHROME% --incognito "http://localhost/SmartStock/"

echo.
echo   Four windows are open. Suggested arrangement:
echo.
echo     localhost      - Administrator
echo     127.0.0.1      - Store Manager
echo     [::1]          - Work Staff
echo     incognito      - Cashier
echo.
echo   On a phone, on the same Wi-Fi:
echo     http://192.168.31.14/SmartStock/
echo.
echo   This window can be closed.
timeout /t 12 /nobreak >NUL
