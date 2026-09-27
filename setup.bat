@echo off
echo ==========================================
echo SC Datasheet Generator - PHP Setup
echo ==========================================
echo.

REM Check if PHP is installed
php -v >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: PHP is not installed or not in PATH
    echo Please install PHP 7.4 or higher
    pause
    exit /b 1
)

echo [OK] PHP found
php -v | findstr /C:"PHP"
echo.

REM Check if Composer is installed
composer --version >nul 2>&1
if %errorlevel% neq 0 (
    echo WARNING: Composer is not installed
    echo Please install Composer from https://getcomposer.org/
    echo.
    echo You can still run the application with basic PDF generation,
    echo but for full PDF support, install Composer and run:
    echo   composer install
    echo.
) else (
    echo [OK] Composer found
    composer --version | findstr /C:"Composer"
    echo.
    echo Installing dependencies...
    composer install
    echo.
)

REM Check if data file exists
if not exist "data\fixtures_app_data.json" (
    echo ERROR: data\fixtures_app_data.json not found
    echo Please copy the data file from the Python version
    pause
    exit /b 1
)

echo [OK] Data file found
echo.

echo ==========================================
echo Setup complete!
echo ==========================================
echo.
echo To start the development server, run:
echo   php -S localhost:8000
echo.
echo Then open: http://localhost:8000
echo.
echo For production, point your web server to this project root directory
echo See README.md for more details
echo.
pause
