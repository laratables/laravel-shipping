<?php

return [
    'enabled' => env('SHIPPING_ENABLED', true),
    'disabled_fallback' => [
        'mode'             => env('SHIPPING_DISABLED_MODE', 'free'),
        'flat_rate_amount' => env('SHIPPING_FLAT_RATE_AMOUNT', 5.99),
        'message'          => env('SHIPPING_DISABLED_MESSAGE', 'Shipping is currently unavailable. Please contact us for a quote.'),
    ],
    'base_fee'                 => env('SHIPPING_BASE_FEE', 2.50),
    'multi_product_surcharge'  => env('SHIPPING_MULTI_PRODUCT_SURCHARGE', 1.50),
    'heavy_item_threshold_kg'  => env('SHIPPING_HEAVY_ITEM_THRESHOLD_KG', 10.0),
    'heavy_item_surcharge'     => env('SHIPPING_HEAVY_ITEM_SURCHARGE', 3.00),
    'max_weight_kg'            => env('SHIPPING_MAX_WEIGHT_KG', 100.0),
    'free_enabled'             => env('SHIPPING_FREE_ENABLED', true),
    'free_threshold'           => env('SHIPPING_FREE_THRESHOLD', 75.00),
    'free_weight_limit_kg'     => env('SHIPPING_FREE_WEIGHT_LIMIT_KG', null),
];
