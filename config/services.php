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
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    'vost_covid19_rest_api' => [
        'base_url' => 'https://covid19-api.vost.pt/Requests'
    ],
    'dssg_pt_covid19' => [
        'full_counties' => 'https://raw.githubusercontent.com/dssg-pt/covid19pt-data/master/data_concelhos_new.csv',
        'full_daily' => 'https://raw.githubusercontent.com/dssg-pt/covid19pt-data/master/data.csv'
    ],
    'ipma' => [
        'api_base_url' => env('IPMA_API_BASE_URL')
    ],
    'telegram-bot-api' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID')
    ],
    'telegram-bot-api-tests' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID_TESTS')
    ],
];
