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
        /** @var StaticEntityRepository<ProductSortingCollection> */
        $repository = new StaticEntityRepository([]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        static::assertSame('product-listing', $resolver->getType());
    }

    public function testGetCollectReturnsNull(): void
    {
        $route = $this->createMock(AbstractProductListingRoute::class);
        /** @var StaticEntityRepository<ProductSortingCollection> */
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
                'id' => 'sorting-1',
                'key' => 'expected-sorting',
            ]),
        ]);

        /** @var StaticEntityRepository<ProductSortingCollection> */
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
        $request = new Request([
            'availableSortings' => [
                'sorting-id' => 'sorting-id-1',
            ],
        ]);
        $context = new ResolverContext(Generator::generateSalesChannelContext(), $request);
        $data = new ElementDataCollection();

        $expectedResult = $this->createMock(ProductListingResult::class);
        $response = new ProductListingRouteResponse($expectedResult);

        $route = $this->createMock(AbstractProductListingRoute::class);
        $route->expects($this->once())->method('load')->willReturn($response);

        $sorting = new ProductSortingCollection([
            (new ProductSortingEntity())->assign([
                'id' => 'sorting-1',
                'key' => 'expected-sorting',
            ]),
        ]);

        /** @var StaticEntityRepository<ProductSortingCollection> */
        $repository = new StaticEntityRepository([$sorting]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        $resolver->enrich($slot, $context, $data);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);
        static::assertInstanceOf(ProductListingResult::class, $data->getListing());

        $this->assertRequestPayload($request);
    }

    public function testRestrictFiltersReadsFromTranslatedConfig(): void
    {
        $slot = new CmsSlotEntity();
        $slot->setId('slot-1');
        // Only set the translated config (simulating category-level override)
        // Do NOT call setConfig() to simulate the scenario where the translated
        // config differs from the base config
        $slot->setTranslated([
            'config' => [
                'filters' => [
                    'value' => '', // All filters disabled
                ],
                'propertyWhitelist' => [
                    'value' => [],
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

        /** @var StaticEntityRepository<ProductSortingCollection> */
        $repository = new StaticEntityRepository([]);

        $resolver = new ProductListingCmsElementResolver($route, $repository);
        $resolver->enrich($slot, $context, $data);

        // All filters should be disabled since we set filters.value to empty string
        static::assertFalse($request->request->get('manufacturer-filter'));
        static::assertFalse($request->request->get('rating-filter'));
        static::assertFalse($request->request->get('shipping-free-filter'));
        static::assertFalse($request->request->get('price-filter'));
        static::assertFalse($request->request->get('property-filter'));
    }

    private function assertRequestPayload(Request $request): void
    {
        static::assertNull($request->request->get('property-whitelist'));
        static::assertTrue($request->request->get('manufacturer-filter'));
        static::assertTrue($request->request->get('rating-filter'));
        static::assertTrue($request->request->get('shipping-free-filter'));
        static::assertTrue($request->request->get('price-filter'));
        static::assertTrue($request->request->get('property-filter'));
        static::assertSame('expected-sorting', $request->request->get('order'));
    }
}
