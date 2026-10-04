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

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'pdf_model' => env('OPENAI_PDF_MODEL', env('OPENAI_MODEL', 'gpt-4o')),
        'chat_model' => env('OPENAI_CHAT_MODEL', env('OPENAI_MODEL', 'gpt-4o')),
        'issuer_domains' => ['sbi' => ['sbicard.com'], 'icici' => ['icicibank.com'], 'hdfc' => ['hdfcbank.com', 'hdfc.bank'], 'axis' => ['axisbank.com'], 'yes' => ['yesbank.in'], 'kotak' => ['kotak.com'], 'idfc' => ['idfcfirstbank.com'], 'american express' => ['americanexpress.com']],
    ],

];
