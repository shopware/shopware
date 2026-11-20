<?php declare(strict_types=1);

$toolLoader = require __DIR__ . '/vendor/autoload.php';
$rootLoader = require dirname(__DIR__, 2) . '/vendor/autoload.php';

// Keep shared Twig/Symfony packages and the tool's collectors on the isolated installation.
$toolLoader->unregister();
$toolLoader->register(true);

return $rootLoader;
