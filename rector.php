<?php declare(strict_types=1);

use Shopware\Commercial\Test\Annotation\ActiveFeatureToggles;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Php80\ValueObject\AnnotationToAttribute;

return RectorConfig::configure()
    ->withSymfonyContainerXml(__DIR__ . '/var/cache/phpstan_dev/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.xml')
    ->withPaths([
        __DIR__ . '/config',
        __DIR__ . '/custom',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withFileExtensions(['php'])
    ->withImportNames()
    ->withSkip([
        __DIR__ . '/src/Core/Framework/Script/ServiceStubs.php',
        __DIR__ . '/src/Recovery',

        '**/vendor/*',
        '**/node_modules/*',
        '**/Resources/*',
    ])
    ->withConfiguredRule(AnnotationToAttributeRector::class, [
        new AnnotationToAttribute(ActiveFeatureToggles::class),
    ]);
