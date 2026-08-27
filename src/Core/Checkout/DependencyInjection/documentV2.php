<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\DocumentV2\Aggregate\DocumentFile\DocumentFileDefinition;
use Shopware\Core\Checkout\DocumentV2\Compatibility\LegacyDocumentEventBridge;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentConfigLoader;
use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\Controller\DocumentV2Controller;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentArchiveGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentDependencyResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerationRequestResolver;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentGenerator;
use Shopware\Core\Checkout\DocumentV2\Generation\DocumentPersister;
use Shopware\Core\Checkout\DocumentV2\Generation\ReferencedDocumentResolver;
use Shopware\Core\Checkout\DocumentV2\Provider\CancellationInvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\CreditNoteDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\DeliveryNoteDataProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentDataProviderRegistry;
use Shopware\Core\Checkout\DocumentV2\Provider\DocumentMetaProvider;
use Shopware\Core\Checkout\DocumentV2\Provider\InvoiceDataProvider;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Renderer\HtmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Renderer\PdfRenderer;
use Shopware\Core\Checkout\DocumentV2\Renderer\ZugferdEmbeddedPdfRenderer;
use Shopware\Core\Checkout\DocumentV2\Renderer\ZugferdXmlRenderer;
use Shopware\Core\Checkout\DocumentV2\Service\CreditItemResolver;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentReader;
use Shopware\Core\Checkout\DocumentV2\Subscriber\DocumentBaseConfigSyncSubscriber;
use Shopware\Core\Checkout\DocumentV2\Subscriber\DocumentTypeNameSyncSubscriber;
use Shopware\Core\Checkout\DocumentV2\Template\DocumentTemplateRenderer;
use Shopware\Core\Checkout\DocumentV2\Template\ZugferdTwigExtension;
use Shopware\Core\Checkout\DocumentV2\Type\CancellationInvoiceDocumentType;
use Shopware\Core\Checkout\DocumentV2\Type\CreditNoteDocumentType;
use Shopware\Core\Checkout\DocumentV2\Type\DeliveryNoteDocumentType;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Checkout\DocumentV2\Type\InvoiceDocumentType;
use Shopware\Core\Checkout\DocumentV2\Xml\XmlFormatter;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

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

    $services->set(DocumentFileResolver::class);

    $services->set(DocumentConfigLoader::class)
        ->args([
            service('document_base_config.repository'),
            service('country.repository'),
            service('media.repository'),
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DocumentBaseConfigSyncSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DocumentTypeNameSyncSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DocumentMetaProvider::class)
        ->args([
            service(DocumentConfigLoader::class),
        ])
        ->tag('shopware.document_v2.provider');

    $services->set(InvoiceDataProvider::class)
        ->public()
        ->args([
            service(DocumentConfigLoader::class),
            service(DocumentTypeRegistry::class),
            service('validator'),
        ])
        ->tag('shopware.document_v2.provider');

    $services->set(DeliveryNoteDataProvider::class)
        ->public()
        ->tag('shopware.document_v2.provider');

    $services->set(CreditItemResolver::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CancellationInvoiceDataProvider::class)
        ->public()
        ->args([
            service(InvoiceDataProvider::class),
        ])
        ->tag('shopware.document_v2.provider');

    $services->set(CreditNoteDataProvider::class)
        ->public()
        ->args([
            service(InvoiceDataProvider::class),
            service(CreditItemResolver::class),
        ])
        ->tag('shopware.document_v2.provider');

    $services->set(DocumentDataProviderRegistry::class)
        ->args([
            tagged_iterator('shopware.document_v2.provider'),
        ]);

    $services->set(InvoiceDocumentType::class)
        ->tag('shopware.document_v2.type');

    $services->set(CancellationInvoiceDocumentType::class)
        ->tag('shopware.document_v2.type');

    $services->set(DeliveryNoteDocumentType::class)
        ->tag('shopware.document_v2.type');

    $services->set(CreditNoteDocumentType::class)
        ->tag('shopware.document_v2.type');

    $services->set(DocumentTypeRegistry::class)
        ->args([
            tagged_iterator('shopware.document_v2.type'),
        ]);

    $services->set(DocumentTemplateRenderer::class)
        ->public()
        ->args([
            service(TemplateFinder::class),
            service('twig'),
            service(Translator::class),
            service(SalesChannelContextFactory::class),
            service('event_dispatcher'),
            param('kernel.project_dir'),
        ]);

    $services->set(ZugferdTwigExtension::class)
        ->tag('twig.extension');

    $services->set(HtmlRenderer::class)
        ->public()
        ->args([
            service(DocumentTemplateRenderer::class),
        ])
        ->tag('shopware.document_v2.renderer');

    $services->set(XmlFormatter::class);

    $services->set(ZugferdXmlRenderer::class)
        ->public()
        ->args([
            service(DocumentTemplateRenderer::class),
            service(XmlFormatter::class),
        ])
        ->tag('shopware.document_v2.renderer');

    $services->set(PdfRenderer::class)
        ->public()
        ->args([
            param('shopware.dompdf.options'),
        ])
        ->tag('shopware.document_v2.renderer');

    $services->set(ZugferdEmbeddedPdfRenderer::class)
        ->public()
        ->args([
            param('kernel.shopware_version'),
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

    $services->set(DocumentArchiveGenerator::class)
        ->args([
            service(MediaService::class),
            service(Filesystem::class),
            service(DocumentRendererRegistry::class),
        ]);

    $services->set(DocumentPersister::class)
        ->args([
            service('document.repository'),
            service('document_file.repository'),
            service('document_type.repository'),
            service(MediaService::class),
            service(FileNameProvider::class),
            service('event_dispatcher'),
        ]);

    $services->set(DocumentReader::class)
        ->args([
            service('document.repository'),
            service(MediaService::class),
            service(DocumentRendererRegistry::class),
            service(DocumentFileResolver::class),
        ]);

    $services->set(ReferencedDocumentResolver::class)
        ->args([
            service(ReferenceInvoiceLoader::class),
            service(Connection::class),
        ]);

    // @deprecated tag:v6.9.0 - Remove together with document generation v1
    $services->set(LegacyDocumentEventBridge::class)
        ->args([
            service('event_dispatcher'),
        ]);

    $services->set(DocumentGenerator::class)
        ->public()
        ->args([
            service(DocumentDataProviderRegistry::class),
            service(DocumentRendererRegistry::class),
            service(DocumentNumberGenerator::class),
            service(DocumentPersister::class),
            service(DocumentDependencyResolver::class),
            service(ReferencedDocumentResolver::class),
            service('order.repository'),
            service(LegacyDocumentEventBridge::class),
        ]);

    $services->set(DocumentGenerationRequestResolver::class)
        ->args([
            service(DataValidator::class),
            service(DocumentTypeRegistry::class),
        ])
        ->tag('controller.argument_value_resolver');

    $services->set(DocumentV2Controller::class)
        ->public()
        ->args([
            service(DocumentGenerator::class),
            service(DocumentReader::class),
            service(DocumentTypeRegistry::class),
            service(DocumentArchiveGenerator::class),
            service('document.repository'),
            service(DocumentPersister::class),
            service(MediaService::class),
            service(FileNameProvider::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};
