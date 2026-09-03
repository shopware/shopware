<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use setasign\Fpdi\Tfpdf\Fpdi;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Customer\Service\GuestAuthenticator;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigValidator;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentType\DocumentTypeDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationDefinition;
use Shopware\Core\Checkout\Document\Api\DocumentTypeTechnicalNameFkResolver;
use Shopware\Core\Checkout\Document\Controller\DocumentController;
use Shopware\Core\Checkout\Document\DocumentDefinition;
use Shopware\Core\Checkout\Document\DocumentGeneratorController;
use Shopware\Core\Checkout\Document\Renderer\CreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DeliveryNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererRegistry;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\StornoRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCancellationInvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdCreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedCancellationInvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedCreditNoteRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdEmbeddedRenderer;
use Shopware\Core\Checkout\Document\Renderer\ZugferdRenderer;
use Shopware\Core\Checkout\Document\SalesChannel\DocumentRoute;
use Shopware\Core\Checkout\Document\Service\DocumentConfigLoader;
use Shopware\Core\Checkout\Document\Service\DocumentFileRendererRegistry;
use Shopware\Core\Checkout\Document\Service\DocumentGenerator;
use Shopware\Core\Checkout\Document\Service\DocumentMerger;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Service\PdfRenderer;
use Shopware\Core\Checkout\Document\Service\ReferenceInvoiceLoader;
use Shopware\Core\Checkout\Document\Service\ZugferdEmbeddedService;
use Shopware\Core\Checkout\Document\Subscriber\DocumentDeleteSubscriber;
use Shopware\Core\Checkout\Document\Twig\DocumentTemplateRenderer;
use Shopware\Core\Checkout\Document\Zugferd\ZugferdBuilder;
use Shopware\Core\Checkout\DocumentV2\Renderer\DocumentRendererRegistry as DocumentV2RendererRegistry;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentFileResolver;
use Shopware\Core\Checkout\DocumentV2\Service\DocumentReader;
use Shopware\Core\Checkout\DocumentV2\Type\DocumentTypeRegistry;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service_closure;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DocumentDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(DocumentTypeDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(DocumentTypeTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(DocumentBaseConfigDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(DocumentBaseConfigSalesChannelDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(DocumentTemplateRenderer::class)
        ->args([
            service(TemplateFinder::class),
            service('twig'),
            service(Translator::class),
            service(SalesChannelContextFactory::class),
            service('event_dispatcher'),
        ]);

    $services->set(DocumentGeneratorController::class)
        ->public()
        ->args([
            service(DocumentGenerator::class),
            service('serializer'),
            service(DataValidator::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set('pdf.merger', Fpdi::class);

    $services->set(DocumentConfigLoader::class)
        ->args([
            service('document_base_config.repository'),
            service('country.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ReferenceInvoiceLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ZugferdEmbeddedService::class);

    $services->set(InvoiceRenderer::class)
        ->args([
            service('order.repository'),
            service(DocumentConfigLoader::class),
            service('event_dispatcher'),
            service(NumberRangeValueGeneratorInterface::class),
            service(Connection::class),
            service(DocumentFileRendererRegistry::class),
            service('validator'),
            service(ClockInterface::class),
        ])
        ->tag('document.renderer');

    $services->set(DeliveryNoteRenderer::class)
        ->args([
            service('order.repository'),
            service(DocumentConfigLoader::class),
            service('event_dispatcher'),
            service(NumberRangeValueGeneratorInterface::class),
            service(Connection::class),
            service(DocumentFileRendererRegistry::class),
            service(ClockInterface::class),
        ])
        ->tag('document.renderer');

    $services->set(StornoRenderer::class)
        ->args([
            service('order.repository'),
            service(DocumentConfigLoader::class),
            service('event_dispatcher'),
            service(NumberRangeValueGeneratorInterface::class),
            service(ReferenceInvoiceLoader::class),
            service(Connection::class),
            service(DocumentFileRendererRegistry::class),
            service('validator'),
            service(ClockInterface::class),
        ])
        ->tag('document.renderer');

    $services->set(CreditNoteRenderer::class)
        ->args([
            service('order.repository'),
            service(DocumentConfigLoader::class),
            service('event_dispatcher'),
            service(NumberRangeValueGeneratorInterface::class),
            service(ReferenceInvoiceLoader::class),
            service(Connection::class),
            service(DocumentFileRendererRegistry::class),
            service('validator'),
            service(ClockInterface::class),
        ])
        ->tag('document.renderer');

    $services->set(DocumentRendererRegistry::class)
        ->args([
            tagged_iterator('document.renderer'),
        ]);

    $services->set(PdfRenderer::class)
        ->args([
            param('shopware.dompdf.options'),
            service(DocumentTemplateRenderer::class),
            param('kernel.project_dir'),
            service(ExtensionDispatcher::class),
        ])
        ->tag('document_type.renderer', ['key' => 'pdf']);

    $services->set(DocumentGenerator::class)
        ->args([
            service(DocumentRendererRegistry::class),
            service(DocumentFileRendererRegistry::class),
            service(MediaService::class),
            service('document.repository'),
            service(Connection::class),
            service(ClockInterface::class),
            service(DocumentFileResolver::class),
            service('event_dispatcher'),
        ]);

    $services->set(DocumentMerger::class)
        ->args([
            service('document.repository'),
            service(MediaService::class),
            service(DocumentGenerator::class),
            service('pdf.merger'),
            service(Filesystem::class),
        ]);

    $services->set(DocumentController::class)
        ->public()
        ->args([
            service(DocumentGenerator::class),
            service(DocumentMerger::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(DocumentRoute::class)
        ->public()
        ->args([
            service(DocumentGenerator::class),
            service(DocumentReader::class),
            service('document.repository'),
            service('shopware.rate_limiter'),
            service(GuestAuthenticator::class),
            tagged_iterator('document_type.renderer', 'key'),
        ]);

    $services->set(HtmlRenderer::class)
        ->args([
            service(DocumentTemplateRenderer::class),
            param('kernel.project_dir'),
            service(ExtensionDispatcher::class),
        ])
        ->tag('document_type.renderer', ['key' => 'html']);

    $services->set(DocumentFileRendererRegistry::class)
        ->args([
            tagged_iterator('document_type.renderer', 'key'),
        ]);

    $services->set(ZugferdRenderer::class)
        ->args([
            service('order.repository'),
            service(Connection::class),
            service(ZugferdBuilder::class),
            service('event_dispatcher'),
            service(DocumentConfigLoader::class),
            service(NumberRangeValueGeneratorInterface::class),
            service(ClockInterface::class),
        ])
        ->tag('document.renderer');

    $services->set(ZugferdEmbeddedRenderer::class)
        ->args([
            service(InvoiceRenderer::class),
            service(ZugferdRenderer::class),
            service(ZugferdEmbeddedService::class),
            param('kernel.shopware_version'),
        ])
        ->tag('document.renderer');

    $services->set(ZugferdCancellationInvoiceRenderer::class)
        ->args([
            service('order.repository'),
            service(DocumentConfigLoader::class),
            service('event_dispatcher'),
            service(NumberRangeValueGeneratorInterface::class),
            service(ReferenceInvoiceLoader::class),
            service(Connection::class),
            service(ZugferdBuilder::class),
            service(ClockInterface::class),
        ])
        ->tag('document.renderer');

    $services->set(ZugferdCreditNoteRenderer::class)
        ->args([
            service('order.repository'),
            service(DocumentConfigLoader::class),
            service('event_dispatcher'),
            service(NumberRangeValueGeneratorInterface::class),
            service(ReferenceInvoiceLoader::class),
            service(Connection::class),
            service(ZugferdBuilder::class),
            service(ClockInterface::class),
        ])
        ->tag('document.renderer');

    $services->set(ZugferdEmbeddedCancellationInvoiceRenderer::class)
        ->args([
            service(StornoRenderer::class),
            service(ZugferdCancellationInvoiceRenderer::class),
            service(ZugferdEmbeddedService::class),
            param('kernel.shopware_version'),
        ])
        ->tag('document.renderer');

    $services->set(ZugferdEmbeddedCreditNoteRenderer::class)
        ->args([
            service(CreditNoteRenderer::class),
            service(ZugferdCreditNoteRenderer::class),
            service(ZugferdEmbeddedService::class),
            param('kernel.shopware_version'),
        ])
        ->tag('document.renderer');

    $services->set(ZugferdBuilder::class)
        ->args([
            service('event_dispatcher'),
            service(AmountCalculator::class),
        ]);

    $services->set(DocumentDeleteSubscriber::class)
        ->args([
            service('document.repository'),
            service('media.repository'),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DocumentTypeTechnicalNameFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.sync.fk_resolver');

    $services->set(DocumentBaseConfigValidator::class)
        ->args([
            service(ClockInterface::class),
            service(Connection::class),
            service(DocumentTypeRegistry::class),
            service_closure(DocumentV2RendererRegistry::class),
        ])
        ->tag('kernel.event_subscriber');
};
