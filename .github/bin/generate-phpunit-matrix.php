<?php

$nightly = $_SERVER['argv'][1] ?? false;
$major = filter_var($_SERVER['argv'][2] ?? false, \FILTER_VALIDATE_BOOLEAN);

// Integration shards: the paths + framework batches together cover the whole tests/integration tree.
$integrationTests = [
    ['path' => 'Core/Checkout'],
    ['path' => 'Core/Content'],
    ['testsuite' => 'core-framework-batch1'],
    ['testsuite' => 'core-framework-batch2'],
    ['testsuite' => 'core-framework-batch3'],
    ['path' => 'Storefront'],
    ['path' => '{Administration,Elasticsearch}'],
    ['path' => '{Core/Installer,Core/Maintenance,Core/Service,Core/System}'],
];

if ($major) {
    // Nightly major-flag run: each integration shard once on a single PHP/DB (migration excluded — php.yml already runs it major).
    echo \json_encode([
        'fail-fast' => false,
        'matrix' => [
            'test' => $integrationTests,
            'php' => ['8.2'],
            'db' => ['mysql:8.0'],
            'opensearch' => ['opensearchproject/opensearch:3'],
        ],
    ], \JSON_THROW_ON_ERROR);

    return;
}

$php = ['8.2'];
$db = ['mysql:8.0'];

if ($nightly) {
    $php = ['8.2', '8.5'];
    $db = ['mysql:8.0', 'mariadb:11', 'quay.io/mariadb-foundation/mariadb-devel:verylatest'];
}

$matrix = [
    'fail-fast' => false,
    'matrix' => [
        'test' => array_merge($integrationTests, [
            ['testsuite' => 'migration'],
        ]),
        'php' => $php,
        'db' => $db,
        'opensearch' => ['opensearchproject/opensearch:3'],
        'include' => [
            [
                'test' => ['testsuite' => 'migration'],
                'php' => '8.2',
                'db' => 'mariadb:11'
            ],
            [
                'test' => ['testsuite' => 'devops'],
                'php' => '8.5',
                'db' => 'mariadb:11'
            ]
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
