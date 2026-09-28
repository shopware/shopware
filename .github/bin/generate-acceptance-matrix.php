<?php declare(strict_types=1);

require_once __DIR__ . '/lib/feature-flags.php';

$php = ['8.2'];

// Emits the `strategy.matrix` object only — `fail-fast` is set statically by the calling
// workflow, because zizmor cannot audit a file whose whole `strategy:` is an expression.
//
// argv[1] is the run profile: '' (PR), 'nightly' or 'release' (patch release gate).
$mode = \strtolower($_SERVER['argv'][1] ?? '');
$nightly = $mode === 'nightly';
$release = $mode === 'release';
$major = \strtolower($_SERVER['argv'][2] ?? '') === 'true';

// Controls which "major" variants end up in the matrix:
//   ''        -> default behaviour (both non-major and major when applicable)
//   'exclude' -> only non-major variants (used by the regular nightly)
//   'only'    -> only major variants (used by the dedicated major nightly)
$majorFilter = \strtolower($_SERVER['argv'][3] ?? '');

// One major variant per in-flight major (`FEATURE_ALL=v6.8.0.0`), so a major's release state is
// covered without the next major's changes active. See lib/feature-flags.php.
$majorLanes = shopware_major_lanes();

if ($nightly) {
    // We add 8.4 separate because of currents
    $php = ['8.2', '8.5'];
}

if ($release) {
    // Patch release gate: cover all supported PHP versions, but without Currents or major simulation.
    $php = ['8.2', '8.4', '8.5'];
}

$majorVariants = ($major || $nightly) ? ['', ...$majorLanes] : [''];
if ($majorFilter === 'exclude') {
    $majorVariants = [''];
} elseif ($majorFilter === 'only') {
    $majorVariants = $majorLanes;
}

$matrix = [
    'name' => ['Platform'],
    'major' => $majorVariants,
    'php-version' => $php,
    'shard' => ['1', '2', '3'],
    'shard-count' => [3],
    'no-currents' => [true],
    'include' => [],
];

// The install test is not a major test, so it only belongs to the non-major run.
if ($majorFilter !== 'only') {
    $matrix['include'][] = [
        'name' => 'Install',
        'php-version' => ($nightly || $release) ? '8.4' : '8.2',
        'shard' => 1,
        'shard-count' => 1,
        'no-currents' => !$nightly,
    ];
}

if ($nightly) {
    for ($i = 0; $i < 3; ++$i) {
        if ($majorFilter !== 'only') {
            $matrix['include'][] = [
                'name' => 'Platform',
                'php-version' => '8.4',
                'shard' => $i + 1,
                'shard-count' => 3,
                'no-currents' => false,
            ];
        }
        if ($majorFilter !== 'exclude') {
            foreach ($majorLanes as $majorLane) {
                $matrix['include'][] = [
                    'name' => 'Platform',
                    'major' => $majorLane,
                    'php-version' => '8.4',
                    'shard' => $i + 1,
                    'shard-count' => 3,
                    'no-currents' => true,
                ];
            }
        }
    }
}

echo \json_encode($matrix, \JSON_THROW_ON_ERROR);
