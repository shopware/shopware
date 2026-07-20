<?php declare(strict_types=1);

/**
 * Replaces `paths: [src, tests]`: PHPStan walks all paths before applying excludePaths
 * (https://github.com/phpstan/phpstan/issues/1978), so `src` would drag in the ~190k files
 * under the administration/storefront frontend roots (src/<Bundle>/Resources/app — Vue/JS
 * sources and node_modules, no PHP). This lists src and tests with those roots left out.
 */

// a directory is analysed whole; a loose file only if it is PHP
$analysable = static fn (string $path): bool => is_dir($path) || str_ends_with($path, '.php');

$paths = [__DIR__ . '/tests'];

foreach (glob(__DIR__ . '/src/*') as $bundle) {
    $frontendRoot = $bundle . '/Resources/app';

    // bundles without a frontend root (src/Core, src/Elasticsearch) are analysed whole
    if (!is_dir($frontendRoot)) {
        $paths[] = $bundle;

        continue;
    }

    // the bundle's own entries, minus `Resources/`
    foreach (glob($bundle . '/*') as $path) {
        if ($path !== $bundle . '/Resources' && $analysable($path)) {
            $paths[] = $path;
        }
    }

    // `Resources/` entries, minus the app/ frontend root
    foreach (glob($bundle . '/Resources/*') as $path) {
        if ($path !== $frontendRoot && $analysable($path)) {
            $paths[] = $path;
        }
    }
}

sort($paths);

$parameters = [
    'paths' => $paths,
];

// The Danger rules and their tests type against the danger-php package from vendor-bin
// (registered in src/Core/DevOps/StaticAnalyze/phpstan-bootstrap.php). Without that install
// (integration jobs strip vendor-bin, plugin pipelines never have it) the symbols are unknown,
// so those files are skipped instead of failing the whole run. The explicit analyseAndScan
// structure is required: the shorthand list form is only normalized by the neon loader and
// gets dropped when returned from a PHP config file. In this form the entries append-merge
// with the excludePaths in phpstan.neon.dist.
if (!is_dir(__DIR__ . '/vendor-bin/danger-php/vendor')) {
    $parameters['excludePaths'] = [
        'analyseAndScan' => [
            __DIR__ . '/src/Core/DevOps/StaticAnalyze/Danger',
            __DIR__ . '/tests/unit/Core/DevOps/StaticAnalyze/Danger',
        ],
    ];
}

return [
    'parameters' => $parameters,
];
