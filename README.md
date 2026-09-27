# SC Datasheet Generator - PHP Version

This is a PHP port of the SC Datasheet Generator, originally built with Python/Flask.

## Requirements

- PHP 7.4 or higher
- Apache web server (with mod_rewrite enabled)
- Composer (for dependency management)

## Installation

1. **Install Composer dependencies:**
   ```bash
   cd datasheet-generator-php
   composer install
   ```

2. **Configure Apache:**

   Point your Apache virtual host document root to the `public/` directory.

   Example Apache configuration:
   ```apache
   <VirtualHost *:80>
       ServerName datasheet.local
       DocumentRoot "E:/AI_projects/dataset/ds/datasheet-generator-php/public"
       
       <Directory "E:/AI_projects/dataset/ds/datasheet-generator-php/public">
           AllowOverride All
           Require all granted
       </Directory>
   </VirtualHost>
   ```

3. **Enable mod_rewrite:**
   ```bash
   # On Linux
   sudo a2enmod rewrite
   sudo systemctl restart apache2
   
   # On Windows with XAMPP/WAMP
   # Edit httpd.conf and uncomment:
   # LoadModule rewrite_module modules/mod_rewrite.so
   ```

4. **Alternative: Use PHP built-in server (for development):**
   ```bash
   php -S localhost:8000 index.php
   # Or on Windows, double-click run.bat
   # Or via Composer: composer start
   ```

   Then open: http://localhost:8000

## Project Structure

```
datasheet-generator-php/
├── api/
│   ├── data_loader.php      # Loads fixtures JSON data
│   ├── pdf_generator.php    # Generates PDF datasheets
│   └── routes.php           # API route handlers
├── app/
│   ├── datasheet-generator.html  # Main frontend HTML
│   └── images/              # Fixture images
├── assets/
│   └── images/              # Logo and static assets
├── data/
│   └── fixtures_app_data.json   # Fixture database
├── public/
│   ├── index.php           # Main entry point
│   └── .htaccess           # Apache rewrite rules
├── composer.json           # PHP dependencies
└── README.md              # This file
```

## API Endpoints

All endpoints are prefixed with `/api/`:

### Health Check
- `GET /api/health` - Returns server status and fixture count

### Categories
- `GET /api/categories` - Returns list of all fixture categories

### Fixtures
- `GET /api/fixtures` - List all fixtures
  - Query params: `?category=...` `?q=...` (search)
- `GET /api/fixtures/{id}` - Get single fixture by ID
- `GET /api/fixtures/{id}/variants/{vid}` - Get specific variant
- `GET /api/fixtures/{id}/variants/{vid}/pdf` - Download PDF datasheet

### Reload Data
- `GET /api/reload` - Reload fixtures data from disk (without restarting server)

## Frontend

The frontend is a single-page application located at `app/datasheet-generator.html`.

Access it at: http://localhost:8000/

Features:
- Browse fixtures by category
- Search by name, power, or specs
- Filter and sort fixtures
- View detailed specifications
- Generate PDF datasheets
- Dark/light theme toggle

## PDF Generation

PDF generation uses the TCPDF library. Install it via Composer:

```bash
composer require tecnickcom/tcpdf
```

If TCPDF is not installed, a basic fallback PDF will be generated with limited formatting.

## Differences from Python Version

1. **Backend Framework**: Flask → PHP core
2. **PDF Generation**: reportlab → TCPDF
3. **Routing**: Flask routes → PHP router + .htaccess
4. **Data Caching**: In-memory Python dict → PHP global variable

## Development

To modify the frontend:
1. Edit `app/datasheet-generator.html`
2. Refresh browser (no rebuild needed)

To modify the API:
1. Edit files in `api/` directory
2. No restart needed (PHP processes each request fresh)

To update fixture data:
1. Replace `data/fixtures_app_data.json`
2. Or call `GET /api/reload` to reload without restart

## Troubleshooting

**404 errors on API routes:**
- Ensure mod_rewrite is enabled
- Check `.htaccess` file exists in `public/`
- Verify Apache AllowOverride is set to "All"

**PDF generation fails:**
- Run `composer install` to install TCPDF
- Check PHP error logs for details

**Fixtures not loading:**
- Verify `data/fixtures_app_data.json` exists
- Check file permissions (readable by web server)

## License

© Short Circuit Company
