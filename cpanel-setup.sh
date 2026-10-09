#!/bin/bash

# Quick Setup Script for Cutting Machine CRM on cPanel
# This script automates the initial setup process

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}🚀 Cutting Machine CRM - cPanel Quick Setup${NC}"
echo "=================================================="
echo ""

# Function to check if command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to log messages
log_info() {
    echo -e "${GREEN}[INFO] $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

log_error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

# Check if we're in the right directory
if [ ! -f "artisan" ]; then
    log_error "Laravel artisan file not found. Please run this script from the Laravel project root."
    exit 1
fi

# Step 1: Check Python availability
log_info "Step 1: Checking Python availability..."
if command_exists python3; then
    PYTHON_CMD="python3"
    log_info "Found Python3: $(python3 --version)"
elif command_exists python; then
    PYTHON_CMD="python"
    log_info "Found Python: $(python --version)"
else
    log_error "Python is not available. Please contact your hosting provider to enable Python support."
    exit 1
fi

# Step 2: Check Composer
log_info "Step 2: Checking Composer..."
if ! command_exists composer; then
    log_error "Composer is not available. Please install Composer first."
    exit 1
fi
log_info "Found Composer: $(composer --version | head -n1)"

# Step 3: Set up Python environment
log_info "Step 3: Setting up Python environment..."
if [ ! -d "fastApiProject - python" ]; then
    log_error "Python project directory not found!"
    exit 1
fi

cd "fastApiProject - python"

# Create virtual environment
if [ ! -d "venv" ]; then
    log_info "Creating Python virtual environment..."
    if [ "$PYTHON_CMD" = "python3" ]; then
        python3 -m venv venv
    else
        python -m venv venv
    fi
    log_info "Virtual environment created successfully"
else
    log_warning "Virtual environment already exists"
fi

# Activate virtual environment and install dependencies
log_info "Installing Python dependencies..."
source venv/bin/activate
pip install -r requirements.txt
deactivate

cd ..

# Step 4: Install PHP dependencies
log_info "Step 4: Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Step 5: Install Node.js dependencies (if available)
if command_exists npm; then
    log_info "Step 5: Installing Node.js dependencies..."
    npm install
    npm run build
else
    log_warning "Node.js/npm not available. Skipping frontend asset compilation."
fi

# Step 6: Set up environment file
log_info "Step 6: Setting up environment configuration..."
if [ ! -f ".env" ]; then
    if [ -f ".env.example" ]; then
        cp .env.example .env
        log_info "Created .env file from .env.example"
    else
        log_error ".env.example file not found!"
        exit 1
    fi
else
    log_warning ".env file already exists"
fi

# Step 7: Set permissions
log_info "Step 7: Setting proper permissions..."
chmod -R 755 .
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Step 8: Generate application key
log_info "Step 8: Generating application key..."
php artisan key:generate

# Step 9: Make Python service script executable
log_info "Step 9: Setting up Python service management..."
chmod +x start_python_service.sh

# Step 10: Create update script
log_info "Step 10: Creating update script..."
cat > update_crm.sh << 'EOF'
#!/bin/bash

echo "🔄 Updating Cutting Machine CRM..."

# Stop Python service
./start_python_service.sh stop

# Update PHP dependencies
composer install --no-dev --optimize-autoloader

# Update Node.js dependencies (if available)
if command -v npm &> /dev/null; then
    npm install
    npm run build
fi

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

echo "✅ Update completed!"
EOF

chmod +x update_crm.sh

# Step 11: Display final instructions
echo ""
echo -e "${GREEN}✅ Setup completed successfully!${NC}"
echo ""
echo -e "${BLUE}📋 Next Steps:${NC}"
echo "1. Edit your .env file with your database credentials:"
echo "   nano .env"
echo ""
echo "2. Run database migrations:"
echo "   php artisan migrate --force"
echo ""
echo "3. Start the Python DXF conversion service:"
echo "   ./start_python_service.sh start"
echo ""
echo "4. Check service status:"
echo "   ./start_python_service.sh status"
echo ""
echo -e "${BLUE}🔧 Useful Commands:${NC}"
echo "• Start Python service: ./start_python_service.sh start"
echo "• Stop Python service: ./start_python_service.sh stop"
echo "• Restart Python service: ./start_python_service.sh restart"
echo "• Check service status: ./start_python_service.sh status"
echo "• View logs: ./start_python_service.sh logs"
echo "• Monitor logs: ./start_python_service.sh monitor"
echo "• Update application: ./update_crm.sh"
echo ""
echo -e "${BLUE}📝 Important Notes:${NC}"
echo "• Make sure your database is created in cPanel"
echo "• Update your .env file with correct database credentials"
echo "• Set up cron jobs in cPanel for Laravel scheduler"
echo "• The Python service runs on port 8000"
echo ""
echo -e "${YELLOW}⚠️  Don't forget to:${NC}"
echo "• Configure your domain/subdomain in cPanel"
echo "• Set up SSL certificate"
echo "• Configure .htaccess for Laravel routing"
echo "• Set up regular backups"
echo ""
echo -e "${GREEN}🎉 Your Cutting Machine CRM is ready for configuration!${NC}" 