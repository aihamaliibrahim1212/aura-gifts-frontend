# PRODUCTION DEPLOYMENT - QUICK CHECKLIST

## STEP-BY-STEP DEPLOYMENT (TL;DR Version)

### Phase 1: Preparation (Do These First)
- [ ] Buy domain name
- [ ] Buy VPS or setup cloud account (AWS/DigitalOcean)
- [ ] Point domain DNS to server
- [ ] Note down: Server IP, SSH username, root password
- [ ] Create SSH keys: `ssh-keygen -t ed25519`

### Phase 2: Server Setup (SSH into your server)

```bash
# Copy & paste all commands below in sequence

# 1. Update system
sudo apt update && sudo apt upgrade -y

# 2. Install PHP & extensions
sudo apt install -y php8.3 php8.3-cli php8.3-fpm php8.3-mysql \
  php8.3-mbstring php8.3-xml php8.3-gd php8.3-zip php8.3-curl \
  php8.3-ctype php8.3-tokenizer php8.3-bcmath php8.3-json

# 3. Install other tools
sudo apt install -y mysql-server nginx git zip unzip curl wget
curl -sS https://getcomposer.org/installer | sudo php -- --install-dir=/usr/local/bin --filename=composer

# 4. Create deploy user
sudo useradd -m -s /bin/bash deploy
sudo usermod -aG www-data deploy

# 5. Create app directory
sudo mkdir -p /var/www/aura-gifts
sudo chown deploy:deploy /var/www/aura-gifts

# 6. Setup MySQL
sudo mysql -e "CREATE DATABASE aura_gifts CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'aura_user'@'localhost' IDENTIFIED BY 'STRONG_PASSWORD';"
sudo mysql -e "GRANT ALL PRIVILEGES ON aura_gifts.* TO 'aura_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"
```

### Phase 3: Deploy Application

```bash
# Switch to deploy user
sudo su - deploy

# Clone repo
cd /var/www/aura-gifts
git clone https://github.com/YOUR_GITHUB/aura-gifts.git .

# Go to backend
cd backend

# Install dependencies
composer install --no-dev --optimize-autoloader

# Create .env file
cp .env.example .env

# Edit .env with your values (use nano or vim)
nano .env
```

**Update these .env values:**
```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
DB_USERNAME=aura_user
DB_PASSWORD=STRONG_PASSWORD
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net (or your email provider)
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=YOUR_SENDGRID_KEY
GOOGLE_CLIENT_ID=YOUR_ID
GOOGLE_CLIENT_SECRET=YOUR_SECRET
CLOUDINARY_URL=cloudinary://YOUR_KEY:YOUR_SECRET@YOUR_NAME
```

**Back in terminal:**
```bash
# Generate app key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Clear cache
php artisan config:clear
php artisan cache:clear

# Optimize
php artisan config:cache
php artisan route:cache
php artisan optimize
```

### Phase 4: Nginx Setup (as root/sudo)

```bash
# Exit deploy user
exit

# Create Nginx config
sudo tee /etc/nginx/sites-available/aura-gifts > /dev/null << 'EOF'
# Redirect HTTP to HTTPS
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

# HTTPS
server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    # SSL (we'll get certificates in next step)
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    root /var/www/aura-gifts;
    index index.html index.php;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api {
        try_files $uri /backend/public/index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.env {
        deny all;
    }
}
EOF

# Enable site
sudo ln -s /etc/nginx/sites-available/aura-gifts /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default

# Test config
sudo nginx -t

# Reload Nginx
sudo systemctl restart nginx
```

### Phase 5: SSL Certificate

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-nginx

# Get certificate (replace yourdomain.com)
sudo certbot certonly --nginx -d yourdomain.com -d www.yourdomain.com

