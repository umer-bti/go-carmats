# Cutting Machine CRM with DXF to Image Conversion

This Laravel application includes an integrated Python FastAPI service that automatically converts DXF files to images when products are uploaded.

## Features

- **Product Management**: Upload and manage product designs with DXF files
- **Automatic DXF Conversion**: Integrated Python service converts DXF files to JPG images
- **File Storage**: Both original DXF files and converted images are stored for later use
- **Modern UI**: Clean, responsive interface built with Bootstrap and DataTables

## System Requirements

- PHP 8.1 or higher
- Python 3.8 or higher
- Composer
- MySQL/PostgreSQL database
- Node.js (for frontend assets)

## Quick Start

### 1. Clone and Setup

```bash
git clone <repository-url>
cd cutting-machine-crm
```

### 2. Environment Setup

Copy the environment file and configure your database:

```bash
cp .env.example .env
```

Edit `.env` and add the Python API configuration:

```env
# Python API Configuration
PYTHON_API_BASE_URL=http://localhost:8000
PYTHON_API_UPLOAD_DIR=dxf_uploads
PYTHON_API_CONVERTED_DIR=dxf_converted
PYTHON_API_TIMEOUT=30
PYTHON_API_RETRY_ATTEMPTS=3
PYTHON_API_RETRY_DELAY=1000
```

### 3. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Python dependencies
cd "fastApiProject - python"
python3 -m venv venv
source venv/bin/activate
pip install -r requirements.txt
cd ..
```

### 4. Database Setup

```bash
# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate

# Seed the database (optional)
php artisan db:seed
```

### 5. Start Services

Use the provided startup script to start both services:

```bash
./start_services.sh
```

This will start:
- Laravel application on `http://localhost:8001`
- Python FastAPI service on `http://localhost:8000`

## Manual Service Startup

If you prefer to start services manually:

### Start Python FastAPI Service

```bash
cd "fastApiProject - python"
source venv/bin/activate
python start_server.py
```

### Start Laravel Application

```bash
php artisan serve --host=0.0.0.0 --port=8001
```

## Usage

### Adding Products with DXF Files

1. Navigate to the Products section in the admin panel
2. Click "Add New Product File"
3. Fill in the product details:
   - Name
   - Code (optional)
   - Number of Clips (optional)
   - Description (optional)
4. Upload a DXF file
5. Click "Save"

The system will automatically:
- Convert the DXF file to a JPG image
- Store both the original DXF and converted image
- Display the image in the product list

### File Storage

Files are stored in the following structure:
```
storage/app/public/
├── dxf_uploads/          # Original DXF files
├── dxf_converted/        # Converted JPG images
└── uploads/
    ├── product_dxf/      # Product DXF files
    └── product_image/    # Product image files
```

## API Endpoints

### Python FastAPI Service

- `GET /` - Redirect to upload page
- `GET /upload` - Upload form page
- `POST /convert` - Convert DXF file to image

### Laravel API

- `GET /console/products` - Get products list
- `POST /console/products` - Create new product
- `PUT /console/products` - Update product
- `DELETE /console/products` - Delete product

## Configuration

### Python API Configuration

Edit `config/python-api.php` or use environment variables:

```env
PYTHON_API_BASE_URL=http://localhost:8000
PYTHON_API_UPLOAD_DIR=dxf_uploads
PYTHON_API_CONVERTED_DIR=dxf_converted
PYTHON_API_TIMEOUT=30
PYTHON_API_RETRY_ATTEMPTS=3
PYTHON_API_RETRY_DELAY=1000
```

### Python Service Configuration

Edit `fastApiProject - python/app/core/config.py` or use environment variables:

```env
BASE_URL=http://localhost:8000
UPLOAD_DIR=data/uploads
CONVERTED_DIR=data/converted
TEMPLATES_DIR=app/templates
```

## Troubleshooting

### Python Service Issues

1. **Port already in use**: Change the port in `start_server.py` or stop the service using that port
2. **Missing dependencies**: Run `pip install -r requirements.txt` in the Python project directory
3. **Permission issues**: Ensure the Python script has execute permissions

### Laravel Issues

1. **Storage permissions**: Run `php artisan storage:link` to create the storage symlink
2. **Database connection**: Check your `.env` file database configuration
3. **Composer dependencies**: Run `composer install` to install missing packages

### File Conversion Issues

1. **Invalid DXF file**: Ensure the uploaded file is a valid DXF format
2. **Conversion timeout**: Increase the timeout in the Python API configuration
3. **Storage space**: Ensure sufficient disk space for file storage

## Development

### Adding New File Types

To support additional file formats:

1. Update the `DxfConversionService` validation method
2. Modify the Python converter to handle the new format
3. Update the file upload validation rules

### Customizing the Conversion

The DXF to image conversion can be customized by modifying:

- `fastApiProject - python/app/converter.py` - Conversion logic
- `app/Services/DxfConversionService.php` - Laravel integration

## License

This project is proprietary software. All rights reserved.

## Support

For technical support or questions, please contact the development team.
