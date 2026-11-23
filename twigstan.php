<?php declare(strict_types=1);

use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan\StorefrontRenderContextCollector;
use TwigStan\Config\TwigStanConfig;

return TwigStanConfig::configure(__DIR__)
    ->phpstanBinPath(__DIR__ . '/vendor-bin/twigstan/bin/phpstan')
    ->phpstanConfigurationFile(__DIR__ . '/twigstan-phpstan.neon')
    ->phpstanMemoryLimit('2G')
    ->twigEnvironmentLoader(__DIR__ . '/twigstan-loader.php')
    ->twigPaths(__DIR__ . '/src/Storefront/Resources/views')
    ->phpPaths(
        __DIR__ . '/src',
        __DIR__ . '/vendor-bin/twigstan/src/StorefrontTemplateContracts.php',
    )
    ->twigContextCollector(StorefrontRenderContextCollector::class)
    ->tempDirectory(__DIR__ . '/var/cache/twigstan')
    ->create();