# Test auto-renewal
sudo certbot renew --dry-run
```

### Phase 6: Permissions & Firewall

```bash
# Fix permissions
sudo chown -R www-data:www-data /var/www/aura-gifts
chmod -R 755 /var/www/aura-gifts
chmod -R 775 /var/www/aura-gifts/backend/storage/
chmod -R 775 /var/www/aura-gifts/backend/bootstrap/cache/
chmod 600 /var/www/aura-gifts/backend/.env

# Setup firewall
sudo ufw enable
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw status
```

### Phase 7: Setup Backups

```bash
# Create backup script
sudo tee /home/deploy/backup.sh > /dev/null << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/aura-gifts"
mkdir -p $BACKUP_DIR
mysqldump -u aura_user -p'YOUR_PASSWORD' aura_gifts | gzip > $BACKUP_DIR/db_$DATE.sql.gz
find $BACKUP_DIR -name "db_*.sql.gz" -mtime +7 -delete
EOF

# Make executable
sudo chmod +x /home/deploy/backup.sh

# Add to crontab (runs daily at 2 AM)
sudo crontab -e
# Add line: 0 2 * * * /home/deploy/backup.sh
```

---

## VERIFICATION CHECKLIST

After deployment, verify everything works:

```bash
# Check HTTPS
curl -I https://yourdomain.com

# Test API
curl https://yourdomain.com/api/status

# Check logs
sudo tail -f /var/www/aura-gifts/backend/storage/logs/laravel.log
sudo tail -f /var/log/nginx/error.log
```

**Browser Tests:**
- [ ] Homepage loads at https://yourdomain.com
- [ ] Green lock icon (HTTPS working)
- [ ] Products display with images
- [ ] Admin login works
- [ ] No console errors (F12 → Console)

---

## IMPORTANT .ENV VALUES TO REMEMBER

| Variable | Value | Where to Get |
|----------|-------|-------------|
| DB_PASSWORD | Your choice | You set it |
| GOOGLE_CLIENT_ID | From Google Cloud Console | https://console.cloud.google.com |
| GOOGLE_CLIENT_SECRET | From Google Cloud Console | https://console.cloud.google.com |
| CLOUDINARY_URL | From Cloudinary dashboard | https://cloudinary.com/console |
| MAIL_PASSWORD | SendGrid API key | https://sendgrid.com |
| APP_KEY | Generate with `php artisan key:generate` | Auto-generated |

---

## TROUBLESHOOTING - QUICK FIXES

**500 Error:**
```bash
tail -f /var/www/aura-gifts/backend/storage/logs/laravel.log
```

**Database Error:**
```bash
mysql -u aura_user -p aura_gifts
```

**Nginx Error:**
```bash
sudo nginx -t
sudo systemctl restart nginx
```

**Permission Denied:**
```bash
sudo chown -R www-data:www-data /var/www/aura-gifts/backend/storage/
```

**CORS Errors:**
```bash
# Check .env
grep CORS /var/www/aura-gifts/backend/.env
# Then run:
php artisan config:clear
```

---

## USEFUL COMMANDS FOR LATER

```bash
# View logs in real-time
tail -f /var/www/aura-gifts/backend/storage/logs/laravel.log

# Restart services
sudo systemctl restart nginx php8.3-fpm

# Check disk usage
df -h

# Check server status
top

# View running processes
ps aux

# Restart database
sudo systemctl restart mysql

# Clear Laravel cache
php artisan cache:clear

# Deploy new code
cd /var/www/aura-gifts && sudo -u deploy git pull origin main && cd backend && php artisan migrate

# Update certificates (manual)
sudo certbot renew --force-renewal
```

---

## WHAT TO BACKUP BEFORE DEPLOYMENT

1. Current database (if migrating)
2. All configuration files
3. Any custom code not in git

```bash
# Backup before starting
mysqldump -u root -p your_current_db > backup.sql
tar -czf backup-$(date +%Y%m%d).tar.gz /path/to/project
```

---

**Status:** Ready to Deploy! 🚀
