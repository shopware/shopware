<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\Cms\CategoryNameCmsElementResolver;
use Shopware\Core\Content\Category\Cms\CategoryNavigationCmsElementResolver;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryBreadcrumbUpdater;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryIndexer;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryNonExistentExceptionHandler;
use Shopware\Core\Content\Category\SalesChannel\CategoryListRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\TreeBuildingNavigationRoute;
use Shopware\Core\Content\Category\Service\CachedDefaultCategoryLevelLoader;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopware\Core\Content\Category\Service\DefaultCategoryLevelLoader;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Subscriber\CategorySubscriber;
use Shopware\Core\Content\Category\Subscriber\CategoryTreeMovedSubscriber;
use Shopware\Core\Content\Category\Tree\CategoryTreePathResolver;
use Shopware\Core\Content\Category\Validation\EntryPointValidator;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CategoryDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(CategoryTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CategoryTagDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelCategoryDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(NavigationLoader::class)
        ->args([
            service('event_dispatcher'),
            service(NavigationRoute::class),
        ]);

    $services->set(NavigationRoute::class)
        ->public()
        ->args([
            service(Connection::class),
            service('sales_channel.category.repository'),
            service(CacheTagCollector::class),
            service(CategoryTreePathResolver::class),
            service(DefaultCategoryLevelLoader::class),
        ]);

    $services->set(DefaultCategoryLevelLoader::class)
        ->args([
            service('sales_channel.category.repository'),
        ]);

    $services->set(CachedDefaultCategoryLevelLoader::class)
        ->decorate(DefaultCategoryLevelLoader::class)
        ->args([
            service('cache.object'),
            service('event_dispatcher'),
            service(CachedDefaultCategoryLevelLoader::class . '.inner'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategoryTreePathResolver::class);

    $services->set(TreeBuildingNavigationRoute::class)
        ->decorate(NavigationRoute::class, null, -2000)
        ->public()
        ->args([
            service(TreeBuildingNavigationRoute::class . '.inner'),
        ]);

    $services->set(CategoryRoute::class)
        ->public()
        ->args([
            service('sales_channel.category.repository'),
            service(SalesChannelCmsPageLoader::class),
            service(EntityCmsSlotConfigInheritanceBuilder::class),
            service(SalesChannelCategoryDefinition::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(CategoryListRoute::class)
        ->public()
        ->args([
            service('sales_channel.category.repository'),
        ]);

    $services->set(CategoryIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('category.repository'),
            service(ChildCountUpdater::class),
            service(TreeUpdater::class),
            service(CategoryBreadcrumbUpdater::class),
            service('event_dispatcher'),
            service('messenger.default_bus'),
        ])
        // Must run before ProductIndexer so ProductCategoryDenormalizer can include parent category paths.
        ->tag('shopware.entity_indexer', ['priority' => 105]);

    $services->set(CategoryBreadcrumbUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(TreeUpdater::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(CategoryBreadcrumbBuilder::class)
        ->args([
            service('category.repository'),
            service('sales_channel.product.repository'),
            service(Connection::class),
            service(EntityRouteResolver::class),
        ]);

    $services->set(CategoryUrlGenerator::class)
        ->args([
            service(EntityRouteResolver::class),
        ]);

    $services->set(EntryPointValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategorySubscriber::class)
        ->args([
            service(SystemConfigService::class),
            service(CategoryUrlGenerator::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategoryTreeMovedSubscriber::class)
        ->args([
            service(EntityIndexerRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategoryNonExistentExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(CategoryNavigationCmsElementResolver::class)
        ->args([
            service(NavigationLoader::class),
        ])
        ->tag('shopware.cms.data_resolver');

    $services->set(CategoryNameCmsElementResolver::class)
        ->args([
            service(HtmlSanitizer::class),
        ])
        ->tag('shopware.cms.data_resolver');
};
