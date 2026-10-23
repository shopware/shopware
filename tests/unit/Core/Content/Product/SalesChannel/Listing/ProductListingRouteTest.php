<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Extension\ProductListingCriteriaExtension;
use Shopware\Core\Content\Product\Extension\ProductListingRouteExtension;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\ProductStream\ProductStreamException;
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListingRoute::class)]
class ProductListingRouteTest extends TestCase
{
    public function testFiltersAreSetForCategories(): void
    {
        $route = $this->createRoute(self::categoryRepository(self::manualCategory('categoryId')));

        static::assertSame([
            'product.visibilities.visibility',
            'product.visibilities.salesChannelId',
            'product.active',
            'product.categoriesRo.id',
        ], self::loadCriteria($route, 'categoryId')->getFilterFields());
    }

    public function testFiltersAreSetForProductStreams(): void
    {
        $route = $this->createRoute(self::categoryRepository(self::groupCategory('categoryId', 'streamId')));

        $criteria = new Criteria();
        $result = $route->load('categoryId', new Request(), static::createStub(SalesChannelContext::class), $criteria)->getResult();

        static::assertSame([
            'product.visibilities.visibility',
            'product.visibilities.salesChannelId',
            'product.active',
            'product.product_stream',
        ], $criteria->getFilterFields());

        static::assertSame('streamId', $result->getStreamId());
    }

    public function testFiltersAndTagsAreSetForDescendantCategories(): void
    {
        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(
                ProductListingRoute::buildName('parent-category'),
                ProductListingRoute::buildName('child-category'),
                EntityCacheKeyGenerator::buildStreamTag('streamId')
            );

        $route = $this->createRoute(
            self::categoryRepository(self::manualCategory('parent-category'), [
                self::groupCategory('child-category', 'streamId', '|parent-category|'),
            ]),
            cacheTagCollector: $cacheTagCollector,
        );

        // the requested category alone covers all manual assignments below it, because categoriesRo holds every
        // category of a product's tree
        static::assertEquals(
            new OrFilter([
                new EqualsFilter('product.product_stream', 'streamId'),
                new EqualsAnyFilter('product.categoriesRo.id', ['parent-category']),
            ]),
            array_values(self::loadCriteria($route, 'parent-category')->getFilters())[1]
        );
    }

    public function testBrokenProductStreamListsNoProductsAndLogsAWarning(): void
    {
        $exception = ProductStreamException::noFilters('streamId');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Product stream configured for category listing has no usable filters.',
                [
                    'productStreamId' => 'streamId',
                    'exception' => $exception,
                ]
            );

        $criteria = $this->loadProductStreamListing($exception, $logger);

