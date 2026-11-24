<?php declare(strict_types=1);

use TwigStan\Config\TwigStanConfig;

$root = dirname(__DIR__, 5);

return TwigStanConfig::configure($root)
    ->phpstanBinPath($root . '/vendor-bin/twigstan/bin/phpstan')
    ->phpstanConfigurationFile(__DIR__ . '/phpstan.neon')
    ->twigEnvironmentLoader(__DIR__ . '/loader.php')
    ->twigPaths(__DIR__)
    ->phpPaths(__DIR__ . '/Controller.php')
    ->tempDirectory($root . '/var/cache/twigstan-template-deprecation-test')
    ->create();
