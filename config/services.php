<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'bybit' => [
        'base_url' => env('BYBIT_BASE_URL', 'https://api.bybit.com'),
        'connect_timeout' => (int) env('BYBIT_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('BYBIT_TIMEOUT', 10),
    ],

    'gdelt' => [
        'base_url' => env('GDELT_BASE_URL', 'https://api.gdeltproject.org'),
        'connect_timeout' => (int) env('GDELT_CONNECT_TIMEOUT', 15),
        'timeout' => (int) env('GDELT_TIMEOUT', 60),
        'request_interval_seconds' => (int) env('GDELT_REQUEST_INTERVAL_SECONDS', 12),
    ],

    'alpha_vantage' => [
        'base_url' => env('ALPHA_VANTAGE_BASE_URL', 'https://www.alphavantage.co'),
        'api_key' => env('ALPHA_VANTAGE_API_KEY'),
        'connect_timeout' => (int) env('ALPHA_VANTAGE_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('ALPHA_VANTAGE_TIMEOUT', 300),
        'news_limit' => (int) env('ALPHA_VANTAGE_NEWS_LIMIT', 200),
    ],

];
