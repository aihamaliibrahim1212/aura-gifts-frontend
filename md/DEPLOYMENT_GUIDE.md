# Aura Gifts - Production Deployment Guide

Complete guide for deploying to AWS, DigitalOcean, or any VPS hosting.

---

## TABLE OF CONTENTS

1. [Pre-Deployment Checklist](#pre-deployment-checklist)
2. [Environment Setup](#environment-setup)
3. [Database Configuration](#database-configuration)
4. [Security Configuration](#security-configuration)
5. [SSL/HTTPS Setup](#ssl-https-setup)
6. [Deployment Steps](#deployment-steps)
7. [Post-Deployment Verification](#post-deployment-verification)
8. [Monitoring & Maintenance](#monitoring--maintenance)
9. [Troubleshooting](#troubleshooting)

---

## PRE-DEPLOYMENT CHECKLIST

### Before You Start
- [ ] Purchase or setup VPS (AWS EC2, DigitalOcean Droplet, etc.)
- [ ] Register production domain name
- [ ] Setup new email address for admin/notifications
- [ ] Create new GitHub account or generate deployment SSH keys
- [ ] Backup all current data (if migrating from existing)
- [ ] Update DNS provider account credentials

### Code Preparation
- [ ] All security fixes applied (✓ Already done)
- [ ] Database migrations ready (✓ Already done)
- [ ] No hardcoded credentials in code (✓ Verified)
- [ ] All environment variables documented
- [ ] Cache and session configuration optimized
- [ ] Error logging configured

### Infrastructure Decisions
- [ ] Choose hosting provider (AWS/DigitalOcean/Heroku/other)
- [ ] Decide on server size/specs (minimum 2GB RAM recommended)
- [ ] Choose database (MySQL 8.0+ recommended)
- [ ] Setup CDN for static assets (Cloudinary already used ✓)
- [ ] Plan backup strategy
- [ ] Setup email service (SendGrid/AWS SES recommended)

### Domain & DNS
- [ ] Domain pointing to hosting provider nameservers
- [ ] SSL certificate ready (Let's Encrypt free or commercial)
- [ ] A record pointing to server IP
- [ ] MX records for email (if needed)
- [ ] www subdomain setup

---

## ENVIRONMENT SETUP

### Server Requirements

**Recommended Specs:**
- OS: Ubuntu 22.04 LTS or latest stable
- CPU: 2+ cores
- RAM: 2GB minimum, 4GB+ recommended
- Storage: 20GB+ SSD
- Bandwidth: Unlimited or 1TB+ monthly

**Software Stack:**
- PHP 8.3+ (already using)
- MySQL 8.0+ or PostgreSQL 14+
- Nginx or Apache
- Node.js 18+ (for build tools if needed)
- Git
- Composer
- npm

### Initial Server Setup (SSH into your server)

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install dependencies
sudo apt install -y curl wget git zip unzip

# Install PHP 8.3 and extensions
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-gd php8.3-zip php8.3-curl php8.3-ctype php8.3-tokenizer \
  php8.3-bcmath php8.3-json

# Install MySQL
sudo apt install -y mysql-server

# Install Nginx
sudo apt install -y nginx

# Install Composer
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# Create deploy user (non-root)
sudo useradd -m -s /bin/bash deploy
sudo usermod -aG www-data deploy
```

### Create Application Directory

```bash
# Create app directory
sudo mkdir -p /var/www/aura-gifts
sudo chown deploy:deploy /var/www/aura-gifts

# Switch to deploy user
sudo -u deploy bash

# Clone your repository (use deploy SSH key)
cd /var/www/aura-gifts
git clone git@github.com:YOUR_USERNAME/aura-gifts.git .
```

---

## DATABASE CONFIGURATION

### MySQL Setup

```bash
# Login to MySQL
sudo mysql -u root

# Create database and user
CREATE DATABASE aura_gifts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'aura_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON aura_gifts.* TO 'aura_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Configure Laravel

Edit `/var/www/aura-gifts/backend-php/.env`:

```env
APP_NAME="Aura Gifts"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:GENERATE_ME (see below)
APP_URL=https://yourdomain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=aura_gifts
DB_USERNAME=aura_user
DB_PASSWORD=STRONG_PASSWORD_HERE

# Email Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=YOUR_SENDGRID_API_KEY
MAIL_FROM_ADDRESS=noreply@yourdomain.com
MAIL_FROM_NAME="Aura Gifts"

# CORS - Production URLs
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com

# Cloudinary
CLOUDINARY_URL=cloudinary://YOUR_API_KEY:YOUR_API_SECRET@YOUR_CLOUD_NAME

# Google OAuth
GOOGLE_CLIENT_ID=YOUR_GOOGLE_CLIENT_ID
GOOGLE_CLIENT_SECRET=YOUR_GOOGLE_CLIENT_SECRET
GOOGLE_REDIRECT_URI=https://yourdomain.com/api/auth/google/callback
```

### Generate App Key

```bash
cd /var/www/aura-gifts/backend-php
php artisan key:generate
```

### Run Migrations

```bash
cd /var/www/aura-gifts/backend-php
php artisan migrate --force
```

---

## SECURITY CONFIGURATION

### Set Permissions

```bash
# Set correct permissions
cd /var/www/aura-gifts/backend-php
sudo chown -R deploy:www-data .
sudo chmod -R 755 .
sudo chmod -R 775 storage/ bootstrap/cache/

# Ensure .env is not readable by others
chmod 600 .env
```

### Firewall Setup

```bash
# Enable UFW firewall
sudo ufw enable

# Allow SSH
sudo ufw allow 22/tcp

# Allow HTTP
sudo ufw allow 80/tcp

# Allow HTTPS
sudo ufw allow 443/tcp

# Check status
sudo ufw status
```

### SSH Hardening

Edit `/etc/ssh/sshd_config`:

```bash
# Change default port (optional, more secure)
Port 2222

# Disable root login
PermitRootLogin no

# Disable password auth (use keys only)
PasswordAuthentication no

# Restart SSH
sudo systemctl restart ssh
```

### .env Security

- [ ] APP_DEBUG=false (never true in production)
- [ ] APP_ENV=production
- [ ] All credentials in .env file only
- [ ] .env file excluded from git
- [ ] File permissions 600 (.env not world-readable)

---

## SSL/HTTPS SETUP

### Option 1: Let's Encrypt (Free - Recommended)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Get certificate
sudo certbot certonly --nginx -d yourdomain.com -d www.yourdomain.com

# Auto-renewal (already setup by Certbot)
sudo certbot renew --dry-run
```

### Option 2: AWS Certificate Manager (if using AWS)

1. Go to AWS Certificate Manager
2. Request public certificate
3. Validate domain ownership
4. Use in CloudFront/ALB

### Configure Nginx for SSL

Create `/etc/nginx/sites-available/aura-gifts`:

```nginx
# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

# HTTPS Configuration
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    # SSL Certificates
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    # Security headers
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    root /var/www/aura-gifts;
    index index.html index.php;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api {
        try_files $uri /backend-php/public/index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/aura-gifts /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

---

## DEPLOYMENT STEPS

### Step 1: Clone Repository

```bash
cd /var/www/aura-gifts
sudo -u deploy git clone git@github.com:YOUR_USERNAME/aura-gifts.git .
cd backend-php
```

### Step 2: Install Dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### Step 3: Configure Environment

```bash
cp .env.example .env
# Edit .env with production values (see Database Configuration section)
```

### Step 4: Generate Keys and Run Migrations

```bash
php artisan key:generate
php artisan migrate --force
php artisan cache:clear
php artisan config:clear
```

### Step 5: Optimize Application

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### Step 6: Set Permissions

```bash
sudo chown -R www-data:www-data /var/www/aura-gifts
chmod -R 755 /var/www/aura-gifts
chmod -R 775 /var/www/aura-gifts/backend-php/storage
chmod -R 775 /var/www/aura-gifts/backend-php/bootstrap/cache
```

### Step 7: Setup Frontend

If using static files:

```bash
# Copy frontend files
cp -r /var/www/aura-gifts/pages/* /var/www/aura-gifts/public/
cp -r /var/www/aura-gifts/js/* /var/www/aura-gifts/public/js/
cp -r /var/www/aura-gifts/css/* /var/www/aura-gifts/public/css/
cp /var/www/aura-gifts/index.html /var/www/aura-gifts/public/
```

### Step 8: Setup PHP-FPM

Create `/etc/php/8.3/fpm/pool.d/aura.conf`:

```ini
[aura]
user = www-data
group = www-data
listen = /var/run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 5
pm.min_spare_servers = 3
pm.max_spare_servers = 10

catch_workers_output = yes
```

Restart PHP-FPM:

```bash
sudo systemctl restart php8.3-fpm
```

---

## POST-DEPLOYMENT VERIFICATION

### Test API Endpoints

```bash
# Test health check
curl https://yourdomain.com/api/status

# Test CORS
curl -H "Origin: https://yourdomain.com" https://yourdomain.com/api/products

# Test database connection
curl https://yourdomain.com/api/admin/dashboard -H "Authorization: Bearer YOUR_TOKEN"
```

### Verify Security

- [ ] HTTPS working (green lock in browser)
- [ ] No mixed content warnings
- [ ] Security headers present (`curl -I https://yourdomain.com`)
- [ ] CORS restricted to your domain
- [ ] .env file not accessible
- [ ] Debug mode is OFF

### Check Application

- [ ] Homepage loads
- [ ] Products display correctly
- [ ] Login/Register working
- [ ] Admin panel accessible
- [ ] Images loading from Cloudinary
- [ ] No console errors in browser

### Database Verification

```bash
mysql -u aura_user -p aura_gifts

# Check tables
SHOW TABLES;

# Verify migrations ran
SELECT * FROM migrations;

# Count records
SELECT COUNT(*) FROM products;
SELECT COUNT(*) FROM users;
```

---

## MONITORING & MAINTENANCE

### Setup Backup (Critical!)

#### Daily Database Backups

```bash
# Create backup script: /home/deploy/backup.sh
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/aura-gifts"
mkdir -p $BACKUP_DIR

mysqldump -u aura_user -p'PASSWORD' aura_gifts | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Keep only last 7 days
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +7 -delete
```

Add to crontab:

```bash
crontab -e
# Add line:
0 2 * * * /home/deploy/backup.sh
```

#### Upload to S3 (Offsite Backup)

```bash
# Install AWS CLI
sudo apt install -y awscli

# Configure AWS credentials
aws configure

# Add to backup script to upload to S3
aws s3 cp $BACKUP_DIR/db_$DATE.sql.gz s3://your-backup-bucket/
```

### Setup Monitoring

#### Email Alerts

Create `/var/www/aura-gifts/backend-php/app/Console/Kernel.php` schedule:

```php
$schedule->command('queue:work')->everyMinute()->onFailure(function () {
    Mail::raw('Queue worker failed!', function ($message) {
        $message->to('admin@yourdomain.com')->subject('Alert: Queue Failed');
    });
});
```

#### Log Monitoring

```bash
# Setup logrotate for Laravel logs
sudo nano /etc/logrotate.d/aura-gifts
```

Content:

```
/var/www/aura-gifts/backend-php/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

#### Disk Space Alerts

```bash
# Monitor disk usage
df -h

# Setup alert (add to crontab)
@daily df -h / | awk 'NR==2 {if ($5+0 > 80) print "Disk usage high"}' | mail -s "Disk Alert" admin@yourdomain.com
```

### Regular Maintenance Tasks

**Weekly:**
- [ ] Review error logs: `/var/www/aura-gifts/backend-php/storage/logs/`
- [ ] Check disk usage: `df -h`
- [ ] Verify backups completed

**Monthly:**
- [ ] Update system: `sudo apt update && sudo apt upgrade`
- [ ] Update PHP packages: `composer update`
- [ ] Review security updates
- [ ] Test restore from backup

**Quarterly:**
- [ ] Security audit
- [ ] Performance review
- [ ] Load testing

---

## TROUBLESHOOTING

### Common Issues & Solutions

#### 1. 500 Internal Server Error

```bash
# Check error logs
tail -f /var/www/aura-gifts/backend-php/storage/logs/laravel.log

# Check Nginx logs
sudo tail -f /var/log/nginx/error.log

# Check permissions
ls -la /var/www/aura-gifts/backend-php/storage/
ls -la /var/www/aura-gifts/backend-php/bootstrap/

# Fix permissions
sudo chmod -R 775 /var/www/aura-gifts/backend-php/storage/
sudo chmod -R 775 /var/www/aura-gifts/backend-php/bootstrap/cache/
```

#### 2. Database Connection Error

```bash
# Test connection
mysql -h 127.0.0.1 -u aura_user -p -e "SELECT 1;"

# Check .env file
cat /var/www/aura-gifts/backend-php/.env | grep DB_

# Verify MySQL running
sudo systemctl status mysql

# Restart MySQL if needed
sudo systemctl restart mysql
```

#### 3. CORS Errors

Check `.env`:
```bash
grep CORS_ALLOWED_ORIGINS /var/www/aura-gifts/backend-php/.env
```

Should include your domain:
```
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
```

Then run:
```bash
php artisan config:clear
php artisan cache:clear
```

#### 4. SSL Certificate Issues

```bash
# Check certificate expiry
sudo certbot certificates

# Renew manually
sudo certbot renew --force-renewal

# Test auto-renewal
sudo certbot renew --dry-run
```

#### 5. Email Not Sending

```bash
# Check mail queue
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Check credentials in .env
grep MAIL_ /var/www/aura-gifts/backend-php/.env
```

#### 6. Git Deployment Issues

```bash
# Verify SSH key
ssh -T git@github.com

# Fix permissions
sudo chown -R deploy:deploy /var/www/aura-gifts/.git

# Pull updates
cd /var/www/aura-gifts
sudo -u deploy git pull origin main
```

---

## NEW PC SETUP (For New Development Machine)

When setting up on a new PC to continue development:

### 1. Install Prerequisites

```bash
# macOS
brew install php@8.3 mysql composer node

# Ubuntu/Linux
sudo apt install php8.3 php8.3-mysql composer nodejs

# Windows
# Download from https://windows.php.net/download
# Download MySQL from https://dev.mysql.com/downloads/mysql/
# Install Node from https://nodejs.org/
```

### 2. Clone Repository

```bash
git clone git@github.com:YOUR_USERNAME/aura-gifts.git
cd aura-gifts
```

### 3. Setup Local Environment

```bash
cd backend-php

# Create .env from production
cp .env.example .env

# Edit for local development
MAIL_MAILER=log  # Log emails instead of sending
APP_DEBUG=true
APP_ENV=local
CORS_ALLOWED_ORIGINS=http://localhost:8000
```

### 4. Install and Run

```bash
composer install
php artisan key:generate
php artisan migrate
php artisan serve

# In another terminal
cd ..
# Serve frontend files or use a simple server
python3 -m http.server 8001
```

---

## BACKUP & RECOVERY CHECKLIST

### Before Going Live - Backup Current Data

```bash
# Export current database (from localhost)
mysqldump -u root -p aura_gifts > aura-gifts-backup.sql

# Zip everything
tar -czf aura-gifts-backup-$(date +%Y%m%d).tar.gz aura-gifts/ aura-gifts-backup.sql

# Store in safe location (Google Drive, AWS S3, etc)
```

### Disaster Recovery Procedure

If something goes wrong:

```bash
# SSH into server
ssh deploy@yourdomain.com

# Stop services
sudo systemctl stop nginx
sudo systemctl stop php8.3-fpm

# Restore database
mysql -u aura_user -p aura_gifts < backup.sql

# Restore files
rm -rf /var/www/aura-gifts/*
git clone ... /var/www/aura-gifts

# Restart services
sudo systemctl start php8.3-fpm
sudo systemctl start nginx
```

---

## FINAL CHECKLIST BEFORE LAUNCHING

- [ ] Domain pointing to server
- [ ] SSL certificate installed and auto-renewing
- [ ] Database migrated and verified
- [ ] All .env variables set correctly
- [ ] Admin account created and tested
- [ ] Email sending configured and tested
- [ ] Backup system running
- [ ] Firewall configured
- [ ] SSH hardened
- [ ] Monitoring alerts setup
- [ ] Error logging enabled
- [ ] CORS configured for production domain
- [ ] APP_DEBUG = false
- [ ] All API endpoints tested
- [ ] Homepage loads correctly
- [ ] Images displaying from Cloudinary
- [ ] Login/Register working
- [ ] Admin panel functional
- [ ] Security headers present
- [ ] No console errors in browser
- [ ] Load testing completed
- [ ] Performance acceptable
- [ ] Database indexed for queries
- [ ] Cron jobs scheduled if needed

---

## SUPPORT RESOURCES

- Laravel Docs: https://laravel.com/docs
- DigitalOcean: https://www.digitalocean.com/docs
- AWS: https://docs.aws.amazon.com/
- Nginx: https://nginx.org/en/docs/
- MySQL: https://dev.mysql.com/doc/
- Let's Encrypt: https://letsencrypt.org/docs/

---

**Last Updated:** 2026-06-11
**Version:** 1.0
