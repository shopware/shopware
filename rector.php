<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Rector\Config\RectorConfig;
use Rector\Core\ValueObject\Visibility;
use Rector\Visibility\Rector\ClassMethod\ChangeMethodVisibilityRector;
use Rector\Visibility\ValueObject\ChangeMethodVisibility;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Php80\ValueObject\AnnotationToAttribute;
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
    $rectorConfig->fileExtensions(['php']);

    $rectorConfig->importNames();
    $rectorConfig->importShortClasses(false);

    $rectorConfig->skip([
        __DIR__ . '/src/Core/Framework/Script/ServiceStubs.php',
        __DIR__ . '/src/Recovery',

        '**/vendor/*',
        '**/node_modules/*',
        '**/Resources/*',
    ]);

    $rectorConfig->ruleWithConfiguration(AnnotationToAttributeRector::class, [
            new AnnotationToAttribute('Shopware\Commercial\Test\Annotation\ActiveFeatureToggles'),
        ]
    );

    // PHPUnit v10 update
    // $rectorConfig->rule(\Rector\PHPUnit\AnnotationsToAttributes\Rector\Class_\CoversAnnotationWithValueToAttributeRector::class);
    // $rectorConfig->rule(\Rector\PHPUnit\AnnotationsToAttributes\Rector\ClassMethod\DataProviderAnnotationToAttributeRector::class);
    // $rectorConfig->rule(\Rector\PHPUnit\AnnotationsToAttributes\Rector\ClassMethod\DependsAnnotationWithValueToAttributeRector::class);
    // $rectorConfig->rule(\Rector\PHPUnit\AnnotationsToAttributes\Rector\Class_\AnnotationWithValueToAttributeRector::class);

//    $rectorConfig->rule(ClassPackageRector::class);
//    $rectorConfig->ruleWithConfiguration(
//        ChangeMethodVisibilityRector::class,
//        [
//            new ChangeMethodVisibility(TestCase::class, 'setUp', Visibility::PROTECTED),
//            new ChangeMethodVisibility(TestCase::class, 'tearDown', Visibility::PROTECTED),
//        ]
//    );
};
