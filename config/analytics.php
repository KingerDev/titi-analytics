<?php

return [
    'username'     => env('ANALYTICS_USERNAME', 'admin'),
    'password'     => env('ANALYTICS_PASSWORD', 'admin123'),
    'notify_email' => env('ANALYTICS_NOTIFY_EMAIL', null),

    // Interné/testovacie účty vylúčené z analytiky
    'excluded_customer_ids' => [2, 8169, 14595, 12453, 32, 12],
];
