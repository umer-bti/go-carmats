#!/bin/bash

# Python DXF API Service Manager for cPanel
# This script manages the Python FastAPI service for DXF file conversion

SERVICE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PYTHON_DIR="$SERVICE_DIR/fastApiProject - python"
PID_FILE="$SERVICE_DIR/python_service.pid"
LOG_FILE="$SERVICE_DIR/python_service.log"
LOCK_FILE="$SERVICE_DIR/python_service.lock"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to log messages
log_message() {
    echo -e "${GREEN}[$(date '+%Y-%m-%d %H:%M:%S')] $1${NC}"
}

log_error() {
    echo -e "${RED}[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1${NC}"
}

log_warning() {
    echo -e "${YELLOW}[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: $1${NC}"
}

# Function to check if Python is available
check_python() {
    if ! command -v python3 &> /dev/null; then
        if ! command -v python &> /dev/null; then
            log_error "Python is not available. Please contact your hosting provider."
            exit 1
        else
            PYTHON_CMD="python"
        fi
    else
        PYTHON_CMD="python3"
    fi
    
    log_message "Using Python: $PYTHON_CMD"
}

# Function to check if virtual environment exists
check_venv() {
    if [ ! -d "$PYTHON_DIR/venv" ]; then
        log_error "Virtual environment not found. Please run the setup first."
        exit 1
    fi
    
    if [ ! -f "$PYTHON_DIR/venv/bin/activate" ]; then
        log_error "Virtual environment is corrupted. Please recreate it."
        exit 1
    fi
}

# Function to start the service
start_service() {
    log_message "Starting Python DXF API service..."
    
    # Check if service is already running
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            log_warning "Service is already running with PID: $PID"
            return 1
        else
            log_warning "Removing stale PID file"
            rm -f "$PID_FILE"
        fi
    fi
    
    # Check if lock file exists
    if [ -f "$LOCK_FILE" ]; then
        log_warning "Lock file exists. Removing..."
        rm -f "$LOCK_FILE"
    fi
    
    # Check Python and virtual environment
    check_python
    check_venv
    
    # Create log directory if it doesn't exist
    mkdir -p "$(dirname "$LOG_FILE")"
    
    # Navigate to Python directory
    cd "$PYTHON_DIR"
    
    # Activate virtual environment and start service
    source venv/bin/activate
    
    # Check if required packages are installed
    if ! python -c "import fastapi, uvicorn" 2>/dev/null; then
        log_error "Required Python packages not found. Installing..."
        pip install -r requirements.txt
    fi
    
    # Start the service in background
    nohup python start_server.py > "$LOG_FILE" 2>&1 &
    echo $! > "$PID_FILE"
    
    # Wait a moment to check if service started successfully
    sleep 2
    
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            log_message "Python service started successfully with PID: $PID"
            log_message "Log file: $LOG_FILE"
            
            # Test if service is responding
            sleep 3
            if curl -s http://localhost:8000/health > /dev/null 2>&1; then
                log_message "Service is responding to health checks"
            else
                log_warning "Service started but health check failed. Check logs."
            fi
        else
            log_error "Failed to start service. Check logs: $LOG_FILE"
            rm -f "$PID_FILE"
            return 1
        fi
    else
        log_error "Failed to create PID file"
        return 1
    fi
    
    cd "$SERVICE_DIR"
}

# Function to stop the service
stop_service() {
    log_message "Stopping Python DXF API service..."
    
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            log_message "Stopping process with PID: $PID"
            kill $PID
            
            # Wait for process to stop
            for i in {1..10}; do
                if ! ps -p $PID > /dev/null 2>&1; then
                    break
                fi
                sleep 1
            done
            
            # Force kill if still running
            if ps -p $PID > /dev/null 2>&1; then
                log_warning "Force killing process"
                kill -9 $PID
            fi
            
            rm -f "$PID_FILE"
            log_message "Service stopped"
        else
            log_warning "Service is not running (stale PID file)"
            rm -f "$PID_FILE"
        fi
    else
        log_warning "PID file not found"
    fi
    
    # Remove lock file if exists
    if [ -f "$LOCK_FILE" ]; then
        rm -f "$LOCK_FILE"
    fi
}

