# VPS Deployment Guide for Cutting Machine CRM

## 1. Server Preparation

### Update System
```bash
sudo apt update && sudo apt upgrade -y
```

### Install Required Packages
```bash
# Install PHP 8.2 and extensions
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-xml php8.2-curl php8.2-mbstring php8.2-zip php8.2-gd php8.2-bcmath php8.2-intl php8.2-soap php8.2-redis

# Install Python 3.9+
sudo apt install -y python3 python3-pip python3-venv

# Install MySQL
sudo apt install -y mysql-server

# Install Nginx
sudo apt install -y nginx

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs
```

## 2. Database Setup

### Create Database and User
```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE cutting_machine_crm;
CREATE USER 'crm_user'@'localhost' IDENTIFIED BY 'your_secure_password';
GRANT ALL PRIVILEGES ON cutting_machine_crm.* TO 'crm_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 3. Application Deployment

### Clone/Upload Application
```bash
# Create application directory
sudo mkdir -p /var/www/cutting-machine-crm
sudo chown $USER:$USER /var/www/cutting-machine-crm

# Upload your application files to this directory
# Or clone from git repository
cd /var/www/cutting-machine-crm
```

### Set Permissions
```bash
sudo chown -R www-data:www-data /var/www/cutting-machine-crm
sudo chmod -R 755 /var/www/cutting-machine-crm
sudo chmod -R 775 /var/www/cutting-machine-crm/storage
sudo chmod -R 775 /var/www/cutting-machine-crm/bootstrap/cache
```

### Install Dependencies
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies and build assets
npm install
npm run build

# Install Python dependencies
cd "fastApiProject - python"
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
cd ..
```

## 4. Environment Configuration

### Create Environment File
```bash
cp .env.example .env
```

### Configure .env File
```env
APP_NAME="Cutting Machine CRM"
APP_ENV=production
APP_KEY=base64:your_generated_key
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cutting_machine_crm
DB_USERNAME=crm_user
DB_PASSWORD=your_secure_password

# Python API Configuration
PYTHON_API_BASE_URL=http://localhost:8000
PYTHON_API_UPLOAD_DIR=dxf_uploads
PYTHON_API_CONVERTED_DIR=dxf_converted
PYTHON_API_TIMEOUT=30
PYTHON_API_RETRY_ATTEMPTS=3
PYTHON_API_RETRY_DELAY=1000

# Cache and Session
CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_LIFETIME=120

# Queue (optional, for background jobs)
QUEUE_CONNECTION=database
```

### Generate Application Key
```bash
php artisan key:generate
```

### Run Database Migrations
```bash
php artisan migrate --force
php artisan db:seed --force
```

## 5. Nginx Configuration

### Create Nginx Site Configuration
```bash
sudo nano /etc/nginx/sites-available/cutting-machine-crm
```

Add the following configuration:
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/cutting-machine-crm/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Enable Site
```bash
sudo ln -s /etc/nginx/sites-available/cutting-machine-crm /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## 6. Python Service Setup

### Create Systemd Service for Python API
```bash
sudo nano /etc/systemd/system/python-dxf-api.service
```

Add the following content:
```ini
[Unit]
Description=Python DXF Conversion API
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/cutting-machine-crm/fastApiProject - python
Environment=PATH=/var/www/cutting-machine-crm/fastApiProject - python/venv/bin
ExecStart=/var/www/cutting-machine-crm/fastApiProject - python/venv/bin/python start_server.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### Enable and Start Python Service
```bash
sudo systemctl daemon-reload
sudo systemctl enable python-dxf-api
sudo systemctl start python-dxf-api
```

## 7. Laravel Queue Setup (Optional)

### Create Queue Worker Service
```bash
sudo nano /etc/systemd/system/laravel-queue.service
```

Add the following content:
```ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
Group=www-data
WorkingDirectory=/var/www/cutting-machine-crm
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### Enable Queue Worker
```bash
sudo systemctl enable laravel-queue
sudo systemctl start laravel-queue
```

## 8. SSL Certificate (Optional but Recommended)

### Install Certbot
```bash
sudo apt install -y certbot python3-certbot-nginx
```

### Obtain SSL Certificate
```bash
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
```

## 9. Final Configuration

### Optimize Laravel
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Set Up Cron Job for Laravel Scheduler
```bash
crontab -e
```

Add this line:
```
* * * * * cd /var/www/cutting-machine-crm && php artisan schedule:run >> /dev/null 2>&1
```

## 10. Security Considerations

### Configure Firewall
```bash
sudo ufw allow 'Nginx Full'
sudo ufw allow OpenSSH
sudo ufw enable
```

### Secure MySQL
```bash
sudo mysql_secure_installation
```

### Regular Updates
```bash
# Create update script
sudo nano /usr/local/bin/update-crm.sh
```

Add the following content:
```bash
#!/bin/bash
cd /var/www/cutting-machine-crm
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
sudo systemctl restart python-dxf-api
sudo systemctl restart laravel-queue
```

Make it executable:
```bash
sudo chmod +x /usr/local/bin/update-crm.sh
```

## 11. Monitoring and Logs

### Check Service Status
```bash
sudo systemctl status nginx
sudo systemctl status php8.2-fpm
sudo systemctl status python-dxf-api
sudo systemctl status laravel-queue
```

### View Logs
```bash
# Nginx logs
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log

# Laravel logs
tail -f /var/www/cutting-machine-crm/storage/logs/laravel.log

# Python API logs
sudo journalctl -u python-dxf-api -f
```

## Troubleshooting

### Common Issues:

1. **Permission Errors**: Ensure proper ownership and permissions
2. **Database Connection**: Verify database credentials and connectivity
3. **Python Service**: Check if all dependencies are installed
4. **File Uploads**: Ensure storage directory is writable
5. **SSL Issues**: Verify domain configuration and certificate

### Performance Optimization:

1. **Enable OPcache** for PHP
2. **Configure Redis** for caching
3. **Optimize MySQL** configuration
4. **Use CDN** for static assets
5. **Enable Gzip** compression in Nginx

Your application should now be accessible at `https://your-domain.com` (or `http://your-domain.com` if SSL is not configured). 