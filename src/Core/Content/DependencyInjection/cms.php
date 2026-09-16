<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\DataAbstractionLayer\FieldSerializer\SlotConfigFieldSerializer;
use Shopware\Core\Content\Cms\DataResolver\CmsSlotsDataResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\FormCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\HtmlCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\TextCmsElementResolver;
use Shopware\Core\Content\Cms\SalesChannel\CmsRoute;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\Cms\Service\CmsFormSlotConfigResolver;
use Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopware\Core\Content\Cms\Subscriber\CmsPageDefaultChangeSubscriber;
use Shopware\Core\Content\Cms\Subscriber\CmsVersionMergeSubscriber;
use Shopware\Core\Content\Cms\Subscriber\UnusedMediaSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\System\Salutation\AbstractSalutationsSorter;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRoute;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CmsPageDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CmsPageTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CmsSectionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CmsBlockDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CmsSlotDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CmsSlotTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CmsSlotsDataResolver::class)
        ->public()
        ->args([
            tagged_iterator('shopware.cms.data_resolver'),
            ['product' => service('sales_channel.product.repository')],
            service(DefinitionInstanceRegistry::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(TextCmsElementResolver::class)
        ->args([
            service(HtmlSanitizer::class),
        ])
        ->tag('shopware.cms.data_resolver');

    $services->set(HtmlCmsElementResolver::class)
        ->tag('shopware.cms.data_resolver');

    $services->set(FormCmsElementResolver::class)
        ->args([
            service(SalutationRoute::class),
            service(AbstractSalutationsSorter::class),
        ])
        ->tag('shopware.cms.data_resolver');

    $services->set(SlotConfigFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('shopware.field_serializer');

    $services->set(SalesChannelCmsPageLoader::class)
        ->args([
            service('cms_page.repository'),
            service(CmsSlotsDataResolver::class),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
        ]);

    $services->set(CmsFormSlotConfigResolver::class)
        ->args([
            service('category.repository'),
            service('landing_page.repository'),
            service('product.repository'),
            service('cms_slot.repository'),
            service(SystemConfigService::class),
        ]);

    $services->set(EntityCmsSlotConfigInheritanceBuilder::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CmsRoute::class)
        ->public()
        ->args([
            service(SalesChannelCmsPageLoader::class),
        ]);

    $services->set(CmsPageDefaultChangeSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UnusedMediaSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CmsVersionMergeSubscriber::class)
        ->tag('kernel.event_subscriber');
};
