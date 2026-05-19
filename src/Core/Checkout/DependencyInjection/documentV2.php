<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentDependencyResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Renderer\HtmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Subscriber\DocumentBaseConfigSyncSubscriber;
use Shopware\Core\Checkout\DocumentV2\Twig\DocumentTemplateRenderer;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DocumentFileDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(DocumentNumberGenerator::class)
        ->args([
            service(NumberRangeValueGeneratorInterface::class),
        ]);

    $services->set(DocumentConfigLoader::class)
        ->args([
            service('document_base_config.repository'),
            service('country.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DocumentBaseConfigSyncSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(InvoiceDataProvider::class)
        ->public()
        ->args([
            service(DocumentConfigLoader::class),
            service('validator'),
        ])
        ->tag('shopware.document_v2.provider');

    $services->set(DocumentDataProviderRegistry::class)
        ->args([
            tagged_iterator('shopware.document_v2.provider'),
        ]);

    $services->set(DocumentTemplateRenderer::class)
        ->public()
        ->args([
            service(TemplateFinder::class),
            service('twig'),
            service(Translator::class),
            service(SalesChannelContextFactory::class),
            param('kernel.project_dir'),
        ]);

    $services->set(HtmlRenderer::class)
        ->public()
        ->args([
            service(DocumentTemplateRenderer::class),
        ])
        ->tag('shopware.document_v2.renderer');

    $services->set(DocumentRendererRegistry::class)
        ->args([
            tagged_iterator('shopware.document_v2.renderer'),
        ]);

    $services->set(DocumentDependencyResolver::class)
        ->args([
            service(DocumentRendererRegistry::class),
        ]);

    $services->set(DocumentPersister::class)
        ->args([
            service('document.repository'),
            service('document_file.repository'),
            service('document_type.repository'),
            service(MediaService::class),
        ]);

    $services->set(DocumentGenerator::class)
        ->args([
            service(DocumentDataProviderRegistry::class),
            service(DocumentRendererRegistry::class),
            service(DocumentNumberGenerator::class),
            service(DocumentPersister::class),
            service(DocumentDependencyResolver::class),
            service('order.repository'),
        ]);
};
