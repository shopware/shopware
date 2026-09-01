<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms\ProductSlider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\Cms\ProductSlider\ProductStreamProcessor;
use Shopware\Core\Content\Product\Events\ProductSliderStreamCriteriaEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductStreamProcessor::class)]
class ProductStreamProcessorTest extends TestCase
{
    use ProductSliderUnitTrait;

    protected FieldConfigCollection $config;

    private ProductStreamBuilder&MockObject $productStreamBuilder;

    /**
     * @var SalesChannelRepository<ProductCollection>&MockObject
     */
    private SalesChannelRepository&MockObject $productRepository;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private LoggerInterface&MockObject $logger;

    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->configureProductStreamBuilder();

        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->config = new FieldConfigCollection();
    }

    public function testGetDecorated(): void
    {
        $this->productRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->expectException(DecorationPatternException::class);
        $this->getProcessor()->getDecorated();
    }

    public function testGetSource(): void
    {
        $this->productRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        static::assertSame('product_stream', $this->getProcessor()->getSource());
    }

    public function testCollect(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');

        $this->config->add($config);

        $this->configureProductStreamBuilder(enrichCriteriaCalls: $this->once());
        $this->productRepository->expects($this->never())->method('search');
        $this->logger->expects($this->never())->method('warning');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(ProductSliderStreamCriteriaEvent::class));

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        static::assertCount(1, $list);

        $criteria = $list[ProductDefinition::class]['product-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $filters = $criteria->getFilters();
        static::assertCount(2, $filters);

        $filter = array_shift($filters);
        static::assertEquals($this->getFilter(), $filter);

        $filter = array_shift($filters);
        $groupingFilter = new NotEqualsFilter('displayGroup', null);

        static::assertEquals($groupingFilter, $filter);
    }

    public function testCollectSkipsGroupingWhenStreamDisplaysVariantsDirectly(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $this->configureProductStreamBuilder(false, $this->once());

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');

        $this->config->add($config);

        $this->productRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        $criteria = $list[ProductDefinition::class]['product-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertCount(1, $criteria->getFilters());
        static::assertCount(0, $criteria->getGroupFields());
        static::assertTrue($criteria->hasState(ProductListingLoader::STATE_SKIP_ADD_GROUPING));
    }

    public function testCollectAddsCloseoutFilterToCriteriaWhenHideCloseoutEnabled(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $this->config->add($config);

        $this->configureProductStreamBuilder(enrichCriteriaCalls: $this->once());
        $this->productRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->enableHideCloseout($resolverContext->getSalesChannelContext()->getSalesChannelId());

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $criteria = $collection->all()[ProductDefinition::class]['product-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $closeoutFilters = array_filter(
            $criteria->getFilters(),
            static fn ($filter) => $filter instanceof ProductCloseoutFilter
        );
        static::assertCount(
            1,
            $closeoutFilters,
            'Closeout filter must be part of the stream criteria so hidden products do not consume the slider limit'
        );
    }

    public function testCollectDoesNotAddCloseoutFilterToCriteriaWhenHideCloseoutDisabled(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $this->config->add($config);

        $this->configureProductStreamBuilder(enrichCriteriaCalls: $this->once());
        $this->productRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        // $this->systemConfigService is left at its empty default: hide closeout off.

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $criteria = $collection->all()[ProductDefinition::class]['product-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $closeoutFilters = array_filter(
            $criteria->getFilters(),
            static fn ($filter) => $filter instanceof ProductCloseoutFilter
        );
        static::assertCount(0, $closeoutFilters);
    }

    public function testCollectEventCanModifyCriteria(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $this->config->add($config);

        $this->configureProductStreamBuilder(enrichCriteriaCalls: $this->once());
        $this->productRepository->expects($this->never())->method('search');
        $this->logger->expects($this->never())->method('warning');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (ProductSliderStreamCriteriaEvent $event): ProductSliderStreamCriteriaEvent {
                $event->criteria->addAssociation('manufacturer');

                return $event;
            });

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        $criteria = $list[ProductDefinition::class]['product-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertTrue($criteria->hasAssociation('manufacturer'));
    }

    public function testCollectReturnsNullWhenProductStreamNoLongerExists(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'deleted-product-stream-id');
        $this->config->add($config);

        $exception = new EntityNotFoundException('product_stream', 'deleted-product-stream-id');

        $this->productRepository->expects($this->never())->method('search');

        $this->productStreamBuilder = $this->createMock(ProductStreamBuilder::class);
        $this->productStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->with(static::isInstanceOf(Criteria::class), 'deleted-product-stream-id', $resolverContext->getSalesChannelContext()->getContext())
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Product stream configured for CMS product slider could not be found.',
                [
                    'productStreamId' => 'deleted-product-stream-id',
                    'exception' => $exception,
                ]
            );

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        static::assertNull($this->getProcessor()->collect($slot, $this->config, $resolverContext));
    }

    public function testCollectDoesNotSwallowDeprecationFromBuildFiltersFallback(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $this->config->add($config);

        // An interface-only builder makes the slider fall back to the deprecated buildFilters(), which
        // throws under the v6.8.0.0 flag. That FeatureException must propagate, not be swallowed by the
        // EntityNotFoundException catch around the stream enrichment.
        $exception = FeatureException::error('buildFilters() is removed in v6.8.0.0');

        $this->productRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $productStreamBuilder = $this->createMock(ProductStreamBuilderInterface::class);
        $productStreamBuilder->expects($this->once())
            ->method('buildFilters')
            ->willThrowException($exception);

        $processor = new ProductStreamProcessor(
            $productStreamBuilder,
            $this->productRepository,
            $this->eventDispatcher,
            $this->logger,
            $this->systemConfigService,
            new ProductCloseoutFilterFactory(),
        );

        $this->expectExceptionObject($exception);

        $processor->collect($slot, $this->config, $resolverContext);
    }

    public function testCollectAddsRandomSortingIfRequired(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $productsConfig = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $sortingConfig = new FieldConfig('productStreamSorting', FieldConfig::SOURCE_PRODUCT_STREAM, 'random');

        $this->config->add($productsConfig);
        $this->config->add($sortingConfig);

        $this->configureProductStreamBuilder(enrichCriteriaCalls: $this->once());
        $this->productRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        static::assertCount(1, $list);

        $criteria = $list[ProductDefinition::class]['product-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $sorting = $criteria->getSorting();
        static::assertCount(2, $sorting);
        static::assertContainsOnlyInstancesOf(FieldSorting::class, $sorting);
    }

    public function testEnrich(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $this->config->add($config);

        $products = $this->getProducts();
        $searchResult = $this->getEntitySearchResult($products);

        $data = new ElementDataCollection();
        $data->add('product-slider-entity-fallback_id', $searchResult);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->productRepository->expects($this->once())
            ->method('search')->willReturn($searchResult);

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $slider);
        static::assertSame('product-stream-1', $slider->getStreamId());
        static::assertSame($products, $slider->getProducts());
    }

    public function testEnrichFiltersOutOfStockCloseoutProductsWhenHideCloseoutEnabled(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $this->config->add($config);

        $products = $this->getProducts();
        $searchResult = $this->getEntitySearchResult($products);

        $data = new ElementDataCollection();
        $data->add('product-slider-entity-fallback_id', $searchResult);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->productRepository->expects($this->once())
            ->method('search')->willReturn($searchResult);

        $this->enableHideCloseout($resolverContext->getSalesChannelContext()->getSalesChannelId());

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $slider);

        $filteredProducts = $slider->getProducts();
        static::assertInstanceOf(ProductCollection::class, $filteredProducts);
        static::assertCount(1, $filteredProducts);
        static::assertTrue($filteredProducts->has('product-1'));
        static::assertFalse($filteredProducts->has('product-2'));
    }

    public function testEnrichKeepsAllProductsWhenHideCloseoutDisabled(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $this->config->add($config);

        $products = $this->getProducts();
        $searchResult = $this->getEntitySearchResult($products);

        $data = new ElementDataCollection();
        $data->add('product-slider-entity-fallback_id', $searchResult);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->productRepository->expects($this->once())
            ->method('search')->willReturn($searchResult);

        // $this->systemConfigService is left at its empty default: hide closeout off.

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $slider);
        static::assertCount(2, $slider->getProducts() ?? new ProductCollection());
    }

    public function testEnrichDoesNothingWithoutEntitySearchResult(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();
        $data = new ElementDataCollection();

        $this->productRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->getProcessor()->enrich($slot, $data, $resolverContext);
        static::assertNull($slot->getData());
    }

    public function testEnrichDoesNothingWithoutProducts(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();
        $data = new ElementDataCollection();

        $result = new EntitySearchResult(
            'tax',
            2,
            new TaxCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $this->productRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $data->add('product-slider-entity-fallback_id', $result);
        $this->getProcessor()->enrich($slot, $data, $resolverContext);
        static::assertNull($slot->getData());
    }

    public function testEnrichUsesEmptyProductCollectionIfNoProductIdsDetermined(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();
        $data = new ElementDataCollection();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $this->config->add($config);

        $result = new EntitySearchResult(
            'product',
            0,
            new ProductCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $data->add('product-slider-entity-fallback_id', $result);

        $this->productRepository->expects($this->never())
            ->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $slider);
        static::assertEmpty($slider->getProducts());
    }

    public function testEnrichKeepsUngroupedVariantsWhenStreamDisplaysVariantsDirectly(): void
    {
        $slot = $this->getSlot();
        $resolverContext = $this->getResolverContext();
        $data = new ElementDataCollection();

        $config = new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'product-stream-1');
        $this->config->add($config);

        $criteria = new Criteria();
        $criteria->addState(ProductListingLoader::STATE_SKIP_ADD_GROUPING);

        $products = $this->getProducts();
        $result = new EntitySearchResult(
            'product',
            $products->count(),
            $products,
            null,
            $criteria,
            Context::createDefaultContext()
        );

        $data->add('product-slider-entity-fallback_id', $result);

        $this->productRepository->expects($this->never())
            ->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(ProductSliderStruct::class, $slider);
        static::assertSame($products, $slider->getProducts());
    }

    /**
     * Scoped to the sales channel so a processor that looked the setting up
     * globally, or under a different key, would still read `false` here.
     */
    private function enableHideCloseout(string $salesChannelId): void
    {
        $this->systemConfigService = new StaticSystemConfigService([
            $salesChannelId => ['core.listing.hideCloseoutProductsWhenOutOfStock' => true],
        ]);
    }

    private function getProcessor(): ProductStreamProcessor
    {
        return new ProductStreamProcessor(
            $this->productStreamBuilder,
            $this->productRepository,
            $this->eventDispatcher,
            $this->logger,
            $this->systemConfigService,
            new ProductCloseoutFilterFactory(),
        );
    }

    private function configureProductStreamBuilder(bool $displayAsGroup = true, ?InvocationOrder $enrichCriteriaCalls = null): void
    {
        $this->productStreamBuilder = $this->createMock(ProductStreamBuilder::class);

        $filter = $this->getFilter();

        $this->productStreamBuilder->expects($enrichCriteriaCalls ?? $this->never())
            ->method('enrichCriteria')
            ->willReturnCallback(static function (Criteria $criteria, mixed ...$_) use ($displayAsGroup, $filter): void {
                $criteria->addFilter($filter);

                if (!$displayAsGroup) {
                    $criteria->addState(ProductListingLoader::STATE_SKIP_ADD_GROUPING);
                }
            });
    }

    private function getFilter(): MultiFilter
    {
        return new MultiFilter(MultiFilter::CONNECTION_OR, [
            new ContainsFilter('product.name', 'Awesome'),
            new EqualsFilter('product.id', 'product-1'),
        ]);
    }
}
