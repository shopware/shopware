<?php declare(strict_types=1);

$php = ['8.2'];

$nightly = \strtolower($_SERVER['argv'][1] ?? '') === 'true';
$major = \strtolower($_SERVER['argv'][2] ?? '') === 'true';

if ($nightly) {
    // We add 8.4 separate because of currents
    $php = ['8.2', '8.5'];
}

$matrix = [
    'fail-fast' => false,
    'matrix' => [
        'name' => ['Platform'],
        'major' => ($major || $nightly) ? ['', 'major'] : [''],
        'php-version' => $php,
        'shard' => ['1', '2', '3'],
        'shard-count' => [3],
        'no-currents' => [true],
        'include' => [
            [
                'name' => 'Install',
                'php-version' => $nightly ? '8.4' : '8.2',
                'shard' => 1,
                'shard-count' => 1,
                'no-currents' => !$nightly,
            ],
        ],
    ],
];

if ($nightly) {
    for ($i = 0; $i < 3; ++$i) {
        $matrix['matrix']['include'][] = [
            'name' => 'Platform',
            'php-version' => '8.4',
            'shard' => $i + 1,
            'shard-count' => 3,
            'no-currents' => false,
        ];
        $matrix['matrix']['include'][] = [
            'name' => 'Platform',
            'major' => 'major',
            'php-version' => '8.4',
            'shard' => $i + 1,
            'shard-count' => 3,
            'no-currents' => true,
        ];
    }
}

echo \json_encode($matrix, \JSON_THROW_ON_ERROR);