        static::assertContainsEquals(new EqualsFilter('product.id', null), $criteria->getFilters());
    }

    public function testEmptyProductStreamListsAllProducts(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $criteria = $this->loadProductStreamListing(ProductStreamException::emptyProductStream('streamId'), $logger);

        static::assertSame([
            'product.visibilities.visibility',
            'product.visibilities.salesChannelId',
            'product.active',
        ], $criteria->getFilterFields());
    }

    public function testClassIsBaseOfDecorationChain(): void
    {
        $eventDispatcher = new EventDispatcher();
        $controller = new ProductListingRoute(
            static::createStub(ProductListingLoader::class),
            static::createStub(EntityRepository::class),
            static::createStub(ProductStreamBuilder::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($eventDispatcher),
            static::createStub(LoggerInterface::class),
        );

        $this->expectException(DecorationPatternException::class);

        $controller->getDecorated();
    }

    public function testExtension(): void
    {
        $eventDispatcher = new EventDispatcher();

        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->exactly(2))->method('__invoke');
        $eventDispatcher->addListener(ProductListingCriteriaExtension::NAME . '.pre', $listener);
        $eventDispatcher->addListener(ProductListingCriteriaExtension::NAME . '.post', $listener);

        $route = $this->createRoute(self::categoryRepository(self::manualCategory('categoryId')), eventDispatcher: $eventDispatcher);

        static::assertSame([
            'product.visibilities.visibility',
            'product.visibilities.salesChannelId',
            'product.active',
            'product.categoriesRo.id',
        ], self::loadCriteria($route, 'categoryId')->getFilterFields());
    }

    public function testPublishesExtension(): void
    {
        $categoryId = Uuid::randomHex();
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(ProductListingRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('product-listing-route.load.pre', static function (ProductListingRouteExtension $extension) use ($categoryId, $request, $context, $criteria, $response): void {
            static::assertSame(['categoryId' => $categoryId, 'request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ProductListingRoute(
            static::createStub(ProductListingLoader::class),
            static::createStub(EntityRepository::class),
            static::createStub(ProductStreamBuilder::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
            static::createStub(LoggerInterface::class),
        );

        static::assertSame($response, $route->load($categoryId, $request, $context, $criteria));
    }

    public function testProductStreamWithDisplayAsGroupFalseCanEnableDirectVariantState(): void
    {
        $route = $this->createRoute(
            self::categoryRepository(self::groupCategory('categoryId', 'streamId')),
            self::createStreamBuilder(displayAsGroup: false),
        );

        static::assertTrue(self::loadCriteria($route, 'categoryId')->hasState(ProductListingLoader::STATE_SKIP_ADD_GROUPING));
    }

    public function testDisplayAsGroupFalseOfSubcategoryGroupDoesNotApplyToParentListing(): void
    {
        $route = $this->createRoute(
            self::categoryRepository(self::manualCategory('parent-category'), [
                self::groupCategory('child-category', 'streamId', '|parent-category|'),
            ]),
            self::createStreamBuilder(displayAsGroup: false),
        );

        $criteria = self::loadCriteria($route, 'parent-category');

        // the subcategory's group adds its products, but the parent listing keeps grouping variants
        static::assertContains('product.product_stream', $criteria->getFilterFields());
        static::assertFalse($criteria->hasState(ProductListingLoader::STATE_SKIP_ADD_GROUPING));
    }

    public function testProductStreamFallsBackToBuildFiltersForInterfaceOnlyBuilder(): void
    {
        // A builder that only implements the deprecated interface (e.g. a decorator that has not yet
        // adopted AbstractProductStreamBuilder). The route must fall back to buildFilters() without a
        // TypeError, add the stream filters, and leave display-as-group enabled (no state set).
        $productStreamBuilder = $this->createMock(ProductStreamBuilderInterface::class);
        $productStreamBuilder->expects($this->once())
            ->method('buildFilters')
            ->willReturn([new EqualsFilter('product.product_stream', 'streamId')]);

        $route = $this->createRoute(self::categoryRepository(self::groupCategory('categoryId', 'streamId')), $productStreamBuilder);

        $criteria = self::loadCriteria($route, 'categoryId');

        static::assertFalse($criteria->hasState(ProductListingLoader::STATE_SKIP_ADD_GROUPING));
        static::assertContainsEquals(new EqualsFilter('product.product_stream', 'streamId'), $criteria->getFilters());
    }

    #[DataProvider('unusableProductStreamProvider')]
    public function testUnusableGroupOfSubcategoryIsSkippedAndLogged(\Throwable $exception): void
    {
        $productStreamBuilder = static::createStub(ProductStreamBuilder::class);
        $productStreamBuilder->method('enrichCriteria')
            ->willReturnCallback(static function (Criteria $criteria, string $id) use ($exception): void {
                if ($id === 'brokenStreamId') {
                    throw $exception;
                }

                $criteria->addFilter(new EqualsFilter('product.product_stream', $id));
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Product stream of a subcategory cannot be used and is skipped in the category listing.',
                [
                    'categoryId' => 'parent-category',
                    'subcategoryId' => 'broken-child-category',
                    'productStreamId' => 'brokenStreamId',
                    'exception' => $exception,
                ]
            );

        $route = $this->createRoute(
            self::categoryRepository(self::manualCategory('parent-category'), [
                self::groupCategory('broken-child-category', 'brokenStreamId', '|parent-category|'),
                self::groupCategory('child-category', 'streamId', '|parent-category|'),
            ]),
            $productStreamBuilder,
            logger: $logger,
        );

        static::assertSame([
            'product.visibilities.visibility',
            'product.visibilities.salesChannelId',
            'product.active',
            'product.product_stream',
            'product.categoriesRo.id',
        ], self::loadCriteria($route, 'parent-category')->getFilterFields());
    }

    public static function unusableProductStreamProvider(): \Generator
    {
        yield 'group without a compiled filter, e.g. saved without conditions or not indexed yet' => [ProductStreamException::noFilters('brokenStreamId')];
        yield 'group whose only condition is empty' => [ProductStreamException::emptyProductStream('brokenStreamId')];
        yield 'group that cannot be found' => [ProductStreamException::productStreamNotFound('brokenStreamId')];
    }

    public function testUnusableGroupOfRequestedCategoryIsNotSkipped(): void
    {
        $productStreamBuilder = static::createStub(ProductStreamBuilder::class);
        $productStreamBuilder->method('enrichCriteria')
            ->willThrowException(ProductStreamException::productStreamNotFound('streamId'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $route = $this->createRoute(
            self::categoryRepository(self::groupCategory('categoryId', 'streamId')),
            $productStreamBuilder,
            logger: $logger,
        );

        $this->expectExceptionObject(ProductStreamException::productStreamNotFound('streamId'));

        self::loadCriteria($route, 'categoryId');
    }

    public function testSubcategoriesAreLoadedThroughTheGroupReference(): void
    {
        $subcategoryCriteria = null;
        $route = $this->createRoute(new StaticEntityRepository([
            new EntityCollection([self::manualCategory('categoryId')]),
            static function (Criteria $criteria) use (&$subcategoryCriteria): EntityCollection {
                $subcategoryCriteria = $criteria;

                return new EntityCollection([self::groupCategory('group-subcategory', 'streamId', '|categoryId|')]);
            },
        ]));

        self::loadCriteria($route, 'categoryId');

        // only subcategories that show a group, so the database can use the index on the group reference
        static::assertInstanceOf(Criteria::class, $subcategoryCriteria);
        static::assertEquals([
            new EqualsFilter('productAssignmentType', CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM),
            new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('productStreamId', null)]),
            new ContainsFilter('path', '|categoryId|'),
        ], $subcategoryCriteria->getFilters());
    }

    public function testGroupCategoryAddsTheManualCategoriesBelowIt(): void
    {
        $route = $this->createRoute(new StaticEntityRepository([
            new EntityCollection([self::groupCategory('group-category', 'streamId')]),
            new EntityCollection([self::groupCategory('group-subcategory', 'subcategoryStreamId', '|group-category|')]),
            static function (Criteria $criteria): EntityCollection {
                // the topmost manual categories are the children of the group category and of its group subcategories
                static::assertEquals([new EqualsAnyFilter('parentId', ['group-category', 'group-subcategory'])], $criteria->getFilters());

                return new EntityCollection([
                    self::manualCategory('manual-child'),
                    self::groupCategory('group-subcategory', 'subcategoryStreamId'),
                ]);
            },
        ]));

        static::assertEquals(
            new OrFilter([
                new EqualsFilter('product.product_stream', 'streamId'),
                new EqualsFilter('product.product_stream', 'subcategoryStreamId'),
                new EqualsAnyFilter('product.categoriesRo.id', ['manual-child']),
            ]),
            array_values(self::loadCriteria($route, 'group-category')->getFilters())[1]
        );
    }

    public function testListingIsTaggedWithTheCategoriesDownToAGroupSubcategory(): void
    {
        // moving or deleting any category on the way down to the group subcategory has to invalidate the listing,
        // the categories above the requested one do not matter
        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(
                ProductListingRoute::buildName('parent-category'),
                ProductListingRoute::buildName('category-between'),
                ProductListingRoute::buildName('group-subcategory'),
                EntityCacheKeyGenerator::buildStreamTag('streamId')
            );

        $route = $this->createRoute(
            self::categoryRepository(self::manualCategory('parent-category'), [
                self::groupCategory('group-subcategory', 'streamId', '|root-category|parent-category|category-between|'),
            ]),
            cacheTagCollector: $cacheTagCollector,
        );

        self::loadCriteria($route, 'parent-category');
    }

    public function testCategoryWithoutGroupIdIsTreatedAsManualCategory(): void
    {
        $productStreamBuilder = $this->createMock(ProductStreamBuilder::class);
        $productStreamBuilder->expects($this->never())->method('enrichCriteria');

        $route = $this->createRoute(self::categoryRepository(self::groupCategory('categoryId', '')), $productStreamBuilder);

        static::assertSame([
            'product.visibilities.visibility',
            'product.visibilities.salesChannelId',
            'product.active',
            'product.categoriesRo.id',
        ], self::loadCriteria($route, 'categoryId')->getFilterFields());
    }

    private function loadProductStreamListing(\Throwable $streamException, LoggerInterface $logger): Criteria
    {
        $productStreamBuilder = static::createStub(ProductStreamBuilder::class);
        $productStreamBuilder->method('enrichCriteria')->willThrowException($streamException);

        $route = $this->createRoute(
            self::categoryRepository(self::groupCategory('categoryId', 'streamId')),
            $productStreamBuilder,
            logger: $logger,
        );

        return self::loadCriteria($route, 'categoryId');
    }

    /**
     * @param StaticEntityRepository<EntityCollection<PartialEntity>> $categoryRepository
     */
    private function createRoute(
        StaticEntityRepository $categoryRepository,
        ProductStreamBuilderInterface|AbstractProductStreamBuilder|null $productStreamBuilder = null,
        ?CacheTagCollector $cacheTagCollector = null,
        ?LoggerInterface $logger = null,
        EventDispatcher $eventDispatcher = new EventDispatcher(),
    ): ProductListingRoute {
        return new ProductListingRoute(
            static::createStub(ProductListingLoader::class),
            $categoryRepository,
            $productStreamBuilder ?? self::createStreamBuilder(),
            $cacheTagCollector ?? static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($eventDispatcher),
            $logger ?? static::createStub(LoggerInterface::class),
        );
    }

    /**
     * The route searches the requested category, then its subcategories with a dynamic product group and, for a group
     * category, the manual categories below it
     *
     * @param list<PartialEntity> $streamSubcategories
     * @param list<PartialEntity> $manualChildren
     *
     * @return StaticEntityRepository<EntityCollection<PartialEntity>>
     */
    private static function categoryRepository(PartialEntity $category, array $streamSubcategories = [], array $manualChildren = []): StaticEntityRepository
    {
        return new StaticEntityRepository([
            new EntityCollection([$category]),
            new EntityCollection($streamSubcategories),
            new EntityCollection($manualChildren),
        ]);
    }

    private static function manualCategory(string $id): PartialEntity
    {
        return new PartialEntity([
            'id' => $id,
            'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT,
        ]);
    }

    private static function groupCategory(string $id, string $productStreamId, string $path = ''): PartialEntity
    {
        return new PartialEntity([
            'id' => $id,
            'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM,
            'productStreamId' => $productStreamId,
            'path' => $path,
        ]);
    }

    /**
     * Resolves every group to a filter on its id. With $displayAsGroup false, the group shows the matching variants as
     * single products.
     */
    private static function createStreamBuilder(bool $displayAsGroup = true): ProductStreamBuilder
    {
        $productStreamBuilder = static::createStub(ProductStreamBuilder::class);
        $productStreamBuilder->method('enrichCriteria')
            ->willReturnCallback(static function (Criteria $criteria, string $id) use ($displayAsGroup): void {
                $criteria->addFilter(new EqualsFilter('product.product_stream', $id));

                if (!$displayAsGroup) {
                    $criteria->addState(ProductListingLoader::STATE_SKIP_ADD_GROUPING);
                }
            });

        return $productStreamBuilder;
    }

    private static function loadCriteria(ProductListingRoute $route, string $categoryId): Criteria
    {
        $criteria = new Criteria();
        $route->load($categoryId, new Request(), static::createStub(SalesChannelContext::class), $criteria);

        return $criteria;
    }
}
