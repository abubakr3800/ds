# SC Datasheet Generator - PHP Version

Welcome to the PHP version of the SC Datasheet Generator!

## 📚 Documentation Index

Start here based on what you need:

### 🚀 Getting Started
- **[QUICKSTART.md](QUICKSTART.md)** - Get running in 5 minutes
- **[README.md](README.md)** - Full setup and usage guide
- **[setup.bat](setup.bat)** / **[setup.sh](setup.sh)** - Automated setup scripts

### 🔄 Migrating from Python
- **[MIGRATION.md](MIGRATION.md)** - Python to PHP migration guide
- **[PROJECT_SUMMARY.md](PROJECT_SUMMARY.md)** - What was built and why

### 👨‍💻 Development
- **[CLAUDE.md](CLAUDE.md)** - Development guidelines and architecture
- **[config.php](config.php)** - Application configuration
- **[composer.json](composer.json)** - PHP dependencies

### 🎨 Branding
- **[sc-brand.md](sc-brand.md)** - Brand guidelines and design system

## 📁 Quick Reference

### Project Structure
```
├── public/           # Web root (point Apache here)
├── api/             # Backend API handlers
├── app/             # Frontend HTML & assets
├── data/            # Fixture database (JSON)
└── assets/          # Static assets (logos, etc.)
```

### Key Files
- **Entry point**: `public/index.php`
- **API routes**: `api/routes.php`
- **Data loader**: `api/data_loader.php`
- **PDF generator**: `api/pdf_generator.php`
- **Frontend**: `app/datasheet-generator.html`

## ⚡ Quick Commands

### Start Development Server
```bash
cd public
php -S localhost:8000
```

### Install Dependencies
```bash
composer install
```

### Test API
```bash
curl http://localhost:8000/api/health
```

## 🎯 What This Does

An interactive web application for browsing and generating technical datasheets for Short Circuit Company's 87 lighting fixture products.

**Features:**
- Browse fixtures by category
- Search and filter by specs
- View detailed specifications
- Generate professional PDF datasheets
- Dark/light theme support

## 🔗 Quick Links

- **API Documentation**: See [README.md#api-endpoints](README.md)
- **Troubleshooting**: See [QUICKSTART.md#troubleshooting](QUICKSTART.md)
- **Production Setup**: See [README.md#production-deployment](README.md)

## ✅ Status

**Production Ready** - Fully functional PHP port with 100% API compatibility with the Python/Flask version.

---

**Need help?** Start with [QUICKSTART.md](QUICKSTART.md) or [README.md](README.md)
