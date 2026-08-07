<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbDataLoader;
use Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbLoaderConfigSerializer;
use Shopware\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRoute;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(BreadcrumbRoute::class)
        ->public()
        ->args([
            service(CategoryBreadcrumbBuilder::class),
            service(CacheTagCollector::class),
        ]);

    // Content System
    $services->set(BreadcrumbDataLoader::class)
        ->args([
            service(BreadcrumbRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(BreadcrumbLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');
};
