<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cms;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\FieldConfig;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\Cms\ProductListingCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductListingCmsElementResolver::class)]
class ProductListingCmsElementResolverTest extends TestCase
{
    public function testGetType(): void
    {
        $route = $this->createMock(AbstractProductListingRoute::class);
        $repository = new StaticEntityRepository([]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        static::assertSame('product-listing', $resolver->getType());
    }

    public function testGetCollectReturnsNull(): void
    {
        $route = $this->createMock(AbstractProductListingRoute::class);
        $repository = new StaticEntityRepository([]);

        $slot = new CmsSlotEntity();
        $context = new ResolverContext(Generator::generateSalesChannelContext(), new Request());

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        static::assertNull($resolver->collect($slot, $context));
    }

    public function testEnrichHandlesDefaultSorting(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('filters', FieldConfig::SOURCE_STATIC, ['filter' => true]),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);
        $slot->setTranslated([
            'config' => [
                'useCustomSorting' => [
                    'value' => true,
                ],
                'defaultSorting' => [
                    'value' => 'sorting-id-1',
                ],
            ],
        ]);
        $request = new Request();
        $context = new ResolverContext(Generator::generateSalesChannelContext(), $request);
        $data = new ElementDataCollection();

        $expectedResult = $this->createMock(ProductListingResult::class);
        $response = new ProductListingRouteResponse($expectedResult);

        $route = $this->createMock(AbstractProductListingRoute::class);
        $route->expects($this->once())->method('load')->willReturn($response);

        $sorting = new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                'id' => 'sorting-id-1',
                'key' => 'expected-sorting',
            ]),
        ]);

        $repository = new StaticEntityRepository([$sorting]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);
        static::assertInstanceOf(ProductListingResult::class, $data->getListing());

        $this->assertRequestPayload($request);
    }

    public function testEnrichHandlesAvailableSorting(): void
    {
        $config = new FieldConfigCollection([
            new FieldConfig('filters', FieldConfig::SOURCE_STATIC, ['filter' => true]),
        ]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);
        $slot->setTranslated([
            'config' => [
                'useCustomSorting' => [
                    'value' => true,
                ],
            ],
        ]);
        $request = new Request();
        $request->attributes->set('restrictedProductSortingCollection', new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                'id' => 'sorting-id-1',
                'key' => 'expected-sorting',
            ]),
        ]));
        $context = new ResolverContext(Generator::generateSalesChannelContext(), $request);
        $data = new ElementDataCollection();

        $expectedResult = $this->createMock(ProductListingResult::class);
        $response = new ProductListingRouteResponse($expectedResult);

        $route = $this->createMock(AbstractProductListingRoute::class);
        $route->expects($this->once())->method('load')->willReturn($response);

        $sorting = new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                'id' => 'sorting-id-1',
                'key' => 'expected-sorting',
            ]),
        ]);

        $repository = new StaticEntityRepository([$sorting]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);
        static::assertInstanceOf(ProductListingResult::class, $data->getListing());

        $this->assertRequestPayload($request);
    }

    public function testAddDefaultSortingSkipsWhenOrderIsPresent(): void
    {
        $config = new FieldConfigCollection([]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);
        $slot->setTranslated([
            'config' => [
                'useCustomSorting' => [
                    'value' => true,
                ],
                'defaultSorting' => [
                    'value' => 'sorting-id-1',
                ],
            ],
        ]);

        // Set order parameter to trigger the early return in addDefaultSorting
        $request = new Request();
        $request->request->set('order', 'existing-order');

        $context = new ResolverContext(Generator::generateSalesChannelContext(), $request);
        $data = new ElementDataCollection();

        $expectedResult = $this->createMock(ProductListingResult::class);
        $response = new ProductListingRouteResponse($expectedResult);

        $route = $this->createMock(AbstractProductListingRoute::class);
        $route->expects($this->once())->method('load')->willReturn($response);

        // Create a sorting with the ID from defaultSorting config
        $sorting = new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                'id' => 'sorting-id-1',
                'key' => 'expected-sorting',
            ]),
        ]);

        $repository = new StaticEntityRepository([$sorting]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        $resolver->enrich($slot, $context, $data);

        // Verify that order parameter is not overwritten (i.e., line 129 was executed)
        static::assertSame('existing-order', $request->get('order'));
        static::assertNotSame('expected-sorting', $request->get('order'));
    }

    public function testRestrictSortings(): void
    {
        $config = new FieldConfigCollection([]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);
        $slot->setTranslated([
            'config' => [
                'useCustomSorting' => [
                    'value' => true,
                ],
                'availableSortings' => [
                    'value' => [
                        'sorting-id-1' => 10,
                        'sorting-id-2' => 20,
                    ],
                ],
            ],
        ]);

        $request = new Request();
        $context = new ResolverContext(Generator::generateSalesChannelContext(), $request);
        $data = new ElementDataCollection();

        $expectedResult = $this->createMock(ProductListingResult::class);
        $response = new ProductListingRouteResponse($expectedResult);

        $route = $this->createMock(AbstractProductListingRoute::class);
        $route->expects($this->once())->method('load')->willReturn($response);

        $sorting = new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                'id' => 'sorting-id-1',
                'key' => 'sorting-key-1',
            ]),
            (new ProductSortingEntity())->assign([
                'id' => 'sorting-id-2',
                'key' => 'sorting-key-2',
            ]),
        ]);

        $repository = new StaticEntityRepository([$sorting]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        $resolver->enrich($slot, $context, $data);

        // Do not directly verify the value as it causes Symfony\Component\HttpFoundation\Exception\BadRequestException
        // since the Request object's InputBag only accepts scalar values
        static::assertTrue($request->request->has('availableSortings'));

        // Verify restrictedProductSortingCollection is set in request attributes
        $restrictedSortings = $request->attributes->get('restrictedProductSortingCollection');
        static::assertInstanceOf(ProductSortingCollection::class, $restrictedSortings);

        // Verify the sorting with higher priority comes first
        static::assertCount(2, $restrictedSortings);
        $firstSorting = $restrictedSortings->first();
        $lastSorting = $restrictedSortings->last();
        static::assertNotNull($firstSorting);
        static::assertNotNull($lastSorting);
        static::assertSame('sorting-id-2', $firstSorting->getId());
        static::assertSame('sorting-id-1', $lastSorting->getId());
    }

    public function testRestrictSortingsWithEmptyConfig(): void
    {
        $config = new FieldConfigCollection([]);

        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        $slot->setFieldConfig($config);
        $slot->setTranslated([
            'config' => [
                'useCustomSorting' => [
                    'value' => true,
                ],
                // No availableSortings configuration
            ],
        ]);

        $request = new Request();
        $context = new ResolverContext(Generator::generateSalesChannelContext(), $request);
        $data = new ElementDataCollection();

        $expectedResult = $this->createMock(ProductListingResult::class);
        $response = new ProductListingRouteResponse($expectedResult);

        $route = $this->createMock(AbstractProductListingRoute::class);
        $route->expects($this->once())->method('load')->willReturn($response);

        $sorting = new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                'id' => 'sorting-id-1',
                'key' => 'sorting-key-1',
            ]),
        ]);

        $repository = new StaticEntityRepository([$sorting]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        $resolver->enrich($slot, $context, $data);

        // Verify availableSortings is not set in request
        static::assertNull($request->request->get('availableSortings'));
        static::assertNull($request->attributes->get('restrictedProductSortingCollection'));
    }

    private function assertRequestPayload(Request $request): void
    {
        static::assertNull($request->get('property-whitelist'));
        static::assertTrue($request->get('manufacturer-filter'));
        static::assertTrue($request->get('rating-filter'));
        static::assertTrue($request->get('shipping-free-filter'));
        static::assertTrue($request->get('price-filter'));
        static::assertTrue($request->get('property-filter'));
        static::assertSame('expected-sorting', $request->get('order'));
    }
}
