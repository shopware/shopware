<?php declare(strict_types=1);

return [
    'root' => [
        'name' => 'swag/test-ships-vendor-directory',
        'pretty_version' => '1.0.0',
        'version' => '1.0.0.0',
        'reference' => null,
        'type' => 'shopware-platform-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => [],
        'dev' => true,
    ],
    'versions' => [
        'composer/composer' => [
            // Intentionally left empty, especially the version, to test the detection of the installed composer version
        ],
    ],
];
