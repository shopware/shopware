<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\LNumber\AddLiteralSeparatorToNumberRector;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Symfony\Rector\MethodCall\ContainerGetToConstructorInjectionRector;
use Rector\Symfony\Set\SymfonyLevelSetList;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;
use Shopware\Core\DevOps\StaticAnalyze\Rector\ClassPackageRector;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->symfonyContainerXml(__DIR__ . '/var/cache/phpstan_dev/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.xml');

    $rectorConfig->paths([
        __DIR__ . '/config',
        __DIR__ . '/custom',
        __DIR__ . '/public',
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);

    $rectorConfig->skip([
        __DIR__ . '/src/Core/Framework/Script/ServiceStubs.php',
        ReturnNeverTypeRector::class,
        AddLiteralSeparatorToNumberRector::class,
        ContainerGetToConstructorInjectionRector::class,
    ]);

    $rectorConfig->rule(ClassPackageRector::class);
    $rectorConfig->rule(AnnotationToAttributeRector::class);

    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_81,
        SymfonyLevelSetList::UP_TO_SYMFONY_62,
    ]);
};
