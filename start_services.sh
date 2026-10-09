#!/bin/bash

# Start Services Script for Cutting Machine CRM
# This script starts both the Laravel application and Python DXF conversion service

echo "🚀 Starting Cutting Machine CRM Services..."

# Function to check if a port is in use
check_port() {
    if lsof -Pi :$1 -sTCP:LISTEN -t >/dev/null ; then
        echo "⚠️  Port $1 is already in use"
        return 1
    else
        return 0
    fi
}

# Function to start Python FastAPI service
start_python_service() {
    echo "🐍 Starting Python DXF conversion service..."
    
    if [ ! -d "fastApiProject - python" ]; then
        echo "❌ Python project directory not found!"
        return 1
    fi
    
    cd "fastApiProject - python"
    
    # Check if virtual environment exists, create if not
    if [ ! -d "venv" ]; then
        echo "📦 Creating Python virtual environment..."
        python3 -m venv venv
    fi
    
    # Activate virtual environment
    source venv/bin/activate
    
    # Install dependencies
    echo "📦 Installing Python dependencies..."
    pip install -r requirements.txt
    
    # Check if port 8000 is available
    if check_port 8000; then
        echo "🌐 Starting FastAPI service on port 8000..."
        python start_server.py &
        PYTHON_PID=$!
        echo "✅ Python service started with PID: $PYTHON_PID"
        echo $PYTHON_PID > ../python_service.pid
    else
        echo "❌ Port 8000 is already in use. Please stop the service using that port."
        return 1
    fi
    
    cd ..
}

# Function to start Laravel application
start_laravel_service() {
    echo "🔄 Starting Laravel application..."
    
    # Check if .env file exists
    if [ ! -f ".env" ]; then
        echo "❌ .env file not found! Please create one from .env.example"
        return 1
    fi
    
    # Install PHP dependencies if needed
    if [ ! -d "vendor" ]; then
        echo "📦 Installing PHP dependencies..."
        composer install
    fi
    
    # Generate application key if not set
    if ! grep -q "APP_KEY=base64:" .env; then
        echo "🔑 Generating application key..."
        php artisan key:generate
    fi
    
    # Run migrations
    echo "🗄️  Running database migrations..."
    php artisan migrate --force
    
    # Check if port 8000 is available for Laravel (we'll use 8001)
    if check_port 8001; then
        echo "🌐 Starting Laravel service on port 8001..."
        php artisan serve --host=0.0.0.0 --port=8001 &
        LARAVEL_PID=$!
        echo "✅ Laravel service started with PID: $LARAVEL_PID"
        echo $LARAVEL_PID > laravel_service.pid
    else
        echo "❌ Port 8001 is already in use. Please stop the service using that port."
        return 1
    fi
}

# Function to stop services
stop_services() {
    echo "🛑 Stopping services..."
    
    # Stop Python service
    if [ -f "python_service.pid" ]; then
        PYTHON_PID=$(cat python_service.pid)
        if kill -0 $PYTHON_PID 2>/dev/null; then
            kill $PYTHON_PID
            echo "✅ Python service stopped"
        fi
        rm -f python_service.pid
    fi
    
    # Stop Laravel service
    if [ -f "laravel_service.pid" ]; then
        LARAVEL_PID=$(cat laravel_service.pid)
        if kill -0 $LARAVEL_PID 2>/dev/null; then
            kill $LARAVEL_PID
            echo "✅ Laravel service stopped"
        fi
        rm -f laravel_service.pid
    fi
}

# Main script logic
case "${1:-start}" in
    start)
        start_python_service
        if [ $? -eq 0 ]; then
            start_laravel_service
            if [ $? -eq 0 ]; then
                echo ""
                echo "🎉 All services started successfully!"
                echo "📱 Laravel application: http://localhost:8001"
                echo "🐍 Python API service: http://localhost:8000"
                echo ""
                echo "Press Ctrl+C to stop all services"
                
                # Wait for interrupt signal
                trap stop_services INT
                wait
            else
                echo "❌ Failed to start Laravel service"
                stop_services
                exit 1
            fi
        else
            echo "❌ Failed to start Python service"
            exit 1
        fi
        ;;
    stop)
        stop_services
        ;;
    restart)
        stop_services
        sleep 2
        $0 start
        ;;
    *)
        echo "Usage: $0 {start|stop|restart}"
        echo "  start   - Start all services (default)"
        echo "  stop    - Stop all services"
        echo "  restart - Restart all services"
        exit 1
        ;;
esac 