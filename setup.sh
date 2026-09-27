#!/bin/bash

echo "=========================================="
echo "SC Datasheet Generator - PHP Setup"
echo "=========================================="
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null
then
    echo "ERROR: PHP is not installed or not in PATH"
    echo "Please install PHP 7.4 or higher"
    exit 1
fi

echo "✓ PHP found: $(php -v | head -n 1)"
echo ""

# Check if Composer is installed
if ! command -v composer &> /dev/null
then
    echo "WARNING: Composer is not installed"
    echo "Please install Composer from https://getcomposer.org/"
    echo ""
    echo "You can still run the application with basic PDF generation,"
    echo "but for full PDF support, install Composer and run:"
    echo "  composer install"
    echo ""
else
    echo "✓ Composer found: $(composer --version | head -n 1)"
    echo ""
    echo "Installing dependencies..."
    composer install
    echo ""
fi

# Check if data file exists
if [ ! -f "data/fixtures_app_data.json" ]; then
    echo "ERROR: data/fixtures_app_data.json not found"
    echo "Please copy the data file from the Python version"
    exit 1
fi

echo "✓ Data file found"
echo ""

echo "=========================================="
echo "Setup complete!"
echo "=========================================="
echo ""
echo "To start the development server, run:"
echo "  php -S localhost:8000 index.php"
echo ""
echo "Then open: http://localhost:8000"
echo ""
echo "For production, point your web server to this project root directory"
echo "See README.md for more details"
echo ""
