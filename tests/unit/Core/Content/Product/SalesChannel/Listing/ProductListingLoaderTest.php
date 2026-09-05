<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingResolvePreviewEvent;
use Shopware\Core\Content\Product\Extension\LoadPreviewExtension;
use Shopware\Core\Content\Product\Extension\ResolveListingIdsExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListingLoader::class)]
class ProductListingLoaderTest extends TestCase
{
    /**
     * @var MockObject&SalesChannelRepository<ProductCollection>
     */
    private MockObject&SalesChannelRepository $productRepository;

    private MockObject&SystemConfigService $systemConfigService;

    private Stub&Connection $connection;

    private EventDispatcher $eventDispatcher;

    private Stub&AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->connection = static::createStub(Connection::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->productCloseoutFilterFactory = static::createStub(AbstractProductCloseoutFilterFactory::class);
        $this->salesChannelContext = Generator::generateSalesChannelContext();
    }

    public function testLoadAddsDisplayGroupGroupingByDefault(): void
    {
        $this->systemConfigService
            ->expects($this->exactly(3))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);

                return match ($key) {
                    'core.listing.partialDataLoading' => false,
                    'core.listing.hideCloseoutProductsWhenOutOfStock' => false,
                    'core.listing.findBestVariant' => false,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $this->productRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                static::assertCount(1, $criteria->getGroupFields());
                static::assertTrue(array_filter(
                    $criteria->getFilters(),
                    static fn ($filter): bool => $filter instanceof NotEqualsFilter && $filter->getField() === 'displayGroup'
                ) !== []);

                return $this->createIdSearchResult($criteria, [
                    'red-l' => ['score' => 10.0],
                    'blue-m' => ['score' => 5.0],
                ]);
            });

        $this->productRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function (LoadPreviewExtension $extension): void {
                $extension->result = array_combine($extension->ids, $extension->ids);
                $extension->stopPropagation();
            }
        );

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertSame(['red-l', 'blue-m'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createProductSearchResult($criteria, ['red-l', 'blue-m']);
            });

        $result = $this->createLoader()->load(new Criteria(), $this->salesChannelContext);

        static::assertSame(['red-l', 'blue-m'], array_values($result->getEntities()->getIds()));
        static::assertSame(2, $result->getTotal());
    }

    public function testLoadUsesGroupedPartialPageAsExactTotal(): void
    {
        $this->systemConfigService
            ->expects($this->exactly(3))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);

                return match ($key) {
                    'core.listing.partialDataLoading' => false,
                    'core.listing.hideCloseoutProductsWhenOutOfStock' => false,
                    'core.listing.findBestVariant' => false,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $this->productRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                return $this->createIdSearchResult($criteria, [
                    'red-l' => ['score' => 10.0],
                    'blue-m' => ['score' => 5.0],
                ], total: 4);
            });

        $this->productRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function (LoadPreviewExtension $extension): void {
                $extension->result = array_combine($extension->ids, $extension->ids);
                $extension->stopPropagation();
            }
        );

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                return $this->createProductSearchResult($criteria, ['red-l', 'blue-m']);
            });

        $criteria = new Criteria();
        $criteria->setLimit(24);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->createLoader()->load($criteria, $this->salesChannelContext);

        static::assertSame(2, $result->getTotal());
    }

    public function testLoadCountsGroupedFullPage(): void
    {
        $this->systemConfigService
            ->expects($this->exactly(3))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);

                return match ($key) {
                    'core.listing.partialDataLoading' => false,
                    'core.listing.hideCloseoutProductsWhenOutOfStock' => false,
                    'core.listing.findBestVariant' => false,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $this->productRepository
            ->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                if ($criteria->getLimit() === 1) {
                    static::assertSame(0, $criteria->getOffset());

                    return $this->createIdSearchResult($criteria, [
                        'red-l' => ['score' => 10.0],
                    ], total: 3);
                }

                return $this->createIdSearchResult($criteria, [
                    'red-l' => ['score' => 10.0],
                    'blue-m' => ['score' => 5.0],
                ], total: 4);
            });

        $this->productRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function (LoadPreviewExtension $extension): void {
                $extension->result = array_combine($extension->ids, $extension->ids);
                $extension->stopPropagation();
            }
        );

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                return $this->createProductSearchResult($criteria, ['red-l', 'blue-m']);
            });

        $criteria = new Criteria();
        $criteria->setLimit(2);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->createLoader()->load($criteria, $this->salesChannelContext);

        static::assertSame(3, $result->getTotal());
    }

    public function testLoadKeepsExtensionTotalWhenGroupingIsSkipped(): void
    {
        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->willReturnMap([
                ['core.listing.partialDataLoading', $this->salesChannelContext->getSalesChannelId(), false],
            ]);

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(ResolveListingIdsExtension::NAME),
            function (ResolveListingIdsExtension $extension): void {
                $extension->criteria->resetFilters();
                $extension->criteria->addGroupField(new FieldGrouping('displayGroup'));
                $extension->result = $this->createIdSearchResult($extension->criteria, [
                    'red-l' => ['score' => 10.0],
                    'blue-m' => ['score' => 5.0],
                ], total: 7);
                $extension->stopPropagation();
            }
        );

        $this->productRepository
            ->expects($this->never())
            ->method('searchIds');

        $this->productRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function (LoadPreviewExtension $extension): void {
                $extension->result = array_combine($extension->ids, $extension->ids);
                $extension->stopPropagation();
            }
        );

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                return $this->createProductSearchResult($criteria, ['red-l', 'blue-m']);
            });

        $criteria = new Criteria();
        $criteria->setLimit(24);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $criteria->addState(ProductListingLoader::STATE_SKIP_ADD_GROUPING);

        $result = $this->createLoader()->load($criteria, $this->salesChannelContext);

        static::assertSame(7, $result->getTotal());
    }

    public function testLoadSkipsGroupingAndPreviewWhenDirectVariantStateIsPresent(): void
    {
        $previewLoaded = false;
        $resolvePreviewEventSeen = false;

        $this->systemConfigService
            ->expects($this->exactly(2))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);

                return match ($key) {
                    'core.listing.partialDataLoading' => false,
                    'core.listing.hideCloseoutProductsWhenOutOfStock' => false,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $this->productRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                static::assertCount(0, $criteria->getGroupFields());
                static::assertFalse(array_filter(
                    $criteria->getFilters(),
                    static fn ($filter): bool => $filter instanceof NotEqualsFilter && $filter->getField() === 'displayGroup'
                ) !== []);

                return $this->createIdSearchResult($criteria, [
                    'variant-a' => ['score' => 10.0],
                    'variant-b' => ['score' => 5.0],
                ]);
            });

        $this->productRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function () use (&$previewLoaded): void {
                $previewLoaded = true;
            }
        );

        $this->eventDispatcher->addListener(
            ProductListingResolvePreviewEvent::class,
            static function (ProductListingResolvePreviewEvent $event) use (&$resolvePreviewEventSeen): void {
                $resolvePreviewEventSeen = true;
                static::assertFalse($event->hasOptionFilter());
                static::assertSame([
                    'variant-a' => 'variant-a',
                    'variant-b' => 'variant-b',
                ], $event->getMapping());
            }
        );

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertSame(['variant-a', 'variant-b'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createProductSearchResult($criteria, ['variant-a', 'variant-b']);
            });

        $criteria = new Criteria();
        $criteria->addState(ProductListingLoader::STATE_SKIP_ADD_GROUPING);

        $result = $this->createLoader()->load($criteria, $this->salesChannelContext);

        static::assertFalse($previewLoaded);
        static::assertTrue($resolvePreviewEventSeen);
        static::assertSame(['variant-a', 'variant-b'], array_values($result->getEntities()->getIds()));
    }

    public function testLoadResolvesPreviewOnSearchRouteWithOptionPostFilterWhenFindBestVariantIsDisabled(): void
    {
        $previewLoaded = false;
        $resolvePreviewEventSeen = false;
        $configKeys = [];

        $this->systemConfigService
            ->expects($this->exactly(4))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId) use (&$configKeys): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);
                $configKeys[] = $key;

                return match ($key) {
                    'core.listing.partialDataLoading' => false,
                    'core.listing.hideCloseoutProductsWhenOutOfStock' => false,
                    'core.listing.findBestVariant' => false,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $this->productRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                static::assertSame([], $criteria->getIds());

                return $this->createIdSearchResult($criteria, [
                    'variant-id' => ['score' => 10.0],
                ]);
            });

        $this->productRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function (LoadPreviewExtension $extension) use (&$previewLoaded): void {
                $previewLoaded = true;
                $extension->result = [
                    'variant-id' => 'preview-id',
                ];
                $extension->stopPropagation();
            }
        );

        $this->eventDispatcher->addListener(
            ProductListingResolvePreviewEvent::class,
            static function (ProductListingResolvePreviewEvent $event) use (&$resolvePreviewEventSeen): void {
                $resolvePreviewEventSeen = true;
                static::assertTrue($event->hasOptionFilter());
                static::assertSame(['variant-id' => 'preview-id'], $event->getMapping());
            }
        );

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertSame(['preview-id'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createProductSearchResult($criteria, ['preview-id']);
            });

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaProductSearchRoute::STATE);
        $criteria->addPostFilter(new EqualsFilter('product.options.id', 'option-id'));

        $result = $this->createLoader()->load($criteria, $this->salesChannelContext);

        static::assertTrue($previewLoaded);
        static::assertTrue($resolvePreviewEventSeen);
        static::assertSame([
            'core.listing.partialDataLoading',
            'core.listing.findBestVariant',
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            'core.listing.findBestVariant',
        ], $configKeys);
        static::assertSame(['preview-id'], array_values($result->getEntities()->getIds()));
    }

    public function testLoadSkipsPreviewOnSearchRouteWhenFindBestVariantIsEnabled(): void
    {
        $previewLoaded = false;

        $this->systemConfigService
            ->expects($this->exactly(4))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);

                return match ($key) {
                    'core.listing.partialDataLoading' => false,
                    'core.listing.hideCloseoutProductsWhenOutOfStock' => false,
                    'core.listing.findBestVariant' => true,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $this->productRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                static::assertSame([], $criteria->getIds());
                static::assertContains(Criteria::STATE_SCORE_RANKED_GROUPING, $criteria->getStates());

                return $this->createIdSearchResult($criteria, [
                    'variant-id' => ['score' => 10.0],
                ]);
            });

        $this->productRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function () use (&$previewLoaded): void {
                $previewLoaded = true;
            }
        );

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertSame(['variant-id'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createProductSearchResult($criteria, ['variant-id']);
            });

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaProductSearchRoute::STATE);

        $result = $this->createLoader()->load($criteria, $this->salesChannelContext);

        static::assertFalse($previewLoaded);
        static::assertSame(['variant-id'], array_values($result->getEntities()->getIds()));
    }

    public function testScoreRankedGroupingExcludesProductsWithVariants(): void
    {
        $actual = $this->resolveSearchIds(findBestVariant: true);

        $expected = new Criteria();
        $expected->addState(ResolvedCriteriaProductSearchRoute::STATE);
        $expected->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $expected->addGroupField(new FieldGrouping('displayGroup'));
        $expected->addFilter(new NotEqualsFilter('displayGroup', null));
        $expected->addState(Criteria::STATE_SCORE_RANKED_GROUPING);
        $expected->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('childCount', 0),
            new EqualsFilter('childCount', null),
        ]));

        static::assertEquals($expected, $actual);
    }

    public function testNonBestVariantSearchDoesNotExcludeProductsWithVariants(): void
    {
        $criteria = $this->resolveSearchIds(findBestVariant: false);

        static::assertFalse($criteria->hasState(Criteria::STATE_SCORE_RANKED_GROUPING));
        static::assertNull($this->findChildCountFilter($criteria));
    }

    private function createLoader(): ProductListingLoader
    {
        return new ProductListingLoader(
            $this->productRepository,
            $this->systemConfigService,
            $this->connection,
            $this->eventDispatcher,
            $this->productCloseoutFilterFactory,
            new ExtensionDispatcher($this->eventDispatcher)
        );
    }

    /**
     * @param array<string, array<string, mixed>> $ids
     */
    private function createIdSearchResult(Criteria $criteria, array $ids, ?int $total = null): IdSearchResult
    {
        $data = [];
        foreach ($ids as $id => $row) {
            $data[$id] = [
                'primaryKey' => $id,
                'data' => $row,
            ];
        }

        return new IdSearchResult($total ?? \count($data), $data, $criteria, $this->salesChannelContext->getContext());
    }

    /**
     * @param list<string> $ids
     *
     * @return EntitySearchResult<ProductCollection>
     */
    private function createProductSearchResult(Criteria $criteria, array $ids): EntitySearchResult
    {
        $products = new ProductCollection();
        foreach ($ids as $id) {
            $products->add((new ProductEntity())->assign(['id' => $id]));
        }

        return new EntitySearchResult('product', $products->count(), $products, new AggregationResultCollection(), $criteria, $this->salesChannelContext->getContext());
    }

    /**
     * Runs a search-route listing through the public loader and returns the criteria the
     * loader resolved the ids with.
     */
    private function resolveSearchIds(bool $findBestVariant): Criteria
    {
        $this->systemConfigService
            ->expects($this->atLeastOnce())
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId) use ($findBestVariant): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);

                return match ($key) {
                    'core.listing.findBestVariant' => $findBestVariant,
                    'core.listing.partialDataLoading', 'core.listing.hideCloseoutProductsWhenOutOfStock' => false,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $resolved = null;
        $this->productRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria) use (&$resolved): IdSearchResult {
                $resolved = $criteria;

                // no ids: the loader stops right after resolving them
                return $this->createIdSearchResult($criteria, []);
            });

        $this->productRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaProductSearchRoute::STATE);

        $this->createLoader()->load($criteria, $this->salesChannelContext);

        static::assertInstanceOf(Criteria::class, $resolved);

        return $resolved;
    }

    private function findChildCountFilter(Criteria $criteria): ?MultiFilter
    {
        foreach ($criteria->getFilters() as $filter) {
            if (!$filter instanceof MultiFilter) {
                continue;
            }

            if (\in_array('childCount', $filter->getFields(), true)) {
                return $filter;
            }
        }

        return null;
    }
}
