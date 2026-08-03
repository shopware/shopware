<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

require __DIR__ . '/vendor/symfony/dependency-injection/Loader/Configurator/ContainerConfigurator.php'; // function declarations inside

$config = new Configuration();

configureImagickSupport($config);

return $config
    /** Scanned as prod, which might cause some false positives */
    ->addPathToScan('config/bundles.php', isDev: false)

    /** Only used if `class_exists` is successful in @see \Shopware\Core\Profiling\Integration\Datadog */
    ->ignoreUnknownClassesRegex('~^DDTrace~')
    /** Danger rules (src/Core/DevOps/StaticAnalyze/Danger) type against the danger-php package from vendor-bin, outside the root autoloader */
    ->ignoreUnknownClassesRegex('~^Danger\\\\~')
    /** Test plugins used in @see \Shopware\Core\Framework\Test\Plugin\PluginIntegrationTestBehaviour */
    ->ignoreUnknownClassesRegex('~^Swag~')
    /** Only used if `class_exists` is successful in @see \Shopware\Core\Profiling\Integration\Tideways */
    ->ignoreUnknownClasses(['Tideways\Profiler'])
    /** Only used if compression method is `zstd` @see \Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor */
    ->ignoreUnknownFunctions(['zstd_compress', 'zstd_uncompress'])

    /** Classes in src directory, that are only used for development */
    ->ignoreErrorsOnPaths([
        __DIR__ . '/src/Core/DevOps/',
        __DIR__ . '/src/Core/Test/',
        __DIR__ . '/src/Elasticsearch/Test',
        __DIR__ . '/src/Storefront/Test',
        __DIR__ . '/src/Core/Content/Test/',
        __DIR__ . '/src/Core/Framework/Test/',
        __DIR__ . '/src/Core/Framework/Feature.php',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPaths([
        __DIR__ . '/src/Core/Framework/Demodata/',
        __DIR__ . '/src/Core/Content/Cms/Command/CreatePageCommand.php',
    ], [ErrorType::SHADOW_DEPENDENCY]) // faker lib via `shopware/dev-tools`
    ->ignoreErrorsOnPackageAndPaths('symfony/var-dumper', [
        __DIR__ . '/src/Core/Framework/Script/Debugging/ScriptTraces.php',
        __DIR__ . '/src/Core/Profiling/',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackagesAndPaths(['symfony/doctrine-bridge', 'doctrine/sql-formatter'], [
        __DIR__ . '/src/Core/Profiling/',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])

    /** Optional dependencies for Flysystem */
    ->ignoreErrorsOnPath(__DIR__ . '/src/Core/Framework/Adapter/Filesystem/Adapter/', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackages(['google/cloud-storage', 'async-aws/s3'], [ErrorType::SHADOW_DEPENDENCY]) // provided by Flysystem adapter

    /** Used in `setasign/fpdi` if available, see https://github.com/setasign/fpdi#installation-with-composer */
    ->ignoreErrorsOnPackage('setasign/tfpdf', [ErrorType::UNUSED_DEPENDENCY])

    /** Has no classes, only used to avoid incompatible dependencies */
    ->ignoreErrorsOnPackage('shopware/conflicts', [ErrorType::UNUSED_DEPENDENCY])

    /** Used by 3rd party libraries */
    ->ignoreErrorsOnExtensions(['ext-pdo_mysql', 'ext-sodium', 'ext-xml'], [ErrorType::UNUSED_DEPENDENCY])

    /** Only used if `function_exists` is successful @see \Shopware\Core\Framework\Plugin\ExtensionExtractor */
    ->ignoreErrorsOnExtension('ext-apcu', [ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnExtension('ext-zend-opcache', [ErrorType::SHADOW_DEPENDENCY])

    /** Only used if `function_exists` is successful @see \Shopware\Core\Content\Media\Thumbnail\ThumbnailService */
    ->ignoreErrorsOnExtension('ext-exif', [ErrorType::SHADOW_DEPENDENCY])

    /** Only used if const defined check is successful */
    ->ignoreErrorsOnExtensionAndPath('ext-pcntl', 'src/Core/Framework/Adapter/Command/CacheWatchDelayedCommand.php', [ErrorType::SHADOW_DEPENDENCY])

    /** Optional dependency */
    ->ignoreErrorsOnExtension('ext-redis', [ErrorType::SHADOW_DEPENDENCY])

    /** Used for debugging */
    ->ignoreErrorsOnPackage('symfony/error-handler', [ErrorType::UNUSED_DEPENDENCY])

    /** Only used in dev envs. @see config/bundles.php which is scanned as prod */
    ->ignoreErrorsOnPackage('symfony/web-profiler-bundle', [ErrorType::DEV_DEPENDENCY_IN_PROD])

    /** Somehow triggered in our CI job and might not be valid locally */
    ->ignoreErrorsOnPackage('symfony/polyfill-php83', [ErrorType::UNUSED_DEPENDENCY])

    /** @deprecated tag:v6.8.0 - Remove these dependencies from the composer.json files */
    ->ignoreErrorsOnPackages([
        'doctrine/inflector',
        'symfony/monolog-bridge',
        'symfony/proxy-manager-bridge',
    ], [ErrorType::UNUSED_DEPENDENCY])
;

function configureImagickSupport(Configuration $config): void
{
    /** Optional dependency and only used if `extension_loaded` is successful in @see \Shopware\Core\Content\Media\DependencyInjection\ThumbnailProcessorCompilerPass */
    if (class_exists(Imagick::class)) {
        /** Differentiation is needed as the CI env has this extension installed */
        $config->ignoreErrorsOnExtension('ext-imagick', [ErrorType::SHADOW_DEPENDENCY]);
    } else {
        $config->ignoreUnknownClasses(['Imagick', 'ImagickPixel']);
    }
}
