<?php

$php = ['8.2'];
$db = ['mysql:8.0'];

$nightly = $_SERVER['argv'][1] ?? false;

if ($nightly || true) {
    $php = ['8.4'];
    $db = ['mariadb:11.7.2', 'mariadb:11.6.2', 'mariadb:11.4', 'mariadb:11.8-rc'];
}

echo \json_encode([
    'fail-fast' => false,
    'matrix' => [
        'test' => [
            // ['path' => 'Core/Checkout'],
            ['path' => 'Core/Content'],
            // ['testsuite' => 'core-framework-batch1'],
            ['testsuite' => 'core-framework-batch2'],
            // ['testsuite' => 'core-framework-batch3'],
            // ['path' => 'Storefront'],
            // ['path' => '{Administration,Elasticsearch}'],
            // ['path' => '{Core/Installer,Core/Maintenance,Core/System}'],
            // ['testsuite' => 'migration'],
            // ['testsuite' => 'devops']
        ],
        'php' => $php,
        'db' => $db,
        'include' => [
            [
                'test' => ['testsuite' => 'migration'],
                'php' => '8.2',
                'db' => 'mariadb:11'
            ],
        ]
    ]
], \JSON_THROW_ON_ERROR);
