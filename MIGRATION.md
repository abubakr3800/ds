# Migration Guide: Python (Flask) → PHP

This document explains the differences between the Python and PHP versions and how to migrate.

## Overview

Both versions provide **identical functionality** and **identical API contracts**. The frontend HTML is the same. Only the backend implementation differs.

## Key Differences

| Aspect | Python (Flask) | PHP |
|--------|---------------|-----|
| **Framework** | Flask web framework | Core PHP (no framework) |
| **Routing** | `@app.route()` decorators | PHP router + `.htaccess` |
| **Server** | Flask dev server / Gunicorn | Apache / Nginx / PHP built-in |
| **PDF Library** | reportlab | TCPDF |
| **Data Loading** | In-memory dict (persistent) | JSON loaded per request |
| **Dependencies** | pip install | composer install |
| **Config** | Environment variables | `config.php` |

## File Mapping

### Python → PHP

```
Python (Flask)                    →  PHP
─────────────────────────────────────────────────────────
app.py                            →  public/index.php + api/routes.php
datasheet_pdf.py                  →  api/pdf_generator.php
(data loading in app.py)          →  api/data_loader.php
requirements.txt                  →  composer.json
data/fixtures_app_data.json       →  data/fixtures_app_data.json (same)
app/datasheet-generator.html      →  app/datasheet-generator.html (same)
```

## API Compatibility

The PHP version implements **100% compatible API** with Flask version:

### Endpoints (Identical)
- ✅ `GET /api/health`
- ✅ `GET /api/reload`
- ✅ `GET /api/categories`
- ✅ `GET /api/fixtures` (with query params)
- ✅ `GET /api/fixtures/{id}`
- ✅ `GET /api/fixtures/{id}/variants/{vid}`
- ✅ `GET /api/fixtures/{id}/variants/{vid}/pdf`

### Response Format (Identical)
All responses return the same JSON structure. Frontend code requires **zero changes**.

## Running Both Versions Side-by-Side

You can run both versions simultaneously:

**Python version:**
```bash
cd ds
python app.py
# Runs on http://localhost:5000
```

**PHP version:**
```bash
cd datasheet-generator-php/public
php -S localhost:8000
# Runs on http://localhost:8000
```

## Migration Checklist

If you're switching from Python to PHP:

### 1. Prerequisites
- [ ] Install PHP 7.4+
- [ ] Install Composer (optional, for PDF)
- [ ] Install Apache with mod_rewrite OR use PHP built-in server

### 2. Setup
- [ ] Copy data files: `data/fixtures_app_data.json`
- [ ] Copy assets: `assets/` directory
- [ ] Copy images: `app/images/` directory
- [ ] Run setup script: `setup.bat` (Windows) or `setup.sh` (Linux/Mac)

### 3. Configuration
- [ ] Update `config.php` if needed
- [ ] Configure Apache virtual host (production only)
- [ ] Set up SSL certificate (production only)

### 4. Testing
- [ ] Test `/api/health` endpoint
- [ ] Test fixture listing
- [ ] Test PDF generation
- [ ] Test frontend UI

### 5. Deployment
- [ ] Set `debug => false` in config.php
- [ ] Configure production web server
- [ ] Set up logging
- [ ] Configure backups

## Code Comparison

### Flask Route Example
```python
@api.get("/fixtures/<int:fixture_id>")
def get_fixture(fixture_id: int):
    if not (0 <= fixture_id < len(_fixtures_cache)):
        abort(404, description="fixture not found")
    return jsonify(_fixtures_cache[fixture_id])
```

### PHP Equivalent
```php
function handleGetFixture($fixtureId) {
    $fixtures = loadFixtures();
    if (!isset($fixtures[$fixtureId])) {
        jsonResponse(['error' => 'fixture not found'], 404);
        return;
    }
    jsonResponse($fixtures[$fixtureId]);
}
```

## Performance Considerations

### Python (Flask)
- **Pros**: Persistent data in memory, faster for repeated requests
- **Cons**: Requires WSGI server for production

### PHP
- **Pros**: Simpler deployment, scales with Apache/Nginx
- **Cons**: Loads JSON on each request (negligible for 87 fixtures)

### Optimization Options

**For PHP** (if needed):
1. Enable **OPcache** (PHP bytecode cache)
2. Use **APCu** for data caching
3. Add **Redis** for session/data storage
4. Enable **gzip compression**

## PDF Generation Differences

### Python (reportlab)
- More control over PDF primitives
- Exact template matching possible
- Steeper learning curve

### PHP (TCPDF)
- Higher-level API
- Easier to use
- May differ slightly from original templates

**Note**: Both produce professional PDFs. Visual differences are minimal.

## When to Use Which Version

### Use Python (Flask) if:
- You prefer Python ecosystem
- You need exact PDF template matching
- You're using other Python data processing tools
- Team is more comfortable with Python

### Use PHP if:
- You have existing PHP infrastructure
- You prefer simpler deployment (Apache/PHP-FPM)
- You want easy shared hosting compatibility
- Team is more comfortable with PHP

## Maintenance

Both versions require the same maintenance:

1. **Update fixture data**: Replace JSON file, call `/api/reload`
2. **Update frontend**: Edit `app/datasheet-generator.html`
3. **Update API**: Modify route handlers
4. **Security patches**: Update dependencies regularly

## Need Both Versions?

You can maintain both! They share:
- ✅ Same data files
- ✅ Same frontend HTML
- ✅ Same assets/images
- ✅ Same API contract

Just keep them in separate directories and update data/frontend in both when needed.

## Getting Help

- Python version: Check Flask documentation
- PHP version: Check PHP.net and TCPDF docs
- Both: See CLAUDE.md in each project
