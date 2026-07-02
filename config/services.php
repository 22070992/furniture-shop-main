<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT'),
    ],
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT'),
    ],

    'vnpay' => [
        'env' => env('VNPAY_ENV', 'sandbox'),
        'tmn_code' => env('VNPAY_TMN_CODE', env('VNP_TMN_CODE')),
        'hash_secret' => env('VNPAY_HASH_SECRET', env('VNP_HASH_SECRET')),
        'payment_url' => env('VNPAY_PAYMENT_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url' => env('VNPAY_RETURN_URL'),
        'ipn_url' => env('VNPAY_IPN_URL'),
        'version' => env('VNPAY_VERSION', '2.1.0'),
        'locale' => env('VNPAY_LOCALE', 'vn'),
        'currency' => env('VNPAY_CURRENCY', 'VND'),
        'order_type' => env('VNPAY_ORDER_TYPE', 'other'),
        'payment_timeout' => (int) env('VNPAY_PAYMENT_TIMEOUT', 15),
        'timezone' => env('VNPAY_TIMEZONE', 'Asia/Ho_Chi_Minh'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 20),
        'temperature' => (float) env('OPENAI_TEMPERATURE', 0.75),
        'max_tokens' => (int) env('OPENAI_MAX_TOKENS', 500),
    ],

];
