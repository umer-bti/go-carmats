# cPanel Deployment Guide for Cutting Machine CRM

This guide is specifically designed for cPanel hosting with terminal access, where you can't use the Software installer for Python applications.

## Prerequisites

- cPanel hosting with SSH/terminal access
- Python support enabled (contact your hosting provider if not available)
- MySQL database access
- Domain or subdomain configured

## Step 1: Access Your Server

### Via SSH (Recommended)
```bash
ssh username@your-server.com
# or
ssh username@your-server-ip
```

### Via cPanel Terminal
1. Log into cPanel
2. Find "Terminal" or "SSH Access" in the Advanced section
3. Click to open terminal

## Step 2: Check Python Availability

```bash
# Check Python version
python3 --version
# or
python --version

# Check pip availability
pip3 --version
# or
pip --version

# Check if virtual environments are supported
python3 -m venv --help
```

If Python is not available, contact your hosting provider to enable it.

## Step 3: Navigate to Your Domain Directory

```bash
# Navigate to your public_html or domain directory
cd public_html
# or
cd domains/yourdomain.com/public_html
# or wherever your hosting provider places web files
```

## Step 4: Upload Your Application

### Option A: Upload via FTP/SFTP first, then extract
```bash
# If you uploaded a zip file
unzip cutting-machine-crm.zip

# If you uploaded individual files, they should already be there
ls -la
```

### Option B: Clone from Git (if available)
```bash
git clone https://github.com/your-repo/cutting-machine-crm.git
cd cutting-machine-crm
```

## Step 5: Set Up Python Environment

```bash
# Navigate to the Python project directory
cd "fastApiProject - python"

# Create virtual environment
python3 -m venv venv

# Activate virtual environment
source venv/bin/activate

# Install Python dependencies
pip install -r requirements.txt

# Test if the Python service works
python start_server.py &
# This should start the service on port 8000
# Press Ctrl+C to stop it for now

# Deactivate virtual environment
deactivate

# Go back to main directory
cd ..
```

## Step 6: Install PHP Dependencies

```bash
# Install Composer dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies (if Node.js is available)
npm install
npm run build
```

## Step 7: Configure Environment

```bash
# Copy environment file
cp .env.example .env

# Edit environment file
nano .env
```

Update the `.env` file with your cPanel-specific settings:

```env
APP_NAME="Cutting Machine CRM"
APP_ENV=production
APP_KEY=base64:your_generated_key
APP_DEBUG=false
APP_URL=https://yourdomain.com

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=error

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=your_cpanel_db_name
DB_USERNAME=your_cpanel_db_user
DB_PASSWORD=your_cpanel_db_password

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

# Queue (optional)
QUEUE_CONNECTION=database
```

## Step 8: Set Permissions

```bash
# Set proper permissions for Laravel
chmod -R 755 .
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Make sure the web server can write to these directories
chown -R $(whoami):$(whoami) .
```

## Step 9: Generate Application Key and Run Migrations

```bash
# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate --force

# Seed the database (optional)
php artisan db:seed --force

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Step 10: Create Python Service Script

Since cPanel doesn't support systemd services, we'll create a custom script to manage the Python service:

```bash
# Create a Python service management script
nano start_python_service.sh
```

Add the following content:

```bash
#!/bin/bash

# Python DXF API Service Manager for cPanel
SERVICE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PYTHON_DIR="$SERVICE_DIR/fastApiProject - python"
PID_FILE="$SERVICE_DIR/python_service.pid"
LOG_FILE="$SERVICE_DIR/python_service.log"

start_service() {
    echo "Starting Python DXF API service..."
    
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            echo "Service is already running with PID: $PID"
            return 1
        else
            rm -f "$PID_FILE"
        fi
    fi
    
    cd "$PYTHON_DIR"
    source venv/bin/activate
    
    # Start the service in background
    nohup python start_server.py > "$LOG_FILE" 2>&1 &
    echo $! > "$PID_FILE"
    
    echo "Python service started with PID: $(cat $PID_FILE)"
    echo "Log file: $LOG_FILE"
}

