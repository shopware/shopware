<?php declare(strict_types=1);

$toolLoader = require __DIR__ . '/vendor/autoload.php';
$rootLoader = require dirname(__DIR__, 2) . '/vendor/autoload.php';

// Keep shared Twig/Symfony packages and the tool's collectors on the isolated installation.
$toolLoader->unregister();
$toolLoader->register(true);

if (!\defined('__PHPSTAN_RUNNING__')) {
    $phpstanLoader = require 'phar://' . __DIR__ . '/phpstan.phar/vendor/autoload.php';
    $phpstanLoader->unregister();
    $phpstanLoader->register(true);
}

if (extension_loaded('phpstan_turbo')) {
    throw new RuntimeException('The experimental context-preservation PHAR requires PHPStan Turbo to be disabled.');
}

return $rootLoader;
