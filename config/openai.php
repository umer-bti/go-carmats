<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Configuration
    |--------------------------------------------------------------------------
    |
    | Used by the batch design upload-and-reorder feature for OCR via the
    | Vision API.
    |
    */

    'api_key' => env('OPENAI_API_KEY'),

    'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),

    'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-4o'),

    'timeout' => (int) env('OPENAI_TIMEOUT', 120),

    'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 4096),

    'image_detail' => env('OPENAI_IMAGE_DETAIL', 'high'),
];
