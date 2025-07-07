<?php

return [
    'levels' => [
        ['max_km' => 7, 'label' => 'Instant Delivery'],
        ['max_km' => 20, 'label' => 'Same Day Delivery'],
        ['max_km' => 50, 'label' => 'Next Day Delivery'],
        ['max_km' => 1000, 'label' => 'Estimated delivery time: 2 days'],
        ['max_km' => 2000, 'label' => 'Estimated delivery time: 3 days'],
        ['max_km' => PHP_INT_MAX, 'label' => 'Estimated delivery time: 5 days'],
    ],
];
