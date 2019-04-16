<?php declare(strict_types=1);

function make_comparator(string $prefix): callable
{
    return function ($a, $b) use ($prefix) {
        if (strpos($a, $prefix) === 0 && strpos($b, $prefix) !== 0) {
            // $a starts with $prefix (and $b does not), sift up
            return -1;
        }

        if (strpos($a, $prefix) !== 0 && strpos($b, $prefix) === 0) {
            // $b starts with $prefix (and $a does not), sift down
            return 1;
        }

        // Otherwise, do a normal string comparison
        return strcmp($a, $b);
    };
}

// collect all dependencies
{
    $dependencies = [[]];
    $devDependencies = [[]];

    foreach (glob(__DIR__ . '/../src/*/composer.json') as $file) {
        $data = json_decode(file_get_contents($file), true);
        $dependencies[] = $data['require'] ?? [];
        $devDependencies[] = $data['require-dev'] ?? [];
    }

    $dependencies = array_merge(...$dependencies);
    $devDependencies = array_merge(...$devDependencies);
}

// remove many repositories
{
    unset(
        $dependencies['shopware/core'],
        $dependencies['shopware/storefront'],
        $dependencies['shopware/administration']
    );
}

// sort by key and prioritize every php extension
{
    uksort($dependencies, make_comparator('ext-'));
    uksort($devDependencies, make_comparator('ext-'));
}

// name php version as first dependency
{
    $phpVersion = $dependencies['php'];
    unset($dependencies['php']);
    $dependencies = array_merge(['php' => $phpVersion], $dependencies);
}

// write dependencies into root composer.json file
{
    $composerFile = __DIR__ . '/../composer.json';

    $platformComposer = json_decode(file_get_contents($composerFile), true);

    $hasChanges = false;
    if (array_diff_assoc($dependencies, $platformComposer['require']) || array_diff_assoc($devDependencies, $platformComposer['require-dev'])) {
        $hasChanges = true;
    }

    $platformComposer['require'] = $dependencies;
    $platformComposer['require-dev'] = $devDependencies;

    $jsonString = json_encode($platformComposer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    file_put_contents($composerFile, $jsonString);

    if ($hasChanges) {
        echo PHP_EOL;
        echo 'ERROR! The platform composer.json file has changed. Please review your commit and add the changes.' . PHP_EOL;
        echo PHP_EOL;
        exit(1);
    }
}
