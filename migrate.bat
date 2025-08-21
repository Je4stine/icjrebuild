@echo off
REM Windows batch file to run migrations

echo ICJ Kenya API - Migration Tool (Windows)
echo ==========================================
echo.

REM Check if PHP is available
php --version >nul 2>&1
if errorlevel 1 (
    echo Error: PHP is not installed or not in PATH
    echo Please install PHP and add it to your system PATH
    pause
    exit /b 1
)

REM Run the migration script with arguments
php "%~dp0migrate.php" %*

if errorlevel 1 (
    echo.
    echo Migration failed. Please check the error messages above.
    pause
    exit /b 1
)

echo.
pause
