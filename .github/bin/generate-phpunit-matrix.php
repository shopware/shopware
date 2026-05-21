<?php

$php = ['8.2'];
$db = ['mysql:8.0'];

$nightly = $_SERVER['argv'][1] ?? false;

if ($nightly) {
    $php = ['8.2', '8.5'];
    $db = ['mysql:8.0', 'mariadb:11', 'quay.io/mariadb-foundation/mariadb-devel:verylatest'];
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

    // PHPUnit 12 preview — non-blocking (handled by continue-on-error in the workflow).
    // Pinned to PHP 8.3 (well-supported by both PHPUnit 11 and 12) so failures are
    // attributable to PHPUnit 12 rather than PHP version drift.
    $phpunit12Tests = $matrix['matrix']['test'];
    // The unit suite is not part of the integration matrix by default — add it here
    // so the PHPUnit 12 preview covers it (most PHPUnit-12-specific deprecations land in unit tests).
    $phpunit12Tests[] = ['testsuite' => 'unit'];
    foreach ($phpunit12Tests as $test) {
        $matrix['matrix']['include'][] = [
            'test' => $test,
            'php' => '8.3',
            'db' => 'mysql:8.0',
            'opensearch' => 'opensearchproject/opensearch:3',
            'phpunit' => '12',
        ];
    }
}

echo \json_encode($matrix, \JSON_THROW_ON_ERROR);
