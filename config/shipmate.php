<?php

return [

    'api_key'  => env('SHIPMATE_API_KEY'),
    'token'    => env('SHIPMATE_TOKEN'),
    'base_url' => env('SHIPMATE_BASE_URL', 'https://api.shipmate.co.uk/v1.2'),

    'delivery_service_key' => env('SHIPMATE_DELIVERY_SERVICE_KEY'),

    // Parcelforce parcel: 80 × 20 × 20 cm, 20 kg (weight in grams)
    'default_weight_grams' => 20000,
    'default_length_cm'    => 80,
    'default_width_cm'     => 20,
    'default_depth_cm'     => 20,

    // Declared value in GBP (Parcelforce requires 0.01–9999999.99)
    'default_parcel_value' => 10.00,

    'max_label_print_count' => 5,

    // Parcelforce Shipper.Reference1 (shipment_reference) max length
    'max_shipment_reference_length' => 30,

    // Customs declaration (required by some delivery services)
    'customs_reason_for_export'   => env('SHIPMATE_CUSTOMS_REASON', 'SALE'),
    'customs_incoterms'           => env('SHIPMATE_CUSTOMS_INCOTERMS', 'DAP'),
    'customs_short_description'   => env('SHIPMATE_CUSTOMS_SHORT_DESC', 'Car Mats'),
    'customs_full_description'    => env('SHIPMATE_CUSTOMS_FULL_DESC', 'Automotive Car Mats'),
    'customs_country_of_origin'   => env('SHIPMATE_CUSTOMS_COUNTRY_OF_ORIGIN', 'GB'),
    'customs_harmonised_code'     => env('SHIPMATE_CUSTOMS_HARMONISED_CODE', '87089900'),

];
