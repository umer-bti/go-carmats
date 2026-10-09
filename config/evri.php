<?php

return [
    'api_key' => env('EVRI_API_KEY', null),
    'api_secret' => env('EVRI_SECRET', null),

    'max_label_print_count' => 5,

    'label_url' => env(
        'EVRI_LABEL_URL',
        'https://www.hermes-europe.co.uk/routing/service/rest/v4/routeDeliveryCreatePreadviceAndLabel'
    ),

  /*
    | Hermes/Evri corporate tracking endpoints (same Basic Auth as label API).
    | Ask your Evri account manager for the correct tracking URL for your account.
    */
    'tracking_endpoints' => array_filter(array_map(
        'trim',
        explode(',', env('EVRI_TRACKING_ENDPOINTS', 'https://www.hermes-europe.co.uk/routing/service/rest/v4/routeDeliveryTrackParcel,https://www.hermes-europe.co.uk/routing/service/rest/v4/getParcelDeliveryStatus'))
    )),

    'public_tracking_url' => env('EVRI_PUBLIC_TRACKING_URL'),

    'verification_batch_limit' => (int) env('EVRI_VERIFICATION_BATCH_LIMIT', 100),

    'verification_recheck_hours' => (int) env('EVRI_VERIFICATION_RECHECK_HOURS', 6),
];
