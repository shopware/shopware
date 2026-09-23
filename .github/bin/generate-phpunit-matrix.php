<?php

require_once __DIR__ . '/lib/feature-flags.php';

// Emits the `strategy.matrix` object only — `fail-fast` is set statically by the calling
// workflow, because zizmor cannot audit a file whose whole `strategy:` is an expression.
//
// argv[1] is the run profile: '' (PR), 'nightly' or 'release'. Only nightly widens the matrix.
$nightly = ($_SERVER['argv'][1] ?? '') === 'nightly';
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
    // Nightly major-flag run: each integration shard once on a single PHP/DB (migration excluded — php.yml already runs it major),
    // and once per in-flight major, so a major's release state is validated without the next major's changes active.
    echo \json_encode([
        'test' => $integrationTests,
        'major' => shopware_major_lanes(),
        'php' => ['8.2'],
        'db' => ['mysql:8.0'],
        'opensearch' => ['opensearchproject/opensearch:3'],
    ], \JSON_THROW_ON_ERROR);

    return;
}

$php = ['8.2'];
$db = ['mysql:8.0'];

if ($nightly) {
    $php = ['8.2', '8.5'];
}

$includes = [
    [
        'test' => ['testsuite' => 'devops'],
        'php' => '8.5',
        'db' => 'mariadb:11'
    ],
    // MySQL 8.4 defaults restrict_fk_on_non_standard_key to ON; NonStandardFkGuardTest
    // skips without it.
    [
        'test' => ['testsuite' => 'devops'],
        'php' => '8.2',
        'db' => 'mysql:8.4'
    ]
];

if ($nightly) {
    // The DB spread runs on PHP 8.2 only and the PHP spread (8.5) on mysql:8.0 only:
    // DB behaviour does not depend on the PHP version, so the full cross product
    // adds jobs but no signal.
    $nightlyDbs = ['mysql:9.7', 'mariadb:11', 'mariadb:12.3', 'quay.io/mariadb-foundation/mariadb-devel:verylatest'];
    foreach ($nightlyDbs as $nightlyDb) {
        foreach (array_merge($integrationTests, [['testsuite' => 'migration']]) as $test) {
            $includes[] = [
                'test' => $test,
                'php' => '8.2',
                'db' => $nightlyDb,
                'opensearch' => 'opensearchproject/opensearch:3',
            ];
        }
    }
} else {
    // Covered by the nightly DB spread above; PR/release runs need the explicit lane.
    $includes[] = [
        'test' => ['testsuite' => 'migration'],
        'php' => '8.2',
        'db' => 'mariadb:11'
    ];
}

$matrix = [
    'test' => array_merge($integrationTests, [
        ['testsuite' => 'migration'],
    ]),
    'php' => $php,
    'db' => $db,
    'opensearch' => ['opensearchproject/opensearch:3'],
    'include' => $includes
];

if ($nightly) {
    $matrix['include'][] = [
        'test' => ['path' => '{Administration,Elasticsearch}'],
        'php' => '8.4',
        'db' => 'mysql:8.0',
        'opensearch' => 'opensearchproject/opensearch:2',
    ];
    /** @deprecated tag:v6.8.0 - Support for OpenSearch 1 will be removed in v6.8.0 (update the docs as well!) */
    $matrix['include'][] = [
        'test' => ['path' => '{Administration,Elasticsearch}'],
        'php' => '8.4',
        'db' => 'mysql:8.0',
        'opensearch' => 'opensearchproject/opensearch:1',
    ];
}

echo \json_encode($matrix, \JSON_THROW_ON_ERROR);
