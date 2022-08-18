<?php declare(strict_types=1);

return [
    'shopware.root_dir' => realpath(__DIR__ . '/../../../'),
    'check.ping_url' => 'recovery/install/ping.php',
    'check.check_url' => 'recovery/install/check.php',
    'check.token.path' => __DIR__ . '/../tmp/token',

    'api.endpoint' => 'https://api.shopware.com',

    'tos.urls' => [
        'de' => 'https://api.shopware.com/gtc/de_DE.html',
        'en' => 'https://api.shopware.com/gtc/en_GB.html',
    ],

    'languages' => ['de', 'en', 'cs', 'es', 'fr', 'it', 'nl', 'pl', 'pt', 'sv', 'da'],

    'slim' => [
        'settings' => [
            'httpVersion' => '1.1',
            'responseChunkSize' => 4096,
            'outputBuffering' => 'append',
            'determineRouteBeforeAppMiddleware' => false,
            'displayErrorDetails' => true,
            'addContentLengthHeader' => true,
            'routerCacheFile' => false,
        ],
        'debug' => true, // set debug to false so custom error handler is used
        'templates.path' => __DIR__ . '/../templates',
    ],

    'menu.helper' => [
        'routes' => [
            'language-selection',
            'requirements',
            'license',
            'database-configuration',
            'database-import',
            'configuration',
            'finish',
        ],
    ],
];
