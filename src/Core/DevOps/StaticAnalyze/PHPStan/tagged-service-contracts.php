<?php declare(strict_types=1);

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Shopware\Core\Checkout\Cart\CartDataCollectorInterface;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupPackagerInterface;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupSorterInterface;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\Checkout\Cart\TaxProvider\AbstractTaxProvider;
use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\LegacyEncoderInterface;
use Shopware\Core\Checkout\Document\Renderer\AbstractDocumentRenderer;
use Shopware\Core\Checkout\Document\Service\AbstractDocumentTypeRenderer;
use Shopware\Core\Checkout\Gateway\Command\Handler\AbstractCheckoutGatewayCommandHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterPickerInterface;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\FilterSorterInterface;
use Shopware\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\Storer\FlowStorer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\AbstractEntitySerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\AbstractFieldSerializer;
use Shopware\Core\Content\ImportExport\Processing\Reader\AbstractReaderFactory;
use Shopware\Core\Content\ImportExport\Processing\Writer\AbstractWriterFactory;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\File\AbstractFileContentValidator;
use Shopware\Core\Content\Media\Metadata\MetadataLoader\MetadataLoaderInterface;
use Shopware\Core\Content\Media\TypeDetector\TypeDetectorInterface;
use Shopware\Core\Content\Product\Cms\ProductSlider\AbstractProductSliderProcessor;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\AbstractStockUpdateFilter;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\AbstractListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AbstractListingProcessor;
use Shopware\Core\Content\ProductExport\Provider\AbstractAgenticCommerceProductExportProvider;
use Shopware\Core\Content\ProductExport\Validator\ValidatorInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\EntitySeoUrlRouteInterface;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteInterface;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\MailFlowDataProviderInterface;
use Shopware\Core\Content\Sitemap\ConfigHandler\ConfigHandlerInterface;
use Shopware\Core\Content\Sitemap\Provider\AbstractUrlProvider;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\AdapterFactoryInterface;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\TemplateNamespaceHierarchyBuilderInterface;
use Shopware\Core\Framework\Api\Sync\AbstractFkResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldEnumProviderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\Demodata\DemodataGeneratorInterface;
use Shopware\Core\Framework\Increment\AbstractIncrementer;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\Routing\AbstractRouteScope;
use Shopware\Core\Framework\Routing\RouteScopeWhitelistInterface;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\SystemCheck\BaseCheck;
use Shopware\Core\Framework\Telemetry\Metrics\Metric\PeriodicMetricCollectorInterface;
use Shopware\Core\Framework\Telemetry\Metrics\MetricTransportInterface;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\AbstractValueGenerator;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Shopware\Core\System\Snippet\Filter\SnippetFilterInterface;
use Shopware\Core\System\Tax\TaxRuleType\TaxRuleTypeFilterInterface;
use Shopware\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Shopware\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Shopware\Storefront\Framework\Captcha\AbstractCaptcha;
use Shopware\Storefront\Framework\Media\StorefrontMediaValidatorInterface;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Twig\Extension\ExtensionInterface;

