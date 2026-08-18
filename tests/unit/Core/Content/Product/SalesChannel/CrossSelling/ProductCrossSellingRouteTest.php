<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\CrossSelling;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsEntity;
use Shopware\Core\Content\Product\Events\ProductCrossSellingStreamCriteriaEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductCrossSellingRoute::class)]
class ProductCrossSellingRouteTest extends TestCase
{
    /**
     * @var EntityRepository<ProductCrossSellingCollection>&Stub
     */
    private Stub&EntityRepository $crossSellingRepository;

    private Stub&ProductListingLoader $listingLoader;

    private CacheTagCollector&Stub $cacheTagCollector;

    private ProductStreamBuilder&Stub $productStreamBuilder;

    private Connection&Stub $connection;

    private ProductCrossSellingRoute $route;

    protected function setUp(): void
    {
        $this->crossSellingRepository = static::createStub(EntityRepository::class);
        $this->listingLoader = static::createStub(ProductListingLoader::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('fetchOne')->willReturn(false);
        $this->productStreamBuilder = static::createStub(ProductStreamBuilder::class);
        $this->productStreamBuilder->method('enrichCriteria')->willReturnCallback(static function (Criteria $criteria, mixed ...$_): void {
            $criteria->addFilter(new EqualsFilter('product.product_stream', 'stream'));
        });

        $this->route = $this->createRoute();
    }

    public function testLoadAddsTags(): void
    {
        $productId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $streamId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $childParentId = Uuid::randomHex();

        $crossSelling = new ProductCrossSellingEntity();
        $crossSelling->setUniqueIdentifier($crossSellingId);
        $crossSelling->setType(ProductCrossSellingDefinition::TYPE_PRODUCT_STREAM);
        $crossSelling->setProductStreamId($streamId);
        $crossSelling->setProductId($productId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'product_cross_selling',
                1,
                new ProductCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $child = new ProductEntity();
        $child->setUniqueIdentifier($childId);
        $child->setId($childId);
        $child->setParentId($childParentId);

        $this->listingLoader->method('load')->willReturn(
            new EntitySearchResult(
                'product',
                1,
                new ProductCollection([$child]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $calls = [
            [EntityCacheKeyGenerator::buildStreamTag($streamId)],
            [
                EntityCacheKeyGenerator::buildProductTag($productId),
                EntityCacheKeyGenerator::buildProductTag($childId),
                EntityCacheKeyGenerator::buildProductTag($childParentId),
            ],
        ];
        $matcher = $this->exactly(\count($calls));
        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($matcher)
            ->method('addTag')
            ->willReturnCallback(static function (string ...$tags) use ($matcher, $calls): void {
                self::assertSame($calls[$matcher->numberOfInvocations() - 1], $tags);
            });

        $this->createRoute($cacheTagCollector)->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria());
    }

    public function testLoadByStreamPropagatesDirectVariantState(): void
    {
        $productId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $streamId = Uuid::randomHex();

        $crossSelling = new ProductCrossSellingEntity();
        $crossSelling->setUniqueIdentifier($crossSellingId);
        $crossSelling->setType(ProductCrossSellingDefinition::TYPE_PRODUCT_STREAM);
        $crossSelling->setProductStreamId($streamId);
        $crossSelling->setProductId($productId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'product_cross_selling',
                1,
                new ProductCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $this->productStreamBuilder->method('enrichCriteria')->willReturnCallback(static function (Criteria $criteria, string $id, mixed ...$_) use ($streamId): void {
            static::assertSame($streamId, $id);
            $criteria->addFilter(new EqualsFilter('product.product_stream', $streamId));
            $criteria->addState(ProductListingLoader::STATE_SKIP_ADD_GROUPING);
        });

        $listingLoader = $this->createMock(ProductListingLoader::class);
        $listingLoader->expects($this->once())
            ->method('load')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertTrue($criteria->hasState(ProductListingLoader::STATE_SKIP_ADD_GROUPING));

                return new EntitySearchResult(
                    'product',
                    0,
                    new ProductCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute(listingLoader: $listingLoader)->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria());
    }

    public function testLoadByStreamIgnoresPartialFieldSelection(): void
    {
        $productId = Uuid::randomHex();
        $crossSellingProductId = Uuid::randomHex();

        $crossSelling = new ProductCrossSellingEntity();
        $crossSelling->setUniqueIdentifier(Uuid::randomHex());
        $crossSelling->setType(ProductCrossSellingDefinition::TYPE_PRODUCT_STREAM);
        $crossSelling->setProductStreamId(Uuid::randomHex());
        $crossSelling->setProductId($productId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'product_cross_selling',
                1,
                new ProductCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $listingLoader = $this->createMock(ProductListingLoader::class);
        $listingLoader->expects($this->once())
            ->method('load')
            ->willReturnCallback(static fn (Criteria $criteria): EntitySearchResult => self::searchProducts($criteria, $crossSellingProductId));

        $element = $this->createRoute(listingLoader: $listingLoader)
            ->load($productId, new Request(), Generator::generateSalesChannelContext(), (new Criteria())->addFields(['id', 'name']))
            ->getResult()
            ->first();

        static::assertNotNull($element);
        static::assertSame([$crossSellingProductId], array_values($element->getProducts()->getIds()));
        static::assertSame(1, $element->getTotal());
    }

    public function testLoadDropsThePartialFieldSelectionBeforeTheCriteriaEvents(): void
    {
        $productId = Uuid::randomHex();
        $crossSellingProductId = Uuid::randomHex();

        $crossSelling = new ProductCrossSellingEntity();
        $crossSelling->setUniqueIdentifier(Uuid::randomHex());
        $crossSelling->setType(ProductCrossSellingDefinition::TYPE_PRODUCT_STREAM);
        $crossSelling->setProductStreamId(Uuid::randomHex());
        $crossSelling->setProductId($productId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'product_cross_selling',
                1,
                new ProductCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $listingLoader = $this->createMock(ProductListingLoader::class);
        $listingLoader->expects($this->once())
            ->method('load')
            ->willReturnCallback(static fn (Criteria $criteria): EntitySearchResult => self::searchProducts($criteria, $crossSellingProductId));

        // excludeFields() is the documented way to drop heavy columns, but it rejects a criteria that
        // still carries an addFields() selection, so the subscriber must not see one
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            ProductCrossSellingStreamCriteriaEvent::class,
            static function (ProductCrossSellingStreamCriteriaEvent $event): void {
                $event->getCriteria()->excludeFields(['description']);
            }
        );

        $route = new ProductCrossSellingRoute(
            $this->crossSellingRepository,
            $eventDispatcher,
            $this->productStreamBuilder,
            static::createStub(SalesChannelRepository::class),
            static::createStub(SystemConfigService::class),
            $listingLoader,
            static::createStub(AbstractProductCloseoutFilterFactory::class),
            $this->cacheTagCollector,
            $this->connection
        );

        $element = $route
            ->load($productId, new Request(), Generator::generateSalesChannelContext(), (new Criteria())->addFields(['id', 'name']))
            ->getResult()
            ->first();

        static::assertNotNull($element);
        static::assertSame([$crossSellingProductId], array_values($element->getProducts()->getIds()));
    }

    public function testLoadByIdsIgnoresPartialFieldSelection(): void
    {
        $productId = Uuid::randomHex();
        $crossSellingProductId = Uuid::randomHex();

        $assignedProduct = new ProductCrossSellingAssignedProductsEntity();
        $assignedProduct->setUniqueIdentifier(Uuid::randomHex());
        $assignedProduct->setProductId($crossSellingProductId);
        $assignedProduct->setPosition(1);

        $crossSelling = new ProductCrossSellingEntity();
        $crossSelling->setUniqueIdentifier(Uuid::randomHex());
        $crossSelling->setType(ProductCrossSellingDefinition::TYPE_PRODUCT_LIST);
        $crossSelling->setProductId($productId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');
        $crossSelling->setAssignedProducts(new ProductCrossSellingAssignedProductsCollection([$assignedProduct]));

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'product_cross_selling',
                1,
                new ProductCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $productRepository = $this->createMock(SalesChannelRepository::class);
        $productRepository->expects($this->once())
            ->method('search')
            ->willReturnCallback(static fn (Criteria $criteria): EntitySearchResult => self::searchProducts($criteria, $crossSellingProductId));

        $element = $this->createRoute(productRepository: $productRepository)
            ->load($productId, new Request(), Generator::generateSalesChannelContext(), (new Criteria())->addFields(['id', 'name']))
            ->getResult()
            ->first();

        static::assertNotNull($element);
        static::assertSame([$crossSellingProductId], array_values($element->getProducts()->getIds()));
        static::assertSame(1, $element->getTotal());
    }

    public function testLoadAlwaysAddsStreamTagForStreamCrossSelling(): void
    {
        $productId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $streamId = Uuid::randomHex();

        $crossSelling = new ProductCrossSellingEntity();
        $crossSelling->setUniqueIdentifier($crossSellingId);
        $crossSelling->setType(ProductCrossSellingDefinition::TYPE_PRODUCT_STREAM);
        $crossSelling->setProductStreamId($streamId);
        $crossSelling->setProductId($productId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'product_cross_selling',
                1,
                new ProductCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $this->listingLoader->method('load')->willReturn(
            new EntitySearchResult(
                'product',
                0,
                new ProductCollection(),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $observedTags = [];
        $this->cacheTagCollector
            ->method('addTag')
            ->willReturnCallback(static function (string ...$tags) use (&$observedTags): void {
                foreach ($tags as $tag) {
                    $observedTags[] = $tag;
                }
            });

        $this->route->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria());

        static::assertContains(
            EntityCacheKeyGenerator::buildStreamTag($streamId),
            $observedTags,
            'Stream tag must be added unconditionally so product_stream_filter writes invalidate cross-selling responses.'
        );
    }

    public function testLoadByStreamExcludesTheCurrentProduct(): void
    {
        $productId = Uuid::randomHex();

        $criteria = $this->loadStreamCriteria($productId);

        static::assertContainsEquals(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsFilter('product.id', $productId),
                new EqualsFilter('product.parentId', $productId),
            ]),
            $criteria->getFilters()
        );
    }

    public function testLoadByStreamExcludesTheCompleteVariantFamilyOfTheCurrentVariant(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $criteria = $this->loadStreamCriteria($variantId, $parentId);

        static::assertContainsEquals(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsFilter('product.id', $parentId),
                new EqualsFilter('product.parentId', $parentId),
            ]),
            $criteria->getFilters(),
            'Variant grouping and main variant resolution would otherwise resolve a sibling variant back to the currently viewed variant.'
        );
    }

    public function testLoadByStreamFallsBackToBuildFiltersForInterfaceOnlyBuilder(): void
    {
        $productId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $streamId = Uuid::randomHex();

        $crossSelling = new ProductCrossSellingEntity();
        $crossSelling->setUniqueIdentifier($crossSellingId);
        $crossSelling->setType(ProductCrossSellingDefinition::TYPE_PRODUCT_STREAM);
        $crossSelling->setProductStreamId($streamId);
        $crossSelling->setProductId($productId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'product_cross_selling',
                1,
                new ProductCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        // A builder that only implements the deprecated interface (e.g. a decorator that has not yet adopted
        // AbstractProductStreamBuilder). The route must fall back to buildFilters() without a TypeError, add
        // the stream filters and leave display-as-group enabled (no skip-grouping state).
        $productStreamBuilder = $this->createMock(ProductStreamBuilderInterface::class);
        $productStreamBuilder->expects($this->once())
            ->method('buildFilters')
            ->willReturn([new EqualsFilter('product.product_stream', $streamId)]);

        $listingLoader = $this->createMock(ProductListingLoader::class);
        $listingLoader->expects($this->once())
            ->method('load')
            ->willReturnCallback(function (Criteria $criteria) use ($streamId): EntitySearchResult {
                static::assertContainsEquals(new EqualsFilter('product.product_stream', $streamId), $criteria->getFilters());
                static::assertFalse($criteria->hasState(ProductListingLoader::STATE_SKIP_ADD_GROUPING));

                return new EntitySearchResult(
                    'product',
                    0,
                    new ProductCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $route = new ProductCrossSellingRoute(
            $this->crossSellingRepository,
            static::createStub(EventDispatcherInterface::class),
            $productStreamBuilder,
            static::createStub(SalesChannelRepository::class),
            static::createStub(SystemConfigService::class),
            $listingLoader,
            static::createStub(AbstractProductCloseoutFilterFactory::class),
            $this->cacheTagCollector,
            $this->connection
        );

        $route->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria());
    }

    /**
     * Loads a single stream cross-selling of the product and returns the criteria it was loaded with.
     *
     * @param string|null $parentId parent of the loaded product, which also owns the cross-selling because
     *                              variants inherit the cross-sellings of their parent
     */
    private function loadStreamCriteria(string $productId, ?string $parentId = null): Criteria
    {
        $crossSelling = new ProductCrossSellingEntity();
        $crossSelling->setUniqueIdentifier(Uuid::randomHex());
        $crossSelling->setType(ProductCrossSellingDefinition::TYPE_PRODUCT_STREAM);
        $crossSelling->setProductStreamId(Uuid::randomHex());
        $crossSelling->setProductId($parentId ?? $productId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'product_cross_selling',
                1,
                new ProductCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($parentId ?? false);

        $streamCriteria = null;
        $listingLoader = $this->createMock(ProductListingLoader::class);
        $listingLoader->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Criteria $criteria) use (&$streamCriteria): EntitySearchResult {
                $streamCriteria = $criteria;

                return new EntitySearchResult(
                    'product',
                    0,
                    new ProductCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute(listingLoader: $listingLoader, connection: $connection)
            ->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria());

        static::assertInstanceOf(Criteria::class, $streamCriteria);

        return $streamCriteria;
    }

    /**
     * Emulates the reader: an allowlist field selection hydrates PartialEntity instances into a generic
     * EntityCollection, which the cross selling elements are not typed against.
     *
     * @return EntitySearchResult<covariant EntityCollection<Entity>>
     */
    private static function searchProducts(Criteria $criteria, string $productId): EntitySearchResult
    {
        if ($criteria->getFields() !== []) {
            $partial = new PartialEntity();
            $partial->setUniqueIdentifier($productId);
            $partial->assign(['id' => $productId]);
            $partial->internalSetEntityData(ProductDefinition::ENTITY_NAME, new FieldVisibility([]));

            $products = new EntityCollection([$partial]);
        } else {
            $product = new ProductEntity();
            $product->setUniqueIdentifier($productId);
            $product->setId($productId);

            $products = new ProductCollection([$product]);
        }

        return new EntitySearchResult(
            'product',
            1,
            $products,
            null,
            $criteria,
            Context::createDefaultContext()
        );
    }

    /**
     * @param SalesChannelRepository<ProductCollection>|null $productRepository
     */
    private function createRoute(
        ?CacheTagCollector $cacheTagCollector = null,
        ?ProductListingLoader $listingLoader = null,
        ?SalesChannelRepository $productRepository = null,
        ?Connection $connection = null,
    ): ProductCrossSellingRoute {
        return new ProductCrossSellingRoute(
            $this->crossSellingRepository,
            static::createStub(EventDispatcherInterface::class),
            $this->productStreamBuilder,
            $productRepository ?? static::createStub(SalesChannelRepository::class),
            static::createStub(SystemConfigService::class),
            $listingLoader ?? $this->listingLoader,
            static::createStub(AbstractProductCloseoutFilterFactory::class),
            $cacheTagCollector ?? $this->cacheTagCollector,
            $connection ?? $this->connection
        );
    }
}
