<?php declare(strict_types=1);

return [
    'slim' => [
        'settings' => [
            'httpVersion' => '1.1',
            'responseChunkSize' => 4096,
            'outputBuffering' => 'append',
            'determineRouteBeforeAppMiddleware' => true,
            'displayErrorDetails' => true,
            'addContentLengthHeader' => true,
            'routerCacheFile' => false,
        ],
        'debug' => true, // set debug to false so custom error handler is used
        'templates.path' => __DIR__ . '/../templates',
    ],
    'storeapi.endpoint' => 'http://store.shopware.de/storeApi',
];
