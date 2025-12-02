<?php

$php = ['8.2'];
$db = ['mysql:8.0'];

$nightly = $_SERVER['argv'][1] ?? false;

if ($nightly) {
    $php = ['8.2', '8.4'];
    $db = ['mysql:8.0', 'mariadb:11'];
}

$matrix = [
    'fail-fast' => false,
    'matrix' => [
        'test' => [
            ['path' => 'Core/Checkout'],
            ['path' => 'Core/Content'],
            ['testsuite' => 'core-framework-batch1'],
            ['testsuite' => 'core-framework-batch2'],
            ['testsuite' => 'core-framework-batch3'],
            ['path' => 'Storefront'],
            ['path' => '{Administration,Elasticsearch}'],
            ['path' => '{Core/Installer,Core/Maintenance,Core/Service,Core/System}'],
            ['testsuite' => 'migration'],
            ['testsuite' => 'devops']
        ],
        'php' => $php,
        'db' => $db,
        'opensearch' => ['opensearchproject/opensearch:3'],
        'include' => [
            [
                'test' => ['testsuite' => 'migration'],
                'php' => '8.2',
                'db' => 'mariadb:11'
            ],
        ]
    ]
];

if ($nightly) {
    $matrix['matrix']['include'][] = [
        'test' => ['path' => '{Administration,Elasticsearch}'],
        'php' => '8.4',
        'db' => 'mysql:8.0',
        'opensearch' => 'opensearchproject/opensearch:2',
    ];
    /** @deprecated tag:v6.8.0 - Support for OpenSearch 1 will be removed in v6.8.0 (update the docs as well!) */
    $matrix['matrix']['include'][] = [
        'test' => ['path' => '{Administration,Elasticsearch}'],
        'php' => '8.4',
        'db' => 'mysql:8.0',
        'opensearch' => 'opensearchproject/opensearch:1',
    ];
}

echo \json_encode($matrix, \JSON_THROW_ON_ERROR);
