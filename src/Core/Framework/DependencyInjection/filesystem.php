<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use League\Flysystem\FilesystemOperator;
use Shopware\Core\Framework\Adapter\Asset\AssetInstallCommand;
use Shopware\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Shopware\Core\Framework\Adapter\Asset\FlysystemLastModifiedVersionStrategy;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AwsS3v3Factory;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\GoogleStorageFactory;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\LocalFactory;
use Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Filesystem
    $services->set(FilesystemFactory::class)
        ->args([
            tagged_iterator('shopware.filesystem.factory'),
        ]);

    $services->set('shopware.filesystem.public', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('shopware.filesystem.public'),
        ]);

    $services->set('shopware.filesystem.private', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'privateFactory'])
        ->args([
            param('shopware.filesystem.private'),
        ]);

    $services->set('shopware.filesystem.temp', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'privateFactory'])
        ->args([
            param('shopware.filesystem.temp'),
        ]);

    $services->set('shopware.filesystem.theme', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('shopware.filesystem.theme'),
        ]);

    $services->set('shopware.filesystem.sitemap', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('shopware.filesystem.sitemap'),
        ]);

    $services->set('shopware.filesystem.asset', FilesystemOperator::class)
        ->public()
        ->factory([service(FilesystemFactory::class), 'factory'])
        ->args([
            param('shopware.filesystem.asset'),
        ]);

    $services->set(FilesystemFactory::class . '.local', LocalFactory::class)
        ->tag('shopware.filesystem.factory');

    $services->set(FilesystemFactory::class . '.amazon_s3', AwsS3v3Factory::class)
        ->args([
            param('shopware.filesystem.batch_write_size'),
            service('shopware.filesystem.s3.client')->nullOnInvalid(),
        ])
        ->tag('shopware.filesystem.factory');

    $services->set(FilesystemFactory::class . '.google_storage', GoogleStorageFactory::class)
        ->tag('shopware.filesystem.factory');

    $services->set('console.command.assets_install', AssetInstallCommand::class)
        ->args([
            service('kernel'),
            service(AssetService::class),
            service(ActiveAppsLoader::class),
        ])
        ->tag('console.command');

    // Assets
    $services->set('shopware.asset.public', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('shopware.filesystem.public.url'),
            ],
            service('assets.empty_version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ])
        ->tag('shopware.asset', ['asset' => 'public']);

    $services->set('shopware.asset.public.version_strategy', FlysystemLastModifiedVersionStrategy::class)
        ->args([
            'theme-metaData',
            service('shopware.filesystem.public'),
            service('cache.object'),
        ]);

    $services->set('shopware.asset.theme.version_strategy', FlysystemLastModifiedVersionStrategy::class)
        ->args([
            'theme-metaData',
            service('shopware.filesystem.theme'),
            service('cache.object'),
        ]);

    $services->set('shopware.asset.asset.version_strategy', FlysystemLastModifiedVersionStrategy::class)
        ->args([
            'asset-metaData',
            service('shopware.filesystem.asset'),
            service('cache.object'),
        ]);

    $services->set('shopware.asset.asset', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('shopware.filesystem.asset.url'),
            ],
            service('shopware.asset.asset.version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ])
        ->tag('shopware.asset', ['asset' => 'asset']);

    $services->set('shopware.asset.asset_without_versioning', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('shopware.filesystem.asset.url'),
            ],
            service('assets.empty_version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ]);

    $services->set('shopware.asset.sitemap', FallbackUrlPackage::class)
        ->lazy()
        ->args([
            [
                param('shopware.filesystem.sitemap.url'),
            ],
            service('assets.empty_version_strategy'),
            service('request_stack')->nullOnInvalid(),
        ])
        ->tag('shopware.asset', ['asset' => 'sitemap']);

    $services->set(CopyBatchInputFactory::class);
};
