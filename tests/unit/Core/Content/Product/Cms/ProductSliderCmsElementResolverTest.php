<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategoryTree\ProductCategoryTreeDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCustomFieldSet\ProductCustomFieldSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSet\ProductFeatureSetDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductOption\ProductOptionDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductProperty\ProductPropertyDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductStreamMapping\ProductStreamMappingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTag\ProductTagDefinition;
use Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingConfig;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\ProductStream\ProductStreamDefinition;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Shopware\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\Tag\TagDefinition;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\System\Unit\UnitDefinition;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductSliderCmsElementResolver::class)]
class ProductSliderCmsElementResolverTest extends TestCase
{
    private ProductSliderCmsElementResolver $sliderResolver;

    private string $productStreamId;

    private MockObject&SystemConfigService $systemConfig;

    protected function setUp(): void
    {
        $this->systemConfig = $this->createMock(SystemConfigService::class);

        $this->sliderResolver = new ProductSliderCmsElementResolver($this->createMock(ProductStreamBuilder::class), $this->systemConfig);

        $this->productStreamId = Uuid::randomHex();
    }

    public function testGetType(): void
    {
        static::assertSame('product-slider', $this->sliderResolver->getType());
    }

    public function testCollectWithEmptyConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig(new FieldConfigCollection());

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithEmptyStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, []));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithStaticConfig(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, ['a', 'b', 'c']));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        static::assertNotNull($collection);
        static::assertCount(1, $collection->all());
        static::assertSame(['a', 'b', 'c'], $collection->all()[ProductDefinition::class]['product-slider_id']->getIds());
    }

    public function testCollectWithMappedConfigButWithoutEntityResolverContext(): void
    {
        $resolverContext = new ResolverContext($this->createMock(SalesChannelContext::class), new Request());

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_MAPPED, 'category.products'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithMappedConfigButWithInvalidProperty(): void
    {
        $category = new CategoryEntity();
        $category->setUniqueIdentifier('category1');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(CategoryDefinition::class), $category);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_MAPPED, 'category.foo'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Property foo do not exist in class ' . CategoryEntity::class);

        $this->sliderResolver->collect($slot, $resolverContext);
    }

    public function testCollectWithMappedConfig(): void
    {
        $product1 = new SalesChannelProductEntity();
        $product1->setUniqueIdentifier('product1');

        $product2 = new SalesChannelProductEntity();
        $product2->setUniqueIdentifier('product2');

        $products = new ProductCollection([$product1, $product2]);

        $category = new CategoryEntity();
        $category->setUniqueIdentifier('category1');
        $category->setProducts($products);

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->createMock(CategoryDefinition::class), $category);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_MAPPED, 'category.products'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        static::assertNull($collection);
    }

    public function testCollectWithMappedConfigProductStream(): void
    {
        $salesChannelContextFactory = $this->createMock(SalesChannelContextFactory::class);

        $salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $resolverContext = new ResolverContext(
            $salesChannelContext,
            new Request()
        );

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, $this->productStreamId));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        static::assertCount(1, $collection->all());
        static::assertEquals(ProductDefinition::class, key($collection->all()));

        /** @phpstan-ignore-next-line - will fail because return type of getIterator will change */
        static::assertEquals('product-slider-entity-fallback_id', key($collection->getIterator()->current()));

        $expectedCriteria = new Criteria();
        $expectedCriteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $expectedCriteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsAnyFilter('product.id', [Uuid::randomHex()]),
                new RangeFilter('product.width', [
                    'gte' => 120,
                    'lte' => 180,
                ]),
            ]
        ));
        $expectedCriteria->setLimit(50);

        /** @var Criteria $criteria */
        /** @phpstan-ignore-next-line - will fail because return type of getIterator will change */
        foreach ($collection->getIterator()->current() as $criteria) {
            static::assertEquals($expectedCriteria->getSorting(), $criteria->getSorting());
            static::assertEquals($expectedCriteria->getLimit(), $criteria->getLimit());
            /** @var MultiFilter $expectedMultiFilter */
            $expectedMultiFilter = $expectedCriteria->getFilters()[0];
            /** @var MultiFilter $multiFilter */
            $multiFilter = $expectedCriteria->getFilters()[0];
            static::assertEquals($expectedMultiFilter->getQueries()[0], $multiFilter->getQueries()[0]);
            /** @var RangeFilter $expectedRangeFilter */
            $expectedRangeFilter = $expectedMultiFilter->getQueries()[1];
            /** @var RangeFilter $rangeFilter */
            $rangeFilter = $expectedMultiFilter->getQueries()[1];
            static::assertEquals($rangeFilter->getField(), $rangeFilter->getField());
            static::assertEquals($expectedRangeFilter->getParameters(), $rangeFilter->getParameters());
        }
    }

    public function testCollectWithMappedConfigButEmptyManyToManyRelation(): void
    {
        $category = new CategoryEntity();
        $category->setUniqueIdentifier('category1');

        $container = new Container();
        $categoryDefinition = new CategoryDefinition();
        $productDefinition = new ProductDefinition();
        $categoryProductDefinition = new ProductCategoryDefinition();

        $container->set(CategoryDefinition::class, $categoryDefinition);
        $container->set(ProductDefinition::class, $productDefinition);
        $container->set(ProductCategoryDefinition::class, $categoryProductDefinition);

        $container->set(ProductOptionDefinition::class, $this->createMock(ProductOptionDefinition::class));
        $container->set(PropertyGroupOptionDefinition::class, $this->createMock(PropertyGroupOptionDefinition::class));
        $container->set(ProductPropertyDefinition::class, $this->createMock(ProductPropertyDefinition::class));
        $container->set(ProductStreamMappingDefinition::class, $this->createMock(ProductStreamMappingDefinition::class));
        $container->set(ProductStreamDefinition::class, $this->createMock(ProductStreamDefinition::class));
        $container->set(ProductCategoryTreeDefinition::class, $this->createMock(ProductCategoryTreeDefinition::class));
        $container->set(ProductTagDefinition::class, $this->createMock(ProductTagDefinition::class));
        $container->set(TagDefinition::class, $this->createMock(TagDefinition::class));
        $container->set(ProductCustomFieldSetDefinition::class, $this->createMock(ProductCustomFieldSetDefinition::class));
        $container->set(CustomFieldSetDefinition::class, $this->createMock(CustomFieldSetDefinition::class));

        $productDefinition->compile(new DefinitionInstanceRegistry($container, [], []));
        $categoryDefinition->compile(new DefinitionInstanceRegistry($container, [], []));
        $categoryProductDefinition->compile(new DefinitionInstanceRegistry($container, [], []));

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $categoryDefinition, $category);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_MAPPED, 'category.products'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categories.id', $category->getUniqueIdentifier()));
        $criteria->addAssociation('cover');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('manufacturer');

        static::assertNotNull($collection);
        static::assertEquals($criteria, $collection->all()[ProductDefinition::class]['product-slider-entity-fallback_id']);
    }

    public function testCollectWithMappedConfigButEmptyOneToManyRelation(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setUniqueIdentifier('product1');

        $productDefinition = new ProductDefinition();

        $container = new Container();
        $container->set(ProductDefinition::class, $productDefinition);
        $container->set(DeliveryTimeDefinition::class, $this->createMock(DeliveryTimeDefinition::class));
        $container->set(TaxDefinition::class, $this->createMock(TaxDefinition::class));
        $container->set(ProductManufacturerDefinition::class, $this->createMock(ProductManufacturerDefinition::class));
        $container->set(UnitDefinition::class, $this->createMock(UnitDefinition::class));
        $container->set(ProductMediaDefinition::class, $this->createMock(ProductMediaDefinition::class));
        $container->set(ProductFeatureSetDefinition::class, $this->createMock(ProductFeatureSetDefinition::class));
        $container->set(CmsPageDefinition::class, $this->createMock(CmsPageDefinition::class));

        $productDefinition->compile(new DefinitionInstanceRegistry($container, [], []));
        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $productDefinition, $product);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_MAPPED, 'product.children'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-slider');
        $slot->setFieldConfig($fieldConfig);

        $collection = $this->sliderResolver->collect($slot, $resolverContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parent.id', $product->getUniqueIdentifier()));
        $criteria->addAssociation('cover');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('manufacturer');

        static::assertNotNull($collection);
        static::assertEquals($criteria, $collection->all()[ProductDefinition::class]['product-slider-entity-fallback_id']);
    }

    #[DataProvider('enrichDataProvider')]
    public function testEnrich(bool $closeout, bool $hidden, int $availableStock): void
    {
        if ($hidden) {
            $this->systemConfig->method('get')->willReturn(true);
        }

        $salesChannelId = 'f3489c46df62422abdea4aa1bb03511c';

        $product = new SalesChannelProductEntity();
        $product->setId('product123');
        $product->setAvailableStock($availableStock);
        $product->setIsCloseout($closeout);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn($salesChannelId);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);

        $productSliderResolver = new ProductSliderCmsElementResolver($this->createMock(ProductStreamBuilder::class), $this->systemConfig);
        $resolverContext = new ResolverContext($salesChannelContext, new Request());
        $result = new ElementDataCollection();
        $result->add('product-slider_product_id', new EntitySearchResult(
            'product',
            1,
            new ProductCollection([$product]),
            null,
            new Criteria(),
            $resolverContext->getSalesChannelContext()->getContext()
        ));

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_STATIC, 'product'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('product_id');
        $slot->setType('');
        $slot->setFieldConfig($fieldConfig);

        $productSliderResolver->enrich($slot, $resolverContext, $result);

        /** @var ProductSliderStruct|null $productSliderStruct */
        $productSliderStruct = $slot->getData();

        static::assertInstanceOf(ProductSliderStruct::class, $productSliderStruct);

        $products = $productSliderStruct->getProducts();
        static::assertNotNull($products);

        /*
         * conditional assertions depending on if an product should be returned or not
         */
        if ($closeout && $hidden && $availableStock === 0) {
            static::assertNull($products->first());
        } else {
            $productEntity = $products->first();
            static::assertInstanceOf(ProductEntity::class, $productEntity);

            $productId = $productEntity->getId();
            static::assertSame($productId, $product->getId());
            static::assertSame($product, $products->first());
        }
    }

    /**
     * @return list<array{closeout: bool, hidden: bool, availableStock: int}>
     */
    public static function enrichDataProvider(): array
    {
        return [
            ['closeout' => false, 'hidden' => false, 'availableStock' => 1],
            ['closeout' => false, 'hidden' => true, 'availableStock' => 1],
            ['closeout' => true, 'hidden' => false, 'availableStock' => 1],
            ['closeout' => true, 'hidden' => true, 'availableStock' => 1],
            ['closeout' => true, 'hidden' => true, 'availableStock' => 0],
        ];
    }

    /**
     * @param string[] $expectedProductIds
     * @param SalesChannelProductEntity[] $streamProducts
     */
    #[DataProvider('streamProductDataProvider')]
    public function testEnrichVariants(array $expectedProductIds, array $streamProducts): void
    {
        $productStreamBuilder = $this->createMock(ProductStreamBuilderInterface::class);
        $resolver = new ProductSliderCmsElementResolver($productStreamBuilder, $this->systemConfig);

        $salesChannelContext = Generator::createSalesChannelContext();
        $resolverContext = new EntityResolverContext($salesChannelContext, new Request(), new ProductDefinition(), new SalesChannelProductEntity());

        $streamResult = new ProductCollection($streamProducts);

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('getEntities')->willReturn($streamResult);

        $fieldConfig = new FieldConfigCollection();
        $fieldConfig->add(new FieldConfig('products', FieldConfig::SOURCE_PRODUCT_STREAM, 'streamId'));

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('product_id');
        $slot->setType('');
        $slot->setFieldConfig($fieldConfig);

        $elementDataCollection = new ElementDataCollection();
        $elementDataCollection->add('product-slider-entity-fallback_' . $slot->getUniqueIdentifier(), $entitySearchResult);

        $resolver->enrich($slot, $resolverContext, $elementDataCollection);

        /** @var ProductSliderStruct|null $productSlider */
        $productSlider = $slot->getData();
        $products = $productSlider?->getProducts();

        if ($products) {
            static::assertCount(\count($expectedProductIds), $products);
            foreach ($expectedProductIds as $expectedProductId) {
                static::assertTrue($products->has($expectedProductId), "Expected product ID $expectedProductId to be included in the slider.");
            }
        } else {
            static::fail('ProductSlider or its products are null.');
        }
    }

    public static function streamProductDataProvider(): \Generator
    {
        $parentId = Uuid::randomHex();
        $mainVariantId = Uuid::randomHex();
        $otherVariantId = Uuid::randomHex();
        $nonExistentId = Uuid::randomHex();

        yield 'Display main product' => [
            'expectedProductIds' => [$parentId],
            'streamProducts' => [
                self::createProduct($parentId, null),
                self::createProduct($mainVariantId, $parentId, new VariantListingConfig(true, null, [])),
                self::createProduct($otherVariantId, $parentId, new VariantListingConfig(true, null, [])),
            ],
        ];

        yield 'Display main variant' => [
            'expectedProductIds' => [$mainVariantId],
            'streamProducts' => [
                self::createProduct($parentId, null, new VariantListingConfig(false, $mainVariantId, [])),
                self::createProduct($mainVariantId, $parentId, new VariantListingConfig(false, $mainVariantId, [])),
                self::createProduct($otherVariantId, $parentId, new VariantListingConfig(false, $mainVariantId, [])),
            ],
        ];

        yield 'Null idToFetch' => [
            'expectedProductIds' => [],
            'streamProducts' => [
                self::createProduct($parentId, null, new VariantListingConfig(false, null, [])),
            ],
        ];

        yield 'Non-existent productToAdd' => [
            'expectedProductIds' => [],
            'streamProducts' => [
                self::createProduct($parentId, null, new VariantListingConfig(false, $nonExistentId, [])),
            ],
        ];
    }

    private static function createProduct(string $id, ?string $parentId, ?VariantListingConfig $config = null): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($id);
        $product->setUniqueIdentifier($id);
        $product->setParentId($parentId);
        $product->setVariantListingConfig($config);

        return $product;
    }
}