stop_service() {
    echo "Stopping Python DXF API service..."
    
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            kill $PID
            rm -f "$PID_FILE"
            echo "Service stopped"
        else
            echo "Service is not running"
            rm -f "$PID_FILE"
        fi
    else
        echo "PID file not found"
    fi
}

restart_service() {
    stop_service
    sleep 2
    start_service
}

status_service() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            echo "Service is running with PID: $PID"
        else
            echo "Service is not running (stale PID file)"
        fi
    else
        echo "Service is not running"
    fi
}

case "$1" in
    start)
        start_service
        ;;
    stop)
        stop_service
        ;;
    restart)
        restart_service
        ;;
    status)
        status_service
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status}"
        exit 1
        ;;
esac
```

Make it executable:

```bash
chmod +x start_python_service.sh
```

## Step 11: Start the Python Service

```bash
# Start the Python service
./start_python_service.sh start

# Check if it's running
./start_python_service.sh status
```

## Step 12: Configure .htaccess for Laravel

Create or update the `.htaccess` file in your public directory:

```bash
nano public/.htaccess
```

Add the following content:

```apache
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

## Step 13: Set Up Cron Jobs

In cPanel, go to "Cron Jobs" and add:

```bash
# Laravel Scheduler (runs every minute)
* * * * * cd /home/username/public_html && php artisan schedule:run >> /dev/null 2>&1

# Restart Python service daily (optional, for stability)
0 2 * * * cd /home/username/public_html && ./start_python_service.sh restart >> /dev/null 2>&1
```

## Step 14: Test Your Application

1. **Test Laravel Application**: Visit `https://yourdomain.com`
2. **Test Python API**: Try uploading a DXF file through the application
3. **Check Logs**: Monitor the Python service log file

```bash
# Check Python service logs
tail -f python_service.log

# Check Laravel logs
tail -f storage/logs/laravel.log
```

## Step 15: Create Update Script

```bash
nano update_crm.sh
```

Add the following content:

```bash
#!/bin/bash

echo "Updating Cutting Machine CRM..."

# Stop Python service
./start_python_service.sh stop

# Update application (if using git)
# git pull origin main

# Update PHP dependencies
composer install --no-dev --optimize-autoloader

# Update Node.js dependencies (if available)
npm install
npm run build

# Run migrations
php artisan migrate --force

# Clear and rebuild caches
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Start Python service
./start_python_service.sh start

echo "Update completed!"
```

Make it executable:

```bash
chmod +x update_crm.sh
```

## Troubleshooting

### Common Issues

1. **Python Not Available**
   ```bash
   # Contact your hosting provider to enable Python
   # Or check if it's available under a different name
   which python3
   which python
   ```

2. **Permission Denied**
   ```bash
   # Fix permissions
   chmod -R 755 .
   chmod -R 775 storage bootstrap/cache
   ```

3. **Database Connection Issues**
   ```bash
   # Check database credentials in .env
   # Verify database exists in cPanel
   ```

4. **Python Service Not Starting**
   ```bash
   # Check logs
   tail -f python_service.log
   
   # Check if port 8000 is available
   netstat -tlnp | grep :8000
   ```

5. **File Upload Issues**
   ```bash
   # Check storage directory permissions
   chmod -R 775 storage
   ```

### Useful Commands

```bash
# Check service status
./start_python_service.sh status

# Restart services
./start_python_service.sh restart

# View real-time logs
tail -f python_service.log
tail -f storage/logs/laravel.log

# Update application
./update_crm.sh
```

## Security Considerations

1. **Keep .env file secure**: Ensure it's not publicly accessible
2. **Regular updates**: Run the update script regularly
3. **Monitor logs**: Check for any suspicious activity
4. **Backup database**: Use cPanel's backup tools

Your application should now be accessible at `https://yourdomain.com` with the Python DXF conversion service running in the background! 