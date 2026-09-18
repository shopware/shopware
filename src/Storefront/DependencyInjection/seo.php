<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrlPersister;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Content\Seo\SeoUrlUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlLifecycleHandler;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteLoader;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRouteProvider;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlSynchronizer;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlUpdateListener;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncHandler;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener;
use Shopware\Storefront\Framework\Seo\SeoUrlRouteNameEnumProvider;
use Shopware\Storefront\Framework\Seo\StorefrontCategoryUrlGenerator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ProductPageSeoUrlRoute::class)
        ->args([
            service(ProductDefinition::class),
        ])
        ->tag('shopware.seo_url.route');

    $services->set(NavigationPageSeoUrlRoute::class)
        ->args([
            service(CategoryDefinition::class),
            service(CategoryBreadcrumbBuilder::class),
        ])
        ->tag('shopware.seo_url.route');

    $services->set(LandingPageSeoUrlRoute::class)
        ->args([
            service(LandingPageDefinition::class),
        ])
        ->tag('shopware.seo_url.route');

    $services->set(SeoUrlUpdateListener::class)
        ->args([
            service(SeoUrlUpdater::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AppSeoUrlRouteProvider::class)
        ->args([
            service('app_seo_url_route.repository'),
            service('cache.object'),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppSeoUrlRouteLoader::class)
        ->args([
            service(AppSeoUrlRouteProvider::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.seo_url.route_loader');

    $services->set(AppSeoUrlSynchronizer::class)
        ->args([
            service(AppSeoUrlRouteProvider::class),
            service('sales_channel.repository'),
            service(SeoUrlPersister::class),
            service(SeoUrlUpdater::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(AppSeoUrlSyncHandler::class)
        ->args([
            service(AppSeoUrlSynchronizer::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(AppSeoUrlLifecycleHandler::class)
        ->args([
            service(MessageBusInterface::class),
        ])
        ->tag('shopware.app_lifecycle.handler', ['priority' => -1500]);

    $services->set(AppSeoUrlUpdateListener::class)
        ->args([
            service(AppSeoUrlRouteProvider::class),
            service(SeoUrlUpdater::class),
            service(MessageBusInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SeoUrlRouteNameEnumProvider::class)
        ->args([
            service(SeoUrlRouteRegistry::class),
        ])
        ->tag('shopware.api.enum_provider');

    $services->set(StorefrontCategoryUrlGenerator::class)
        ->decorate(CategoryUrlGenerator::class)
        ->args([
            service(StorefrontCategoryUrlGenerator::class . '.inner'),
            service('router'),
        ]);
};