# Function to restart the service
restart_service() {
    log_message "Restarting Python DXF API service..."
    stop_service
    sleep 2
    start_service
}

# Function to check service status
status_service() {
    if [ -f "$PID_FILE" ]; then
        PID=$(cat "$PID_FILE")
        if ps -p $PID > /dev/null 2>&1; then
            log_message "Service is running with PID: $PID"
            
            # Check if service is responding
            if curl -s http://localhost:8000/health > /dev/null 2>&1; then
                log_message "Service is responding to health checks"
            else
                log_warning "Service is running but not responding to health checks"
            fi
            
            # Show recent logs
            if [ -f "$LOG_FILE" ]; then
                log_message "Recent logs (last 5 lines):"
                tail -5 "$LOG_FILE" | sed 's/^/  /'
            fi
        else
            log_warning "Service is not running (stale PID file)"
            rm -f "$PID_FILE"
        fi
    else
        log_warning "Service is not running"
    fi
}

# Function to show logs
show_logs() {
    if [ -f "$LOG_FILE" ]; then
        log_message "Showing logs (last 20 lines):"
        tail -20 "$LOG_FILE"
    else
        log_warning "Log file not found: $LOG_FILE"
    fi
}

# Function to monitor logs in real-time
monitor_logs() {
    if [ -f "$LOG_FILE" ]; then
        log_message "Monitoring logs in real-time (Ctrl+C to stop):"
        tail -f "$LOG_FILE"
    else
        log_warning "Log file not found: $LOG_FILE"
    fi
}

# Function to install/update dependencies
install_deps() {
    log_message "Installing/updating Python dependencies..."
    
    check_python
    check_venv
    
    cd "$PYTHON_DIR"
    source venv/bin/activate
    
    log_message "Installing requirements..."
    pip install -r requirements.txt
    
    log_message "Dependencies installed successfully"
    cd "$SERVICE_DIR"
}

# Function to create virtual environment
create_venv() {
    log_message "Creating Python virtual environment..."
    
    check_python
    
    if [ -d "$PYTHON_DIR/venv" ]; then
        log_warning "Virtual environment already exists. Removing..."
        rm -rf "$PYTHON_DIR/venv"
    fi
    
    cd "$PYTHON_DIR"
    
    if [ "$PYTHON_CMD" = "python3" ]; then
        python3 -m venv venv
    else
        python -m venv venv
    fi
    
    if [ $? -eq 0 ]; then
        log_message "Virtual environment created successfully"
        install_deps
    else
        log_error "Failed to create virtual environment"
        exit 1
    fi
    
    cd "$SERVICE_DIR"
}

# Function to show help
show_help() {
    echo "Python DXF API Service Manager for cPanel"
    echo ""
    echo "Usage: $0 {start|stop|restart|status|logs|monitor|install|setup|help}"
    echo ""
    echo "Commands:"
    echo "  start     - Start the Python DXF API service"
    echo "  stop      - Stop the Python DXF API service"
    echo "  restart   - Restart the Python DXF API service"
    echo "  status    - Show service status and recent logs"
    echo "  logs      - Show recent logs"
    echo "  monitor   - Monitor logs in real-time"
    echo "  install   - Install/update Python dependencies"
    echo "  setup     - Create virtual environment and install dependencies"
    echo "  help      - Show this help message"
    echo ""
    echo "Files:"
    echo "  PID file: $PID_FILE"
    echo "  Log file: $LOG_FILE"
    echo "  Python dir: $PYTHON_DIR"
}

# Main execution
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
    logs)
        show_logs
        ;;
    monitor)
        monitor_logs
        ;;
    install)
        install_deps
        ;;
    setup)
        create_venv
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|logs|monitor|install|setup|help}"
        exit 1
        ;;
esac 