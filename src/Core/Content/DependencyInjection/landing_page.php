<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageContentLayout\LandingPageSpecificationSource;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageSalesChannel\LandingPageSalesChannelDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTag\LandingPageTagDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationDefinition;
use Shopware\Core\Content\LandingPage\DataAbstractionLayer\LandingPageIndexer;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\LandingPage\LandingPageValidator;
use Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Shopware\Core\Content\LandingPage\SalesChannel\SalesChannelLandingPageDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Framework\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(LandingPageDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(LandingPageTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(LandingPageTagDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(LandingPageSalesChannelDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(LandingPageIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('landing_page.repository'),
            service('event_dispatcher'),
        ])
        ->tag('shopware.entity_indexer', ['priority' => 1000]);

    $services->set(SalesChannelLandingPageDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(LandingPageRoute::class)
        ->public()
        ->args([
            service('sales_channel.landing_page.repository'),
            service(SalesChannelCmsPageLoader::class),
            service(EntityCmsSlotConfigInheritanceBuilder::class),
            service(SalesChannelLandingPageDefinition::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(LandingPageValidator::class)
        ->args([
            service('validator'),
        ])
        ->tag('kernel.event_subscriber');

    // Content System
    $services->set(LandingPageContentLayoutDefinition::class)
        ->args([
            service(ContentLayoutMetadataDeriver::class),
        ])
        ->tag('shopware.entity.definition');

    $services->set(LandingPageSpecificationSource::class)
        ->args([
            service('landing_page_content_layout.repository'),
            service(LandingPageContentLayoutDefinition::class),
            service(EntityLayoutContextFactory::class),
        ])
        ->tag('content_system.entity_specification_source', ['priority' => 100]);
};
