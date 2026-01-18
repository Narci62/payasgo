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

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'fedapay' => [
        'public_key' => env('FEDAPAY_L_PUBLIC_KEY'),
        'secret_key' => env('FEDAPAY_L_PRIVATE_KEY'),
        'mode' => 'live', // 'sandbox' ou 'live'
        'webhook_signature_key' => env('FEDAPAY_WEBHOOK_SIGNATURE_KEY'),
    ],


    'fedapayT' => [
        'public_key' => env('FEDAPAY_T_PUBLIC_KEY'),
        'secret_key' => env('FEDAPAY_T_PRIVATE_KEY'),
        'mode' => env('FEDAPAY_MODE', 'sandbox'), // 'sandbox' ou 'live'
        'webhook_signature_key' => env('FEDAPAY_WEBHOOK_SIGNATURE_SANDBOX_KEY'),
    ],

    'amapi' => [
        'base_url' => env('AMAPI_BASE_URL', 'https://androidmanagement.googleapis.com/v1'),
        'project_id' => env('AMAPI_PROJECT_ID'),
        'enterprise_id' => env('AMAPI_ENTERPRISE_ID'),
        'service_account_key' => env('AMAPI_SERVICE_ACCOUNT_KEY'),
        //path to service account json in storage/app/public/
        'service_account_json' => env('AMAPI_SERVICE_ACCOUNT_JSON', 'storage/app/public/trueline-payguard-amapi-556ed97a2e37.json'), // Chemin vers le fichier JSON

        // Politiques prédéfinies
        'policies' => [
            'default' => env('AMAPI_POLICY_DEFAULT', 'default_policy'),
            'locked' => env('AMAPI_POLICY_LOCKED', 'locked_policy'),
        ],

        // Webhooks
        'webhook_url' => env('AMAPI_WEBHOOK_URL'),
        'webhook_secret' => env('AMAPI_WEBHOOK_SECRET'),
    ],


];
