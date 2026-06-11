# Aura Gifts - Setup Guide

## New PC Installation

### Prerequisites

Install these first:

- **PHP 8.3** (with mysql, curl, json, xml extensions)
- **MySQL** (or MariaDB, or use existing XAMPP)
- **Composer** (PHP package manager)
- **Git**

### Step-by-Step Setup

#### 1. Clone Repository
```bash
git clone https://github.com/aihamaliibrahim1212/aura-gifts.git
cd aura-gifts
```

#### 2. Install PHP Dependencies
```bash
composer install
```

#### 3. Create Environment File
```bash
cp backend/.env.example backend/.env
```

**Check `.env` file has these values (should be pre-configured):**
```
APP_NAME="Aura Gifts"
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost:8000

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=auragifts
DB_USERNAME=root
DB_PASSWORD=                    # Leave empty if no password
```

If using different MySQL credentials, update them in `.env`.

#### 4. Generate Laravel Key
```bash
php backend/artisan key:generate
```

#### 5. Create Database
```bash
mysql -u root -e "CREATE DATABASE auragifts;"
```

#### 6. Run Database Migrations
```bash
php backend/artisan migrate --seed
```

#### 7. Start the Server
```bash
php backend/artisan serve
```

### Access the Site

- **Frontend:** http://localhost:8000
- **Admin Panel:** http://localhost:8000/admin/dashboard.html
- **phpMyAdmin:** http://localhost/phpmyadmin (if using XAMPP)

### Database Access

- **Host:** localhost
- **Username:** root
- **Password:** (leave empty or your MySQL password)
- **Database:** auragifts

### Troubleshooting

**Port 8000 already in use?**
```bash
php backend/artisan serve --port=8001
```

**Database connection error?**
Check `.env` file has correct DB credentials

**Missing dependencies?**
```bash
composer install
composer update
```

---

## File Structure

```
aura-gifts/
├── backend/          # Laravel API backend
│   ├── app/         # Controllers, Models, Middleware
│   ├── routes/      # API routes
│   ├── .env         # Configuration (DO NOT COMMIT)
│   └── artisan      # CLI tool
├── frontend/        # HTML/CSS/JavaScript frontend
│   ├── js/          # JavaScript files
│   ├── css/         # Stylesheets
│   ├── pages/       # HTML pages
│   └── index.html   # Homepage
└── md/              # Documentation files
```

## First Time Tips

1. **Admin Login:** Check `admin_users` table in phpMyAdmin
2. **Add Products:** Use admin panel at `/admin/dashboard.html`
3. **Database:** All data is stored in MySQL, manageable via phpMyAdmin
4. **Frontend:** Served directly from backend at port 8000
5. **No GitHub Pages:** Local development only

## Important Notes

- Do NOT commit `.env` file to Git
- API calls go to `http://localhost:8000`
- Database migrations handle table creation
- All frontend files are served by the backend
