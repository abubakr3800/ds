# Quick Start Guide - PHP Version

## 1. Requirements Check

Make sure you have:
- ✅ PHP 7.4 or higher
- ✅ Composer (optional, for PDF generation)
- ✅ Apache with mod_rewrite (for production) OR use PHP built-in server (for development)

## 2. Installation

### Windows:
```batch
cd datasheet-generator-php
setup.bat
```

### Linux/Mac:
```bash
cd datasheet-generator-php
chmod +x setup.sh
./setup.sh
```

## 3. Start Development Server

```bash
cd public
php -S localhost:8000
```

## 4. Open in Browser

Navigate to: **http://localhost:8000**

You should see the SC Datasheet Generator interface!

## 5. Test the API

Open these URLs to test:

- Health check: http://localhost:8000/api/health
- Categories: http://localhost:8000/api/categories
- All fixtures: http://localhost:8000/api/fixtures

## 6. Install PDF Support (Optional)

For full PDF generation:

```bash
composer require tecnickcom/tcpdf
```

## 7. Production Deployment

### Apache Configuration:

Create a virtual host:

```apache
<VirtualHost *:80>
    ServerName your-domain.com
    DocumentRoot "E:/AI_projects/dataset/ds/datasheet-generator-php/public"
    
    <Directory "E:/AI_projects/dataset/ds/datasheet-generator-php/public">
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/datasheet-error.log
    CustomLog ${APACHE_LOG_DIR}/datasheet-access.log combined
</VirtualHost>
```

Enable mod_rewrite:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### Nginx Configuration:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/datasheet-generator-php/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Troubleshooting

**Problem**: "Cannot load fixtures data"
- **Solution**: Make sure `data/fixtures_app_data.json` exists

**Problem**: 404 on API endpoints
- **Solution**: Check .htaccess file exists and mod_rewrite is enabled

**Problem**: PDF generation fails
- **Solution**: Run `composer install` to get TCPDF library

**Problem**: Images not showing
- **Solution**: Copy images from original project to `app/images/`

## What's Next?

- Read the full [README.md](README.md) for detailed documentation
- Check [CLAUDE.md](CLAUDE.md) for development guidelines
- Review [sc-brand.md](sc-brand.md) for branding guidelines

## Need Help?

The PHP version mirrors the Python version's API exactly. Any frontend code that worked with Flask will work with this PHP backend.
