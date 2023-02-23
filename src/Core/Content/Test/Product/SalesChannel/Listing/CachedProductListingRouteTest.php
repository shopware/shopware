<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Listing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingRouteCacheTagsEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\CachedProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheTracer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @group cache
 * @group store-api
 */
class CachedProductListingRouteTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private const ALL_TAG = 'test-tag';

    private const DATA = [
        'name' => 'test',
        'productNumber' => 'test',
        'stock' => 10,
        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
        'tax' => ['name' => 'test', 'taxRate' => 15],
    ];

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    /**
     * @dataProvider criteriaProvider
     */
    public function testCriteria(Criteria $criteria): void
    {
        $ids = new IdsCollection();

        $context = $this->createMock(SalesChannelContext::class);
        $response = new ProductListingRouteResponse(new ProductListingResult('product', 0, new ProductCollection(), null, $criteria, $context->getContext()));

        $core = $this->createMock(ProductListingRoute::class);
        $core->expects(static::exactly(2))
            ->method('load')
            ->willReturn($response);

        $route = new CachedProductListingRoute(
            $core,
            $this->getContainer()->get('cache.object'),
            $this->getContainer()->get(EntityCacheKeyGenerator::class),
            $this->getContainer()->get(CacheTracer::class),
            $this->getContainer()->get('event_dispatcher'),
            []
        );

        $route->load($ids->get('c.1'), new Request(), $context, $criteria);

        $route->load($ids->get('c.1'), new Request(), $context, $criteria);

        $criteria->setLimit(200);

        // check that provided criteria has other key
        $route->load($ids->get('c.1'), new Request(), $context, $criteria);
    }

    public static function criteriaProvider(): \Generator
    {
        yield 'Paginated criteria' => [(new Criteria())->setOffset(1)->setLimit(20)];
        yield 'Filtered criteria' => [(new Criteria())->addFilter(new EqualsFilter('active', true))];
        yield 'Post filtered criteria' => [(new Criteria())->addPostFilter(new EqualsFilter('active', true))];
        yield 'Aggregation criteria' => [(new Criteria())->addAggregation(new StatsAggregation('price', 'price'))];
        yield 'Query criteria' => [(new Criteria())->addQuery(new ScoreQuery(new EqualsFilter('active', true), 200))];
        yield 'Term criteria' => [(new Criteria())->setTerm('test')];
        yield 'Sorted criteria' => [(new Criteria())->addSorting(new FieldSorting('active'))];
    }

    /**
     * @dataProvider stateProvider
     */
    public function testStates(array $current, array $config): void
    {
        $ids = new IdsCollection();

        $criteria = new Criteria();

        $hasState = \count(array_intersect($config, $current)) > 0;

        $context = $this->createMock(SalesChannelContext::class);
        $context->expects(static::any())
            ->method('hasState')
            ->willReturn($hasState);

        $response = new ProductListingRouteResponse(new ProductListingResult('product', 0, new ProductCollection(), null, $criteria, $context->getContext()));

        $core = $this->createMock(ProductListingRoute::class);

        $calls = 1;
        if ($hasState) {
            $calls = 2;
        }
        $core->expects(static::exactly($calls))
            ->method('load')
            ->willReturn($response);

        $route = new CachedProductListingRoute(
            $core,
            $this->getContainer()->get('cache.object'),
            $this->getContainer()->get(EntityCacheKeyGenerator::class),
            $this->getContainer()->get(CacheTracer::class),
            $this->getContainer()->get('event_dispatcher'),
            $config
        );

        $route->load($ids->get('c.1'), new Request(), $context, $criteria);

        $route->load($ids->get('c.1'), new Request(), $context, $criteria);
    }

    public static function stateProvider(): \Generator
    {
        yield 'No states' => [[], []];
        yield 'Has one state' => [['logged-in'], ['logged-in', 'cart-filled']];
        yield 'Has no state' => [['logged-in'], ['cart-filled']];
        yield 'Has multiple states' => [['logged-in', 'cart-filled'], ['logged-in', 'cart-filled']];
    }

    /**
     * @dataProvider invalidationProvider
     */
    public function testInvalidation(\Closure $before, \Closure $after, int $calls): void
    {
        $this->getContainer()->get('cache.object')->invalidateTags([self::ALL_TAG]);

        $this->getContainer()->get('event_dispatcher')
            ->addListener(ProductListingRouteCacheTagsEvent::class, static function (ProductListingRouteCacheTagsEvent $event): void {
                $event->addTags([self::ALL_TAG]);
            });

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly($calls))->method('__invoke');

        $this->getContainer()
            ->get('event_dispatcher')
            ->addListener(ProductListingRouteCacheTagsEvent::class, $listener);

        $categoryId = Uuid::randomHex();

        $category = [
            'id' => $categoryId,
            'name' => 'test',
            'parentId' => $this->context->getSalesChannel()->getNavigationCategoryId(),
        ];

        $this->getContainer()->get('category.repository')->create([$category], Context::createDefaultContext());

        $before($categoryId, $this->getContainer());

        $route = $this->getContainer()->get(ProductListingRoute::class);
        $route->load($categoryId, new Request(), $this->context, new Criteria());
        $route->load($categoryId, new Request(), $this->context, new Criteria());

        $after($categoryId, $this->getContainer());

        $route->load($categoryId, new Request(), $this->context, new Criteria());
        $route->load($categoryId, new Request(), $this->context, new Criteria());
    }

    public static function invalidationProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'cache is invalidated if the created product is linked to the category' => [
            function (): void {
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $product = array_merge(['id' => $ids->get('product')], self::DATA, self::assign($categoryId));
                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache is invalidated if the updated product is linked to the category' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $product = array_merge(['id' => $ids->get('product')], self::DATA, self::assign($categoryId));
                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $update = ['id' => $ids->get('product'), 'name' => 'test'];
                $container->get('product.repository')->update([$update], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache is invalidated if the deleted product is linked to the category' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $product = array_merge(['id' => $ids->get('product')], self::DATA, self::assign($categoryId));
                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $delete = ['id' => $ids->get('product')];
                $container->get('product.repository')->delete([$delete], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache is invalidated if the updated manufacturer is used as filter in the category listing' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $product = array_merge(
                    self::DATA,
                    self::assign($categoryId),
                    ['manufacturer' => ['id' => $ids->get('manufacturer'), 'name' => 'test']]
                );

                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $update = ['id' => $ids->get('manufacturer'), 'name' => 'test'];
                $container->get('product_manufacturer.repository')->update([$update], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache is invalidated if the deleted manufacturer is used as filter in the category listing' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $product = array_merge(
                    self::DATA,
                    self::assign($categoryId),
                    ['manufacturer' => ['id' => $ids->get('manufacturer'), 'name' => 'test']]
                );

                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $delete = ['id' => $ids->get('manufacturer')];
                $container->get('product_manufacturer.repository')->delete([$delete], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache is invalidated if the updated property is used as filter in the category listing' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $product = array_merge(
                    self::DATA,
                    self::assign($categoryId),
                    [
                        'properties' => [
                            ['id' => $ids->get('property'), 'name' => 'red', 'group' => ['name' => 'color']],
                        ],
                    ]
                );

                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $update = ['id' => $ids->get('property'), 'name' => 'yellow'];
                $container->get('property_group_option.repository')->update([$update], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache is invalidated if the deleted property is used as filter in the category listing' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $product = array_merge(
                    self::DATA,
                    self::assign($categoryId),
                    [
                        'properties' => [
                            ['id' => $ids->get('property'), 'name' => 'red', 'group' => ['name' => 'color']],
                        ],
                    ]
                );

                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $delete = ['id' => $ids->get('property')];
                $container->get('property_group_option.repository')->delete([$delete], Context::createDefaultContext());
            },
            2,
        ];

        yield 'cache is not invalidated if the created product is not linked to the category' => [
            function (): void {
            },
            function (string $categoryId, ContainerInterface $container): void {
                $product = self::DATA;
                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            1,
        ];

        yield 'Cache is not invalidated if the updated product is not linked to the category' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $product = array_merge(['id' => $ids->get('product')], self::DATA);
                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $update = ['id' => $ids->get('product'), 'name' => 'test'];
                $container->get('product.repository')->update([$update], Context::createDefaultContext());
            },
            1,
        ];

        yield 'Cache is not invalidated if the deleted product is not linked to the category' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $product = array_merge(['id' => $ids->get('product')], self::DATA);
                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $delete = ['id' => $ids->get('product')];
                $container->get('product.repository')->delete([$delete], Context::createDefaultContext());
            },
            1,
        ];

        yield 'Cache is not invalidated if the updated manufacturer is not used as filter in the category listing' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $container->get('product_manufacturer.repository')
                    ->create([['id' => $ids->get('manufacturer-not-used'), 'name' => 'test']], Context::createDefaultContext());

                $product = array_merge(
                    self::DATA,
                    self::assign($categoryId)
                );

                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $update = ['id' => $ids->get('manufacturer-not-used'), 'name' => 'test'];
                $container->get('product_manufacturer.repository')->update([$update], Context::createDefaultContext());
            },
            1,
        ];

        yield 'Cache is not invalidated if the deleted manufacturer is not used as filter in the category listing' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $container->get('product_manufacturer.repository')
                    ->create([['id' => $ids->get('manufacturer-not-used'), 'name' => 'test']], Context::createDefaultContext());

                $product = array_merge(
                    self::DATA,
                    self::assign($categoryId)
                );

                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $delete = ['id' => $ids->get('manufacturer-not-used')];
                $container->get('product_manufacturer.repository')->delete([$delete], Context::createDefaultContext());
            },
            1,
        ];

        yield 'Cache is not invalidated if the updated property is not used as filter in the category listing' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $container->get('property_group_option.repository')->create(
                    [
                        ['id' => $ids->get('property'), 'name' => 'red', 'group' => ['name' => 'color']],
                    ],
                    Context::createDefaultContext()
                );

                $product = array_merge(
                    self::DATA,
                    self::assign($categoryId),
                );

                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $update = ['id' => $ids->get('property'), 'name' => 'yellow'];
                $container->get('property_group_option.repository')->update([$update], Context::createDefaultContext());
            },
            1,
        ];

        yield 'Cache is not invalidated if the deleted property is not used as filter in the category listing' => [
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $container->get('property_group_option.repository')->create(
                    [
                        ['id' => $ids->get('property'), 'name' => 'red', 'group' => ['name' => 'color']],
                    ],
                    Context::createDefaultContext()
                );

                $product = array_merge(
                    self::DATA,
                    self::assign($categoryId),
                );

                $container->get('product.repository')->create([$product], Context::createDefaultContext());
            },
            function (string $categoryId, ContainerInterface $container) use ($ids): void {
                $delete = ['id' => $ids->get('property')];
                $container->get('property_group_option.repository')->delete([$delete], Context::createDefaultContext());
            },
            1,
        ];
    }

    private static function assign(string $categoryId): array
    {
        return ['categories' => [['id' => $categoryId]]];
    }
}
