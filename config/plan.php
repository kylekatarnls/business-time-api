<?php

declare(strict_types=1);

use function App\Util\integerOrInfinity;

return [
    'guest' => [
        'title' => env('GUEST_PLAN_TITLE', 'Guest'),
        'name' => env('GUEST_PLAN_NAME', 'Vicopo Guest'),
        'id' => env('GUEST_PLAN_PRODUCT', 'guest'),
        'limit' => integerOrInfinity(env('GUEST_PLAN_LIMIT', 1000)),
        'price' => [
            'currency' => env('GUEST_PLAN_CURRENCY', 'eur'),
            'amount' => (float) env('GUEST_PLAN_PRICE', 0),
        ],
    ],
    'free' => [
        'title' => env('FREE_PLAN_TITLE', 'Free'),
        'name' => env('FREE_PLAN_NAME', 'Vicopo Free'),
        'id' => env('FREE_PLAN_PRODUCT', 'free'),
        'limit' => integerOrInfinity(env('FREE_PLAN_LIMIT', 5000)),
        'price' => [
            'currency' => env('FREE_PLAN_CURRENCY', 'eur'),
            'amount' => (float) env('FREE_PLAN_PRICE', 0),
        ],
    ],
    'start' => [
        'title' => env('START_PLAN_TITLE', 'Start'),
        'name' => env('START_PLAN_NAME', 'Vicopo Start'),
        'id' => env('START_PLAN_PRODUCT'),
        'limit' => integerOrInfinity(env('START_PLAN_LIMIT', 20000)),
        'price' => [
            'currency' => env('START_PLAN_CURRENCY', 'eur'),
            'amount' => (float) env('START_PLAN_PRICE', 9.9),
            'monthly' => env('START_PLAN_MONTHLY_PRICE_ID'),
            'yearly' => env('START_PLAN_YEARLY_PRICE_ID'),
        ],
    ],
    'pro' => [
        'title' => env('PRO_PLAN_TITLE', 'Pro'),
        'name' => env('PRO_PLAN_NAME', 'Vicopo Pro'),
        'id' => env('PRO_PLAN_PRODUCT'),
        'limit' => integerOrInfinity(env('PRO_PLAN_LIMIT', 200000)),
        'price' => [
            'currency' => env('PRO_PLAN_CURRENCY', 'eur'),
            'amount' => (float) env('PRO_PLAN_PRICE', 19.9),
            'monthly' => env('PRO_PLAN_MONTHLY_PRICE_ID'),
            'yearly' => env('PRO_PLAN_YEARLY_PRICE_ID'),
        ],
    ],
    'premium' => [
        'title' => env('PREMIUM_PLAN_TITLE', 'Premium'),
        'name' => env('PREMIUM_PLAN_NAME', 'Vicopo Premium'),
        'id' => env('PREMIUM_PLAN_PRODUCT'),
        'limit' => integerOrInfinity(env('PRO_PLAN_LIMIT', INF)),
        'price' => [
            'currency' => env('PREMIUM_PLAN_CURRENCY', 'eur'),
            'amount' => (float) env('PREMIUM_PLAN_PRICE', 34.9),
            'monthly' => env('PREMIUM_PLAN_MONTHLY_PRICE_ID'),
            'yearly' => env('PREMIUM_PLAN_YEARLY_PRICE_ID'),
        ],
    ],
];
