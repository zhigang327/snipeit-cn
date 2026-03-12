<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
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

    'wechat' => [
        'enabled' => env('WECHAT_ENABLED', false),
        'webhook_url' => env('WECHAT_WEBHOOK_URL', ''),
        'notifications' => [
            'asset_expiring' => env('WECHAT_NOTIFY_ASSET_EXPIRING', true),
            'asset_changed' => env('WECHAT_NOTIFY_ASSET_CHANGED', true),
            'inventory_created' => env('WECHAT_NOTIFY_INVENTORY_CREATED', true),
            'inventory_completed' => env('WECHAT_NOTIFY_INVENTORY_COMPLETED', true),
            'maintenance' => env('WECHAT_NOTIFY_MAINTENANCE', true),
            'borrow' => env('WECHAT_NOTIFY_BORROW', true),
        ],
    ],

];
