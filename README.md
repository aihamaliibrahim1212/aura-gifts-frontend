# Aura Gifts - E-commerce Platform

A full-stack gifting platform with Laravel backend and static HTML/CSS/JavaScript frontend.

## Quick Start

**Read:** [`md/SETUP.md`](md/SETUP.md) for complete installation instructions.

## TL;DR

```bash
# 1. Clone repo
git clone https://github.com/aihamaliibrahim1212/aura-gifts.git
cd aura-gifts

# 2. Install & Setup
composer install
cp backend/.env.example backend/.env
php backend/artisan key:generate
mysql -u root -e "CREATE DATABASE auragifts;"
php backend/artisan migrate --seed

# 3. Run
php backend/artisan serve

# 4. Open
http://localhost:8000
```

## Requirements

- PHP 8.3+
- MySQL 5.7+
- Composer
- Git

## Documentation

All guides in [`md/`](md/) folder:
- **SETUP.md** — Installation guide for new PC
- **DEV_SETUP.md** — Local development workflow
- **DEPLOYMENT_GUIDE.md** — Deploy to DigitalOcean
- **QUICK_DEPLOY.md** — Fast deployment checklist

## Project Structure

```
aura-gifts/
├── backend/          Laravel API backend
├── frontend/         HTML/CSS/JavaScript frontend
├── md/               Documentation
└── README.md         This file
```

## Features

- ✓ Product catalog with hampers
- ✓ Shopping cart & checkout
- ✓ User authentication (Google OAuth)
- ✓ Admin panel (manage products, orders, reviews)
- ✓ Customer reviews & ratings
- ✓ Banner management
- ✓ FAQ section
- ✓ Responsive design

## Default Admin Access

Check `admin_users` table in phpMyAdmin after running migrations.

## Database

- **Host:** localhost
- **Username:** root
- **Password:** (leave empty)
- **Database:** auragifts

Access via phpMyAdmin: http://localhost/phpmyadmin

## Support

All questions answered in the documentation files.

---

**Created:** 2026  
**License:** Private
