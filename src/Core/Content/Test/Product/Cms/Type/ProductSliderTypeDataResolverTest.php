<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms\Type;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\EntityResolverContext;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cms\ProductSliderCmsElementResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\ProductStream\ProductStreamEntity;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

class ProductSliderTypeDataResolverTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var ProductSliderCmsElementResolver
     */
    private $sliderResolver;

    /**
     * @var string
     */
    private $productStreamId;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $productIdWidth100;

    /**
     * @var string
     */
    private $productIdWidth150;

    /**
     * @var array
     */
    private $randomProductIds;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|SystemConfigService
     */
    private $systemConfig;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $this->systemConfig = $this->createMock(SystemConfigService::class);

        $this->sliderResolver = new ProductSliderCmsElementResolver($this->getContainer()->get(ProductStreamBuilder::class), $this->systemConfig);

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

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->getContainer()->get(CategoryDefinition::class), $category);

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

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->getContainer()->get(CategoryDefinition::class), $category);

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
        $this->createTestProductStreamEntity();

        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

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

        static::assertCount(1, $collection->all());
        static::assertEquals('Shopware\Core\Content\Product\ProductDefinition', key($collection->all()));
        static::assertEquals('product-slider-entity-fallback_id', key($collection->getIterator()->current()));

        $expectedCriteria = new Criteria();
        $expectedCriteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        $expectedCriteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsAnyFilter('product.id', $this->randomProductIds),
                new RangeFilter('product.width', [
                    'gte' => 120,
                    'lte' => 180,
                ]),
            ]
        ));
        $expectedCriteria->setLimit(50);

        /** @var Criteria $criteria */
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

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->getContainer()->get(CategoryDefinition::class), $category);

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

        static::assertNotNull($collection);
        static::assertEquals($criteria, $collection->all()[ProductDefinition::class]['product-slider-entity-fallback_id']);
    }

    public function testCollectWithMappedConfigButEmptyOneToManyRelation(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setUniqueIdentifier('product1');

        $resolverContext = new EntityResolverContext($this->createMock(SalesChannelContext::class), new Request(), $this->getContainer()->get(ProductDefinition::class), $product);

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

        static::assertNotNull($collection);
        static::assertEquals($criteria, $collection->all()[ProductDefinition::class]['product-slider-entity-fallback_id']);
    }

    /**
     * @dataProvider enrichDataProvider
     */
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

        $salesChannel = $this->createMock(SalesChannelEntity::class);
        $salesChannel->method('getId')->willReturn($salesChannelId);

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

        /** @var ProductSliderStruct|null $ProductSliderStruct */
        $ProductSliderStruct = $slot->getData();

        static::assertInstanceOf(ProductSliderStruct::class, $ProductSliderStruct);

        /*
         * conditional assertions depending on if an product should be returned or not
         */
        if ($closeout && $hidden && $availableStock === 0) {
            static::assertNull($ProductSliderStruct->getProducts()->first());
        } else {
            $productId = $ProductSliderStruct->getProducts()->first()->getId();
            static::assertSame($productId, $product->getId());
            static::assertSame($product, $ProductSliderStruct->getProducts()->first());
        }
    }

    /**
     * @return array[] closeout, hidden, availableStock
     *                 This sets if an product can be backordered, if it should be hidden if it can not an is no longer available and the available products
     */
    public function enrichDataProvider(): array
    {
        return [
            [false, false, 1],
            [false, true, 1],
            [true, false, 1],
            [true, true, 1],
            [true, true, 0],
        ];
    }

    private function createTestProductStreamEntity(): ProductStreamEntity
    {
        $this->randomProductIds = array_column($this->createProducts(), 'id');
        $randomProductIdsString = implode('|', $this->randomProductIds);

        $stream = [
            'id' => $this->productStreamId,
            'name' => 'testStream',
            'filters' => [
                [
                    'type' => 'multi',
                    'queries' => [
                        [
                            'type' => 'equalsAny',
                            'field' => 'product.id',
                            'value' => $randomProductIdsString,
                        ],
                        [
                            'type' => 'range',
                            'field' => 'product.width',
                            'parameters' => [
                                'gte' => 120,
                                'lte' => 180,
                            ],
                        ],
                    ],
                    'operator' => 'AND',
                ],
            ],
        ];
        $productRepository = $this->getContainer()->get('product_stream.repository');
        $productRepository->create([$stream], $this->context);

        return $productRepository->search(new Criteria([$this->productStreamId]), $this->context)->first();
    }

    private function createProducts(): array
    {
        $productRepository = $this->getContainer()->get('product.repository');
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $salesChannelId = TestDefaults::SALES_CHANNEL;
        $products = [];

        $widths = [
            '100',
            '110',
            '120',
            '130',
            '140',
            '150',
            '160',
            '170',
            '180',
            '190',
        ];

        for ($i = 0; $i < 10; ++$i) {
            $products[] = [
                'id' => Uuid::randomHex(),
                'productNumber' => Uuid::randomHex(),
                'width' => $widths[$i],
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'taxRate' => 17, 'name' => 'with id'],
                'visibilities' => [
                    ['salesChannelId' => $salesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        $this->productIdWidth100 = $products[0]['id'];
        $this->productIdWidth150 = $products[5]['id'];

        $productRepository->create($products, $this->context);

        return $products;
    }
}
