<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Python API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Python FastAPI service that converts DXF files
    | to images.
    |
    */

    'base_url' => env('PYTHON_API_BASE_URL', 'http://localhost:8000'),
    
    'upload_dir' => env('PYTHON_API_UPLOAD_DIR', 'dxf_uploads'),
    
    'converted_dir' => env('PYTHON_API_CONVERTED_DIR', 'dxf_converted'),
    
    /*
    |--------------------------------------------------------------------------
    | Timeout Settings
    |--------------------------------------------------------------------------
    |
    | Timeout settings for API requests to the Python service.
    |
    */
    
    'timeout' => env('PYTHON_API_TIMEOUT', 30),
    
    /*
    |--------------------------------------------------------------------------
    | Retry Settings
    |--------------------------------------------------------------------------
    |
    | Retry settings for failed API requests.
    |
    */
    
    'retry_attempts' => env('PYTHON_API_RETRY_ATTEMPTS', 3),
    
    'retry_delay' => env('PYTHON_API_RETRY_DELAY', 1000), // milliseconds
]; 