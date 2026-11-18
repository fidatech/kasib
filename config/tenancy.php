<?php

/*
| One database per business.
|
| central_domains  hosts that open the system administrator panel (Fida Technologies)
| base_domain      a new business gets <slug>.<base_domain> unless a domain of its own is written
| database_prefix  a business's database is <prefix><slug>
| sqlite_path      where the database files are kept when the system runs on SQLite (tests)
| cpanel           on cPanel hosting: the account and its API token, so databases and subdomains are made through the panel
*/

return [
    'central_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env('CENTRAL_DOMAINS', 'localhost,127.0.0.1,admin.kasib.test'))))),
    'base_domain' => env('TENANT_BASE_DOMAIN', 'kasib.test'),
    'database_prefix' => env('TENANT_DATABASE_PREFIX', 'hb_'),
    'sqlite_path' => storage_path('tenants'),
    'cpanel' => [
        'user' => env('CPANEL_USER'),
        'token' => env('CPANEL_TOKEN'),
        'url' => env('CPANEL_URL', 'https://127.0.0.1:2083'),
        'docroot' => env('CPANEL_DOCROOT', 'public_html'),
        // every name under the base domain already reaches the system with a valid certificate
        // (a "*" subdomain with a wildcard certificate): no subdomain is made for a new business
        'wildcard' => (bool) env('CPANEL_WILDCARD', false),
    ],
    // a copy of every nightly backup is sent here by FTP, so the backups are not only on this server
    'backup_ftp' => [
        'host' => env('BACKUP_FTP_HOST'),
        'port' => env('BACKUP_FTP_PORT', 21),
        'user' => env('BACKUP_FTP_USER'),
        'password' => env('BACKUP_FTP_PASSWORD'),
        'path' => env('BACKUP_FTP_PATH', 'kasib-backups'),
        'ssl' => (bool) env('BACKUP_FTP_SSL', true),
    ],
    // the business anybody may open from the website; an empty DEMO_SLUG means no demo
    'demo' => ['slug' => env('DEMO_SLUG', 'demo'), 'email' => env('DEMO_EMAIL', 'demo@kasib.af'), 'password' => env('DEMO_PASSWORD', 'demo1234'),
        // how many papers of earlier years the demo holds beside the two months of its story
        'transactions' => (int) env('DEMO_TRANSACTIONS', 100000)],

    'reserved_slugs' => ['admin', 'www', 'api', 'app', 'mail', 'dab', 'central', 'demo'],
];