return [
    'parameters' => [
        // Changing a mapped contract class is a backward compatibility break and must not be done in a minor release.
        'shopwareTaggedServiceContractTagContracts' => [
            'document.renderer' => AbstractDocumentRenderer::class,
            'document_type.renderer' => AbstractDocumentTypeRenderer::class,
            'flow.action' => FlowAction::class,
            'flow.storer' => FlowStorer::class,
            'lineitem.group.packager' => LineItemGroupPackagerInterface::class,
            'lineitem.group.sorter' => LineItemGroupSorterInterface::class,
            'messenger.receiver' => ReceiverInterface::class,
            'promotion.filter.picker' => FilterPickerInterface::class,
            'promotion.filter.sorter' => FilterSorterInterface::class,
            'shopware.api.enum_provider' => FieldEnumProviderInterface::class,
            'shopware.app_script.twig.extension' => ExtensionInterface::class,
            'shopware.cart.collector' => CartDataCollectorInterface::class,
            'shopware.cart.line_item.factory' => LineItemFactoryInterface::class,
            'shopware.cart.processor' => CartProcessorInterface::class,
            'shopware.cart.validator' => CartValidatorInterface::class,
            'shopware.checkout.gateway.command' => AbstractCheckoutGatewayCommandHandler::class,
            'shopware.cms.data_resolver' => CmsElementResolverInterface::class,
            'shopware.cms.product_slider.processor' => AbstractProductSliderProcessor::class,
            'shopware.dal.exception_handler' => ExceptionHandlerInterface::class,
            'shopware.demodata_generator' => DemodataGeneratorInterface::class,
            'shopware.elastic.admin-searcher-index' => AbstractAdminIndexer::class,
            'shopware.entity.definition' => EntityDefinition::class,
            'shopware.entity.hookable' => [EntityDefinition::class, Entity::class],
            'shopware.entity.seo_url.route' => EntitySeoUrlRouteInterface::class,
            'shopware.entity_indexer' => EntityIndexer::class,
            'shopware.es.definition' => AbstractElasticsearchDefinition::class,
            'shopware.filesystem.factory' => AdapterFactoryInterface::class,
            'shopware.import_export.entity_serializer' => AbstractEntitySerializer::class,
            'shopware.import_export.field_serializer' => AbstractFieldSerializer::class,
            'shopware.import_export.reader_factory' => AbstractReaderFactory::class,
            'shopware.import_export.writer_factory' => AbstractWriterFactory::class,
            'shopware.increment.gateway' => AbstractIncrementer::class,
            'shopware.legacy_encoder' => LegacyEncoderInterface::class,
            'shopware.listing.filter.handler' => AbstractListingFilterHandler::class,
            'shopware.listing.processor' => AbstractListingProcessor::class,
            'shopware.mail.data_provider' => MailFlowDataProviderInterface::class,
            'shopware.media.file_content.validator' => AbstractFileContentValidator::class,
            'shopware.media_type.detector' => TypeDetectorInterface::class,
            'shopware.metadata.loader' => MetadataLoaderInterface::class,
            'shopware.metric_transport_factory' => MetricTransportInterface::class,
            'shopware.oauth.scope' => ScopeEntityInterface::class,
            'shopware.path.strategy' => AbstractMediaPathStrategy::class,
            'shopware.payment.method' => AbstractPaymentHandler::class,
            'shopware.product.stock_filter' => AbstractStockUpdateFilter::class,
            'shopware.product_export.provider' => AbstractAgenticCommerceProductExportProvider::class,
            'shopware.product_export.validator' => ValidatorInterface::class,
            'shopware.route_scope' => AbstractRouteScope::class,
            'shopware.route_scope_whitelist' => RouteScopeWhitelistInterface::class,
            'shopware.rule.definition' => Rule::class,
            'shopware.scheduled.task' => ScheduledTask::class,
            'shopware.seo_url.route' => SeoUrlRouteInterface::class,
            'shopware.sitemap.config_handler' => ConfigHandlerInterface::class,
            'shopware.sitemap_url_provider' => AbstractUrlProvider::class,
            'shopware.snippet.filter' => SnippetFilterInterface::class,
            'shopware.storefront.captcha' => AbstractCaptcha::class,
            'shopware.sync.fk_resolver' => AbstractFkResolver::class,
            'shopware.system_check' => BaseCheck::class,
            'shopware.tax.provider' => AbstractTaxProvider::class,
            'shopware.telemetry.periodic_metric_collector' => PeriodicMetricCollectorInterface::class,
            'shopware.twig.hierarchy_builder' => TemplateNamespaceHierarchyBuilderInterface::class,
            'shopware.value_generator_connector' => AbstractIncrementStorage::class,
            'shopware.value_generator_pattern' => AbstractValueGenerator::class,
            'storefront.media.upload.validator' => StorefrontMediaValidatorInterface::class,
            'tax.rule_type_filter' => TaxRuleTypeFilterInterface::class,
        ],
    ],
];
