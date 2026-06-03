<?php declare(strict_types=1);

$php = ['8.2'];
$db = ['mysql:8.0'];

$nightly = $_SERVER['argv'][1] ?? false;

if ($nightly) {
    $php = ['8.2', '8.5'];
    $db = ['mysql:8.0', 'mariadb:11', 'quay.io/mariadb-foundation/mariadb-devel:verylatest'];
}

// Reproducer branch: run only the flaky {Administration,Elasticsearch} path with a pinned random seed
// (see integration.yml) so the ElasticsearchProductTest term-search failures reproduce deterministically.
$matrix = [
    'fail-fast' => false,
    'matrix' => [
        'test' => [
            ['path' => '{Administration,Elasticsearch}'],
        ],
        'php' => $php,
        'db' => $db,
        'opensearch' => ['opensearchproject/opensearch:3'],
    ],
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
