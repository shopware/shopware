<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationFactory;
use Shopware\Core\Framework\ContentSystem\Adapter\RenderingSpecificationResolver;
use Shopware\Core\Framework\ContentSystem\Validation\LayoutRootSourceReader;
use Shopware\Storefront\ContentSystem\Extension\ContentLayoutExtension;
use Shopware\Storefront\ContentSystem\Extension\SalesChannelDomainExtension;
use Shopware\Storefront\ContentSystem\Extension\SalesChannelExtension;
use Shopware\Storefront\ContentSystem\FooterContentLayout\FooterContentLayoutDefinition;
use Shopware\Storefront\ContentSystem\FooterContentLayout\FooterSpecificationSource;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutDefinition;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderSpecificationSource;
use Shopware\Storefront\ContentSystem\Validation\HeaderFooterAssignmentWriteValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    // Entity Definitions
    $services->set(HeaderContentLayoutDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(FooterContentLayoutDefinition::class)
        ->tag('shopware.entity.definition');

    // Entity Extensions
    $services->set(SalesChannelExtension::class)
        ->tag('shopware.entity.extension');

    $services->set(SalesChannelDomainExtension::class)
        ->tag('shopware.entity.extension');

    $services->set(ContentLayoutExtension::class)
        ->tag('shopware.entity.extension');

    // Section Resolvers (Header/Footer)
    $services->set('content_system.section_resolver.header', RenderingSpecificationResolver::class)
        ->args([
            [service(HeaderSpecificationSource::class)],
            service(RenderingSpecificationFactory::class),
        ])
        ->tag('content_system.section_resolver', ['section' => 'header']);

    $services->set('content_system.section_resolver.footer', RenderingSpecificationResolver::class)
        ->args([
            [service(FooterSpecificationSource::class)],
            service(RenderingSpecificationFactory::class),
        ])
        ->tag('content_system.section_resolver', ['section' => 'footer']);

    // Domain-Aware Specification Sources
    $services->set(HeaderSpecificationSource::class)
        ->args([
            service(DomainAwareLayoutResolver::class),
            service('header_content_layout.repository'),
        ])
        ->tag('content_system.specification_source', ['section' => 'header']);

    $services->set(FooterSpecificationSource::class)
        ->args([
            service(DomainAwareLayoutResolver::class),
            service('footer_content_layout.repository'),
        ])
        ->tag('content_system.specification_source', ['section' => 'footer']);

    // Header/Footer Binding Gate (§8.2, empty root context)
    $services->set(HeaderFooterAssignmentWriteValidator::class)
        ->args([
            service(LayoutRootSourceReader::class),
        ])
        ->tag('kernel.event_subscriber');
};
