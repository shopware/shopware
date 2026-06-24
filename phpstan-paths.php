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

return [
    'parameters' => [
        'paths' => $paths,
    ],
];
