<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\CrossSelling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
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
     * @var EntityRepository<ProductCrossSellingCollection>&MockObject
     */
    private MockObject&EntityRepository $crossSellingRepository;

    private MockObject&ProductListingLoader $listingLoader;

    private MockObject&CacheTagCollector $cacheTagCollector;

    private MockObject&ProductStreamBuilder $productStreamBuilder;

    private ProductCrossSellingRoute $route;

    protected function setUp(): void
    {
        $this->crossSellingRepository = $this->createMock(EntityRepository::class);
        $this->listingLoader = $this->createMock(ProductListingLoader::class);
        $this->cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $this->productStreamBuilder = $this->createMock(ProductStreamBuilder::class);
        $this->productStreamBuilder->method('enrichCriteria')->willReturnCallback(static function (Criteria $criteria, mixed ...$_): void {
            $criteria->addFilter(new EqualsFilter('product.product_stream', 'stream'));
        });

        $this->route = new ProductCrossSellingRoute(
            $this->crossSellingRepository,
            $this->createMock(EventDispatcherInterface::class),
            $this->productStreamBuilder,
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(SystemConfigService::class),
            $this->listingLoader,
            $this->createMock(AbstractProductCloseoutFilterFactory::class),
            $this->cacheTagCollector
        );
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
        $this->cacheTagCollector->expects($matcher)
            ->method('addTag')
            ->willReturnCallback(static function (string ...$tags) use ($matcher, $calls): void {
                self::assertSame($calls[$matcher->numberOfInvocations() - 1], $tags);
            });

        $this->route->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria());
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

        $this->listingLoader->expects($this->once())
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

        $this->route->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria());
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

        $route = new ProductCrossSellingRoute(
            $this->crossSellingRepository,
            $this->createMock(EventDispatcherInterface::class),
            $productStreamBuilder,
            $this->createMock(SalesChannelRepository::class),
            $this->createMock(SystemConfigService::class),
            $this->listingLoader,
            $this->createMock(AbstractProductCloseoutFilterFactory::class),
            $this->cacheTagCollector
        );

        $this->listingLoader->expects($this->once())
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

        $route->load($productId, new Request(), Generator::generateSalesChannelContext(), new Criteria());
    }
}
