<?php declare(strict_types=1);

namespace Shopware\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Shopware\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Shopware\Core\Content\MeasurementSystem\Unit\MeasurementUnitConverter;
use Shopware\Core\Content\Media\UnusedMediaPurger;
use Shopware\Core\Content\Product\AbstractIsNewDetector;
use Shopware\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\AbstractPropertyGroupSorter;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingExceptionHandler;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductSpecificationSource;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingTranslation\ProductCrossSellingTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCustomFieldSet\ProductCustomFieldSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDownload\ProductDownloadDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductOption\ProductOptionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductProperty\ProductPropertyDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigExceptionHandler;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfigField\ProductSearchConfigFieldExceptionHandler;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductStreamMapping\ProductStreamMappingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Api\ProductActionController;
use Shopware\Core\Content\Product\Api\ProductNumberFkResolver;
use Shopware\Core\Content\Product\Cart\ProductGateway;
use Shopware\Core\Content\Product\Cart\ProductLineItemCommandValidator;
use Shopware\Core\Content\Product\Cleanup\CleanupProductKeywordDictionaryTask;
use Shopware\Core\Content\Product\Cleanup\CleanupProductKeywordDictionaryTaskHandler;
use Shopware\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTask;
use Shopware\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTaskHandler;
use Shopware\Core\Content\Product\Cms\BuyBoxCmsElementResolver;
use Shopware\Core\Content\Product\Cms\CrossSellingCmsElementResolver;
use Shopware\Core\Content\Product\Cms\ManufacturerLogoCmsElementResolver;
use Shopware\Core\Content\Product\Cms\ProductBoxCmsElementResolver;
use Shopware\Core\Content\Product\Cms\ProductDescriptionReviewsCmsElementResolver;
use Shopware\Core\Content\Product\Cms\ProductListingCmsElementResolver;
use Shopware\Core\Content\Product\Cms\ProductNameCmsElementResolver;
use Shopware\Core\Content\Product\Cms\ProductSlider\ProductStreamProcessor;
use Shopware\Core\Content\Product\Cms\ProductSlider\StaticProductProcessor;
use Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingLoaderConfigSerializer;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductDetailDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductDetailLoaderConfigSerializer;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingLoaderConfigSerializer;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewLoaderConfigSerializer;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSearchDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSearchLoaderConfigSerializer;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSuggestDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSuggestLoaderConfigSerializer;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceAccessorBuilder;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceQuantitySelector;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserBuilder;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductExceptionHandler;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\RatingAverageUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdate\StockUpdateFilterProvider;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Content\Product\Garan\GaranLabelProductValidator;
use Shopware\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\Garan\GaranLabelTwigFilter;
use Shopware\Core\Content\Product\IsNewDetector;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\ProductTypeRegistry;
use Shopware\Core\Content\Product\ProductVariationBuilder;
use Shopware\Core\Content\Product\PropertyGroupSorter;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\AvailableCombinationLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRoute;
use Shopware\Core\Content\Product\SalesChannel\Garan\AbstractGaranLabelRoute;
use Shopware\Core\Content\Product\SalesChannel\Garan\GaranLabelRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\AbstractListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ManufacturerListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\PriceListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\PropertyListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\RatingListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\ShippingFreeListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AggregationListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AssociationLoadingListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\BehaviorListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\CompressedCriteriaListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\PagingListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\SortingListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ResolveCriteriaProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Price\AppScriptProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\ProductListRoute;
use Shopware\Core\Content\Product\SalesChannel\PurchaseLimit\ProductPurchaseLimitRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewSaveRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingDefinition;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingExceptionHandler;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingTranslationDefinition;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ResolvedCriteriaProductSuggestRoute;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilder;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzer;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreter;
use Shopware\Core\Content\Product\Stock\AvailableStockMirrorSubscriber;
use Shopware\Core\Content\Product\Stock\LoadProductStockSubscriber;
use Shopware\Core\Content\Product\Stock\OrderStockSubscriber;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Content\Product\Subscriber\CustomFieldSearchableSubscriber;
use Shopware\Core\Content\Product\Subscriber\ProductDescriptionTeaserSubscriber;
use Shopware\Core\Content\Product\Subscriber\ProductSubscriber;
use Shopware\Core\Content\Product\Subscriber\RepairDigitalProductStatesSubscriber;
use Shopware\Core\Content\Product\Util\VariantCombinationLoader;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\ProductProvider;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Framework\ContentSystem\Helper\ContentLayoutMetadataDeriver;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->instanceof(AbstractListingFilterHandler::class)
        ->tag('shopware.listing.filter.handler');

    $services->set(ProductExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(ProductSortingExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(ProductDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(ProductStreamMappingDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(SalesChannelProductDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(ProductCategoryDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductCustomFieldSetDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductTagDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductConfiguratorSettingDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductConfiguratorSettingExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(ProductPriceDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(ProductPropertyDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductSearchKeywordDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductKeywordDictionaryDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductReviewDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductManufacturerDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductManufacturerTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductMediaDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductDownloadDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductOptionDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductCategoryTreeDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductCrossSellingDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductCrossSellingTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductCrossSellingAssignedProductsDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductFeatureSetDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductFeatureSetTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductSortingDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductSortingTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductSearchConfigDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductSearchConfigFieldDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductSearchConfigFieldExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(ProductSearchConfigExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(ProductGateway::class)
        ->args([
            service('sales_channel.product.repository'),
            service('event_dispatcher'),
        ]);

    $services->set(AbstractPropertyGroupSorter::class, PropertyGroupSorter::class);

    $services->set(AbstractProductMaxPurchaseCalculator::class, ProductMaxPurchaseCalculator::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(AbstractIsNewDetector::class, IsNewDetector::class)
        ->args([
            service(SystemConfigService::class),
            service(ClockInterface::class),
        ]);

    $services->set(ProductVariationBuilder::class);

    $services->set(GaranLabelDurationFormatter::class);

    $services->set(GaranLabelRenderer::class)
        ->args([
            service('twig'),
        ]);

    $services->set(GaranLabelResolver::class)
        ->args([
            service(GaranLabelDurationFormatter::class),
            service(GaranLabelRenderer::class),
        ]);

    $services->set(GaranLabelTwigFilter::class)
        ->args([
            service(GaranLabelDurationFormatter::class),
            service('product.repository'),
            service(GaranLabelResolver::class),
        ])
        ->tag('twig.extension');

    $services->set(GaranLabelProductValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(CustomFieldSearchableSubscriber::class)
        ->args([
            service(Connection::class),
            service('parameter_bag'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RepairDigitalProductStatesSubscriber::class)
        ->args([
            service(Connection::class),
            service(StatesUpdater::class)->nullOnInvalid(),
            service(AbstractKeyValueStorage::class),
            service('logger'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductSubscriber::class)
        ->args([
            service(ProductVariationBuilder::class),
            service(ProductPriceCalculator::class),
            service(AbstractPropertyGroupSorter::class),
            service(AbstractProductMaxPurchaseCalculator::class),
            service(AbstractIsNewDetector::class),
            service(SystemConfigService::class),
            service(ProductMeasurementUnitBuilder::class),
            service(MeasurementUnitConverter::class),
            service('request_stack'),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductDescriptionTeaserBuilder::class)
        ->args([
            service(HtmlSanitizer::class),
        ]);

    $services->set(ProductDescriptionTeaserSubscriber::class)
        ->args([
            service(ProductDescriptionTeaserBuilder::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductDescriptionTeaserIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service(Connection::class),
            service(ProductDescriptionTeaserBuilder::class),
        ])
        ->tag('shopware.entity_indexer');

    $services->set(OrderStockSubscriber::class)
        ->args([
            service(Connection::class),
            service(StockStorage::class),
            param('shopware.stock.enable_stock_management'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AvailableStockMirrorSubscriber::class)
        ->tag('kernel.event_listener');

    $services->set(LoadProductStockSubscriber::class)
        ->args([
            service(StockStorage::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductSearchKeywordAnalyzer::class)
        ->args([
            service(Tokenizer::class),
            service(TokenFilter::class),
            service(SearchConfigLoader::class),
        ]);

    $services->set(ProductActionController::class)
        ->public()
        ->args([
            service(VariantCombinationLoader::class),
            service(ProductTypeRegistry::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ProductVisibilityDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(VariantCombinationLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(DeliveryTimeDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(ProductBoxCmsElementResolver::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('shopware.cms.data_resolver');

    $services->set(ProductListingCmsElementResolver::class)
        ->args([
            service(ProductListingRoute::class),
            service('product_sorting.repository'),
        ])
        ->tag('shopware.cms.data_resolver');

    $services->set(ProductSliderCmsElementResolver::class)
        ->args([
            tagged_iterator('shopware.cms.product_slider.processor'),
            service('logger'),
        ])
        ->tag('shopware.cms.data_resolver');

    $services->set(StaticProductProcessor::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ])
        ->tag('shopware.cms.product_slider.processor');

    $services->set(ProductStreamProcessor::class)
        ->args([
            service(ProductStreamBuilder::class),
            service('sales_channel.product.repository'),
            service('event_dispatcher'),
            service('logger'),
        ])
        ->tag('shopware.cms.product_slider.processor');

    $services->set(ProductNameCmsElementResolver::class)
        ->tag('shopware.cms.data_resolver');

    $services->set(ManufacturerLogoCmsElementResolver::class)
        ->tag('shopware.cms.data_resolver');

    $services->set(CrossSellingCmsElementResolver::class)
        ->args([
            service(ProductCrossSellingRoute::class),
        ])
        ->tag('shopware.cms.data_resolver');

    $services->set(ProductDescriptionReviewsCmsElementResolver::class)
        ->args([
            service(ProductReviewLoader::class),
            service(ScriptExecutor::class),
            service(SystemConfigService::class),
        ])
        ->tag('shopware.cms.data_resolver');

    $services->set(ProductPriceCalculator::class)
        ->args([
            service('unit.repository'),
            service(QuantityPriceCalculator::class),
            service(ExtensionDispatcher::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppScriptProductPriceCalculator::class)
        ->decorate(ProductPriceCalculator::class)
        ->args([
            service(AppScriptProductPriceCalculator::class . '.inner'),
            service(ScriptExecutor::class),
            service(ScriptPriceStubs::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CompositeListingProcessor::class)
        ->args([
            tagged_iterator('shopware.listing.processor'),
        ]);

    $services->set(CompressedCriteriaListingProcessor::class)
        ->args([
            service(CompressedCriteriaDecoder::class),
        ])
        // priority needs to be higher than of all other processors to make custom filters passed in compressed criteria to work
        ->tag('shopware.listing.processor', ['priority' => 1000]);

    $services->set(ManufacturerListingFilterHandler::class);

    $services->set(PriceListingFilterHandler::class);

    $services->set(RatingListingFilterHandler::class);

    $services->set(ShippingFreeListingFilterHandler::class);

    $services->set(PropertyListingFilterHandler::class)
        ->args([
            service('property_group.repository'),
            service('property_group_option.repository'),
            service(Connection::class),
        ]);

    $services->set(SortingListingProcessor::class)
        ->args([
            service(SystemConfigService::class),
            service('product_sorting.repository'),
        ])
        ->tag('shopware.listing.processor');

    $services->set(AggregationListingProcessor::class)
        ->args([
            tagged_iterator('shopware.listing.filter.handler'),
            service('event_dispatcher'),
        ])
        ->tag('shopware.listing.processor');

    $services->set(AssociationLoadingListingProcessor::class)
        ->tag('shopware.listing.processor');

    $services->set(BehaviorListingProcessor::class)
        ->tag('shopware.listing.processor', ['priority' => -1000]);

    $services->set(PagingListingProcessor::class)
        ->args([
            service(SystemConfigService::class),
            param('shopware.api.store.max_limit'),
        ])
        ->tag('shopware.listing.processor');

    $services->set(ProductSearchBuilderInterface::class, ProductSearchBuilder::class)
        ->args([
            service(ProductSearchTermInterpreter::class),
            service('logger'),
            param('shopware.search.term_max_length'),
            param('shopware.product.search_keyword.indexing'),
        ]);

    $services->set(ProductLineItemCommandValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ResolvedCriteriaProductSuggestRoute::class)
        ->decorate(ProductSuggestRoute::class, null, -2000)
        ->public()
        ->args([
            service(ProductSearchBuilderInterface::class),
            service('event_dispatcher'),
            service(ResolvedCriteriaProductSuggestRoute::class . '.inner'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(ProductSuggestRoute::class)
        ->public()
        ->args([
            service(ProductListingLoader::class),
        ]);

    $services->set(ProductSearchRoute::class)
        ->public()
        ->args([
            service(ProductSearchBuilderInterface::class),
            service(ProductListingLoader::class),
        ]);

    $services->set(ResolvedCriteriaProductSearchRoute::class)
        ->decorate(ProductSearchRoute::class, null, -2000)
        ->public()
        ->args([
            service(ResolvedCriteriaProductSearchRoute::class . '.inner'),
            service('event_dispatcher'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(ResolveCriteriaProductListingRoute::class)
        ->decorate(ProductListingRoute::class, null, -2000)
        ->public()
        ->args([
            service(ResolveCriteriaProductListingRoute::class . '.inner'),
            service('event_dispatcher'),
            service(CompositeListingProcessor::class),
        ]);

    $services->set(FindProductVariantRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service(CacheTagCollector::class),
            service(SystemConfigService::class),
            service(ProductCloseoutFilterFactory::class),
        ]);

    // decorated by cached route
    $services->set(ProductListingRoute::class)
        ->public()
        ->args([
            service(ProductListingLoader::class),
            service('category.repository'),
            service(ProductStreamBuilder::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ProductIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('product.repository'),
            service(Connection::class),
            service(VariantListingUpdater::class),
            service(ProductCategoryDenormalizer::class),
            service(InheritanceUpdater::class),
            service(RatingAverageUpdater::class),
            service(SearchKeywordUpdater::class),
            service(ChildCountUpdater::class),
            service(ManyToManyIdFieldUpdater::class),
            service(StockStorage::class),
            service('event_dispatcher'),
            service(CheapestPriceUpdater::class),
            service(ProductStreamUpdater::class),
            service('messenger.default_bus'),
            service(StatesUpdater::class)->nullOnInvalid(),
            service(ClockInterface::class),
        ])
        ->tag('shopware.entity_indexer', ['priority' => 100]);

    $services->set(ProductStreamUpdater::class)
        ->args([
            service(Connection::class),
            service(ProductDefinition::class),
            service('product.repository'),
            service('messenger.default_bus'),
            service(ManyToManyIdFieldUpdater::class),
            service('language.repository'),
            param('shopware.product_stream.indexing'),
        ])
        ->tag('shopware.entity_indexer');

    $services->set(ProductTypeRegistry::class)
        ->public()
        ->args([
            param('shopware.product.allowed_types'),
        ])
        ->tag('shopware.api.enum_provider');

    $services->set(StatesUpdater::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(VariantListingUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ProductCategoryDenormalizer::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CheapestPriceUpdater::class)
        ->args([
            service(Connection::class),
            service(CheapestPriceQuantitySelector::class),
            service('event_dispatcher'),
        ]);

    $services->set(CheapestPriceQuantitySelector::class);

    $services->set(RatingAverageUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(SearchKeywordUpdater::class)
        ->args([
            service(Connection::class),
            service('language.repository'),
            service('product.repository'),
            service(ProductSearchKeywordAnalyzer::class),
            service(ClockInterface::class),
            param('shopware.product.search_keyword.indexing'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(StockUpdateFilterProvider::class)
        ->args([
            tagged_iterator('shopware.product.stock_filter'),
        ]);

    $services->set(StockStorage::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(ProductListingLoader::class)
        ->args([
            service('sales_channel.product.repository'),
            service(SystemConfigService::class),
            service(Connection::class),
            service('event_dispatcher'),
            service(ProductCloseoutFilterFactory::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ProductDetailRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service('product_translation.repository'),
            service(SystemConfigService::class),
            service(Connection::class),
            service(ProductConfiguratorLoader::class),
            service(CategoryBreadcrumbBuilder::class),
            service(SalesChannelCmsPageLoader::class),
            service(EntityCmsSlotConfigInheritanceBuilder::class),
            service(SalesChannelProductDefinition::class),
            service(ProductCloseoutFilterFactory::class),
            service('event_dispatcher'),
            service(CacheTagCollector::class),
        ]);

    $services->set(ProductPurchaseLimitRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service(AbstractProductMaxPurchaseCalculator::class),
        ]);

    $services->set(ProductReviewLoader::class)
        ->args([
            service(ProductReviewRoute::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ]);

    $services->set(ProductReviewRoute::class)
        ->public()
        ->args([
            service('product_review.repository'),
            service(SystemConfigService::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(ProductConfiguratorLoader::class)
        ->args([
            service(AvailableCombinationLoader::class),
            service('property_group_option.repository'),
        ]);

    $services->set(AvailableCombinationLoader::class)
        ->args([
            service(Connection::class),
            service(StockStorage::class),
            service(SystemConfigService::class),
        ]);

    $services->set(ProductCrossSellingRoute::class)
        ->public()
        ->args([
            service('product_cross_selling.repository'),
            service('event_dispatcher'),
            service(ProductStreamBuilder::class),
            service('sales_channel.product.repository'),
            service(SystemConfigService::class),
            service(ProductListingLoader::class),
            service(ProductCloseoutFilterFactory::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(ProductReviewSaveRoute::class)
        ->public()
        ->args([
            service('product_review.repository'),
            service(DataValidator::class),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(ProductProvider::class),
        ]);

    $services->set(ProductListRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
        ]);

    $services->set(GaranLabelRoute::class)
        ->public()
        ->args([
            service('sales_channel.product.repository'),
            service(GaranLabelResolver::class),
        ]);

    $services->alias(AbstractGaranLabelRoute::class, GaranLabelRoute::class);

    $services->set(BuyBoxCmsElementResolver::class)
        ->args([
            service(ProductConfiguratorLoader::class),
            service('product_review.repository'),
        ])
        ->tag('shopware.cms.data_resolver');

    $services->set(TokenFilter::class)
        ->args([
            service(SearchConfigLoader::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CheapestPriceAccessorBuilder::class)
        ->args([
            param('shopware.dal.max_rule_prices'),
            service('logger'),
        ])
        ->tag('shopware.field_accessor_builder', ['priority' => -200]);

    $services->set(CleanupProductKeywordDictionaryTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(CleanupProductKeywordDictionaryTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CleanupUnusedDownloadMediaTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(CleanupUnusedDownloadMediaTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(UnusedMediaPurger::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(ProductCloseoutFilterFactory::class);

    $services->set(ProductNumberFkResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('shopware.sync.fk_resolver');

    // Content System
    $services->set(ProductContentLayoutDefinition::class)
        ->args([
            service(ContentLayoutMetadataDeriver::class),
        ])
        ->tag('shopware.entity.definition');

    $services->set(ProductDetailDataLoader::class)
        ->args([
            service(ProductDetailRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(ProductDetailLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(ProductListingDataLoader::class)
        ->args([
            service(ProductListingRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(ProductListingLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(CrossSellingDataLoader::class)
        ->args([
            service(ProductCrossSellingRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(CrossSellingLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(ProductReviewDataLoader::class)
        ->args([
            service(ProductReviewRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(ProductReviewLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(ProductSearchDataLoader::class)
        ->args([
            service(ProductSearchRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(ProductSearchLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(ProductSuggestDataLoader::class)
        ->args([
            service(ProductSuggestRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(ProductSuggestLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');

    $services->set(ProductSpecificationSource::class)
        ->args([
            service('product_content_layout.repository'),
            service(ProductContentLayoutDefinition::class),
            service(EntityLayoutContextFactory::class),
        ])
        ->tag('content_system.entity_specification_source', ['priority' => 100]);
};
