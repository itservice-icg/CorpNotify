@echo off
setlocal EnableExtensions

set "ROOT_DIR=%~dp0"
set "SERVER_DIR=%ROOT_DIR%server"
set "AGENT_PROJECT=%ROOT_DIR%CorpNotifyAgent\CorpNotifyAgent.csproj"
set "AGENT_DIR=%ROOT_DIR%publish\win-x64"
set "AGENT_EXE=%AGENT_DIR%\CorpNotifyAgent.exe"
set "LOGIN_URL=http://127.0.0.1:8000/login"

title CorpNotify Launcher

where php >nul 2>&1
if errorlevel 1 (
    echo [ERROR] PHP was not found in PATH.
    echo Install PHP or add C:\xampp\php to PATH, then try again.
    pause
    exit /b 1
)

if not exist "%SERVER_DIR%\artisan" (
    echo [ERROR] Laravel project was not found at:
    echo %SERVER_DIR%
    pause
    exit /b 1
)

if not exist "%SERVER_DIR%\vendor\autoload.php" (
    echo [ERROR] Laravel dependencies are missing.
    echo Run: cd /d "%SERVER_DIR%" ^&^& composer install
    pause
    exit /b 1
)

if not exist "%AGENT_EXE%" (
    echo [INFO] Published Agent was not found. Building it now...
    where dotnet >nul 2>&1
    if errorlevel 1 (
        echo [ERROR] .NET SDK was not found in PATH.
        pause
        exit /b 1
    )

    dotnet publish "%AGENT_PROJECT%" -c Release -r win-x64 --self-contained true -p:PublishSingleFile=true -o "%AGENT_DIR%"
    if errorlevel 1 (
        echo [ERROR] Agent publish failed.
        pause
        exit /b 1
    )
)

if /I "%~1"=="--check" (
    echo [OK] CorpNotify prerequisites are ready.
    exit /b 0
)

pushd "%SERVER_DIR%"
php artisan config:clear >nul 2>&1
popd

powershell.exe -NoProfile -Command "$listener=Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1; if (-not $listener) { exit 0 }; try { $response=Invoke-WebRequest -UseBasicParsing -Uri '%LOGIN_URL%' -TimeoutSec 2; if ($response.StatusCode -eq 200 -and $response.Content -match 'CorpNotify') { exit 10 } } catch {}; $owner=Get-Process -Id $listener.OwningProcess -ErrorAction SilentlyContinue; $ownerName=if ($owner) { $owner.ProcessName } else { 'Unknown' }; Write-Host ('[ERROR] Port 8000 is already used by PID ' + $listener.OwningProcess + ' (' + $ownerName + ').'); exit 20"
set "PORT_STATUS=%ERRORLEVEL%"

if /I "%~1"=="--check-port" (
    if "%PORT_STATUS%"=="0" echo [OK] Port 8000 is available.
    if "%PORT_STATUS%"=="10" echo [OK] CorpNotify Server is using port 8000.
    if "%PORT_STATUS%"=="20" exit /b 1
    exit /b 0
)

if "%PORT_STATUS%"=="0" (
    echo [INFO] Starting Laravel Server...
    start "CorpNotify Server" /D "%SERVER_DIR%" cmd.exe /k "php artisan serve --host=127.0.0.1 --port=8000"
) else if "%PORT_STATUS%"=="10" (
    echo [INFO] CorpNotify Server is already running on port 8000.
) else (
    echo [ERROR] CorpNotify cannot start because port 8000 belongs to another program.
    echo Close that program or change the CorpNotify port, then try again.
    pause
    exit /b 1
)

echo [INFO] Waiting for Laravel Server...
powershell.exe -NoProfile -Command "$deadline=(Get-Date).AddSeconds(30); do { try { $response=Invoke-WebRequest -UseBasicParsing -Uri '%LOGIN_URL%' -TimeoutSec 2; if ($response.StatusCode -eq 200) { exit 0 } } catch {}; Start-Sleep -Milliseconds 500 } while ((Get-Date) -lt $deadline); exit 1"
if errorlevel 1 (
    echo [ERROR] Laravel Server did not become ready within 30 seconds.
    echo Check the CorpNotify Server window for details.
    pause
    exit /b 1
)

tasklist.exe /FI "IMAGENAME eq CorpNotifyAgent.exe" /NH | find.exe /I "CorpNotifyAgent.exe" >nul
if errorlevel 1 (
    echo [INFO] Starting CorpNotify Agent...
    start "CorpNotify Agent" /D "%AGENT_DIR%" "%AGENT_EXE%"
) else (
    echo [INFO] CorpNotify Agent is already running.
)

echo [OK] CorpNotify is running.
start "" "%LOGIN_URL%"

endlocal
exit /b 0
