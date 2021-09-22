<?php declare(strict_types=1);

$parent = dirname(__DIR__, 1);
// root/platform/src/Recovery and root/vendor/shopware/recovery
$rootDir = dirname($parent, 2);
if (basename(dirname($rootDir)) === 'vendor') {
    // root/vendor/shopware/platform/src/Recovery
    $rootDir = dirname($rootDir, 2);
}
if (!is_dir($rootDir . '/vendor') && is_dir(dirname($parent) . '/vendor')) {
    // platform/src/Recovery -> platform only
    $rootDir = dirname($parent);
}

require $rootDir . '/vendor/autoload.php';

$fileSystem = new \Symfony\Component\Filesystem\Filesystem();
$publicAssetPath = $rootDir . '/public/recovery';

if (!$fileSystem->exists($publicAssetPath)) {
    $fileSystem->mkdir($publicAssetPath);
}

$fileSystem->mirror(__DIR__ . '/Resources/public', $publicAssetPath);
