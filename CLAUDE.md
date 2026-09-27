# CLAUDE.md

This file provides guidance to Claude Code when working with code in this repository.

## Project Overview

**SC Datasheet Generator (PHP Version)** — A PHP port of the Python/Flask datasheet generator. An interactive web app for browsing, filtering, and generating technical datasheets for Short Circuit Company's lighting fixtures.

**Tech Stack**: PHP 7.4+ backend API + Vanilla HTML/CSS/JS frontend + TCPDF for PDF generation

---

## Architecture

### Backend (PHP)
- **Entry Point**: `public/index.php` - Main router and request handler
- **API Routes**: `api/routes.php` - Handles all `/api/*` endpoints
- **Data Layer**: `api/data_loader.php` - Loads and caches fixtures JSON data
- **PDF Generation**: `api/pdf_generator.php` - Generates PDF datasheets using TCPDF

### Frontend (Single-page app)
- **File**: `app/datasheet-generator.html`
- **Behavior**: Three-stage selector flow: Category → Fixture → Variant → Generate sheet
- **Data flow**: Fetch categories and fixtures from `/api`, store in memory (DATA array), render on demand
- **Output**: Dynamic HTML sheet (printable or PDF export via server)

### Data Layer
- **Location**: `data/fixtures_app_data.json`
- Source of truth: 87 fixtures with nested variants
- Each variant has specs (power, efficacy, CRI), technical data, tables, figures, abstract, and features

---

## Key Differences from Python Version

1. **Backend Framework**: Flask (Python) → Core PHP
2. **Routing**: Flask decorators → PHP router with .htaccess rewrites
3. **PDF Generation**: reportlab (Python) → TCPDF (PHP)
4. **Data Caching**: Python dict in memory → PHP global variable (resets per request)
5. **Deployment**: WSGI server → Apache/Nginx with PHP-FPM or built-in PHP server

---

## API Endpoints

All endpoints prefixed with `/api/`:

### Health & Admin
- `GET /api/health` - Server status and fixture count
- `GET /api/reload` - Reload fixtures data from disk

### Categories & Fixtures
- `GET /api/categories` - List all categories
- `GET /api/fixtures` - List fixtures (supports `?category=...` and `?q=...` search)
- `GET /api/fixtures/{id}` - Get single fixture
- `GET /api/fixtures/{id}/variants/{vid}` - Get specific variant
- `GET /api/fixtures/{id}/variants/{vid}/pdf` - Generate and download PDF

---

## Development Workflow

### No build step
The HTML is self-contained. Changes to frontend require only browser refresh.

### Running the server

**Development (built-in PHP server):**
```bash
cd public
php -S localhost:8000
```

**Production (Apache):**
Configure virtual host to point to `public/` directory with mod_rewrite enabled.

### API Dependency
The frontend expects API at `/api` with same endpoints as Flask version.

### Data Updates
After modifying `fixtures_app_data.json`:
- Call `GET /api/reload` to reload data without restarting
- Or restart PHP server (if using built-in server)

### PDF Generation
Requires TCPDF library:
```bash
composer require tecnickcom/tcpdf
```

Without TCPDF, a basic fallback PDF is generated.

---

## File Structure

```
datasheet-generator-php/
├── public/
│   ├── index.php          # Main entry point & router
│   └── .htaccess          # Apache rewrite rules
├── api/
│   ├── routes.php         # API endpoint handlers
│   ├── data_loader.php    # JSON data loader
│   └── pdf_generator.php  # PDF generation
├── app/
│   ├── datasheet-generator.html  # Frontend SPA
│   └── images/            # Fixture images
├── data/
│   └── fixtures_app_data.json    # Fixtures database
├── assets/
│   └── images/            # Logo and static assets
├── config.php             # Application configuration
├── composer.json          # PHP dependencies
├── setup.bat / setup.sh   # Setup scripts
└── README.md             # Setup & usage docs
```

---

## Brand Integration

Same as Python version - follows `sc-brand.md`:

### Colors
- `--sc-red: #EB1B26` (Primary)
- `--sc-dark-red: #A40E16` (Gradient)
- `--sc-black: #000000` (Text)
- `--sc-white: #FFFFFF`
- `--sc-gray: #CCCCCC`

### Fonts
- **Headline**: Anton (uppercase, 3X scale)
- **UI/Body**: Poppins (weights 300, 400, 500, 600)

### Layout
- Golden Ratio: 1.618
- Max content width: 80%
- Min margins: 10% each side

---

## PHP-Specific Notes

### Request Handling
- Each PHP request is independent (no persistent state like Flask)
- Data is loaded from JSON and cached in `$GLOBALS` per request
- For high-traffic, consider using APCu or Redis for caching

### Error Handling
- Development: `display_errors = 1` in config
- Production: Log errors, don't display to users

### Security
- Input validation on all API parameters
- Path traversal protection when serving files
- CORS headers configured in index.php

### PDF Generation
- TCPDF is more verbose than reportlab
- Template matching may not be 100% identical
- Consider using mPDF or FPDF if TCPDF is too complex

---

## Common Tasks

### Add a new API endpoint
1. Add route handling in `api/routes.php`
2. Implement handler function
3. Test with browser or curl

### Modify PDF layout
1. Edit `api/pdf_generator.php`
2. Adjust `buildPDFContent()` function
3. Regenerate PDF to test

### Update fixture data
1. Replace `data/fixtures_app_data.json`
2. Call `/api/reload` or restart server

### Deploy to production
1. Configure Apache virtual host pointing to `public/`
2. Enable mod_rewrite
3. Run `composer install --no-dev`
4. Set `debug => false` in config.php

---

## Troubleshooting

**404 on API routes:**
- Check mod_rewrite is enabled
- Verify .htaccess exists and AllowOverride is set

**JSON parse errors:**
- Validate fixtures_app_data.json syntax
- Check file permissions (readable by web server)

**PDF generation fails:**
- Install TCPDF: `composer require tecnickcom/tcpdf`
- Check PHP memory_limit (increase if needed)

**Images not loading:**
- Verify images exist in `app/images/`
- Check file paths are correct (case-sensitive on Linux)

---

## Future Enhancements

1. **Database Migration**: Move from JSON to MySQL/PostgreSQL
2. **Caching Layer**: Implement Redis/Memcached for production
3. **API Authentication**: Add JWT or API key authentication
4. **Admin Panel**: Create PHP admin interface for managing fixtures
5. **PDF Templates**: Improve TCPDF templates to match reportlab output exactly

---

## Notes

- This is a **functional port**, not a line-by-line translation
- PDF output may differ slightly from Python version
- Frontend is identical to Python version
- API contract is maintained for compatibility
