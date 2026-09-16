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

    'verimor_sms' => [
        'username' => env('VERIMOR_SMS_USERNAME'),
        'password' => env('VERIMOR_SMS_PASSWORD'),
        'source_address' => env('VERIMOR_SMS_SOURCE_ADDRESS'),
        'endpoint' => env('VERIMOR_SMS_ENDPOINT', 'https://sms.verimor.com.tr/v2/send.json'),
    ],

    'cari_plus' => [
        'base_url' => env('CARI_PLUS_BASE_URL', 'https://api.cariplus.com.tr'),
        'client_id' => env('CARI_PLUS_CLIENT_ID'),
        'client_secret' => env('CARI_PLUS_CLIENT_SECRET'),
        'connect_timeout' => (int) env('CARI_PLUS_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('CARI_PLUS_TIMEOUT', 10),
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

];
