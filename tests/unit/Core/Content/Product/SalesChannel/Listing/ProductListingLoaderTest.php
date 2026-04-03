<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Events\ProductListingResolvePreviewEvent;
use Shopware\Core\Content\Product\Extension\LoadPreviewExtension;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Content\Product\Util\ExplicitProductListingIdMerger;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ProductListingLoader::class)]
class ProductListingLoaderTest extends TestCase
{
    /**
     * @var MockObject&SalesChannelRepository<ProductCollection>
     */
    private MockObject&SalesChannelRepository $productRepository;

    private MockObject&SystemConfigService $systemConfigService;

    private MockObject&Connection $connection;

    private EventDispatcher $eventDispatcher;

    private MockObject&AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->connection = $this->createMock(Connection::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->productCloseoutFilterFactory = $this->createMock(AbstractProductCloseoutFilterFactory::class);
        $this->salesChannelContext = Generator::generateSalesChannelContext();
    }

    public function testLoadMergesExplicitProductIdsThroughPublicApi(): void
    {
        $this->systemConfigService
            ->expects($this->exactly(3))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);

                return match ($key) {
                    'core.listing.hideCloseoutProductsWhenOutOfStock' => false,
                    'core.listing.findBestVariant' => false,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $this->productRepository
            ->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                static $call = 0;
                ++$call;

                if ($call === 1) {
                    static::assertCount(1, $criteria->getGroupFields());
                    static::assertTrue(\count(array_filter(
                        $criteria->getFilters(),
                        static fn ($filter): bool => $filter instanceof NotEqualsFilter && $filter->getField() === 'displayGroup'
                    )) > 0);

                    return $this->createIdSearchResult($criteria, [
                        'red-l' => ['score' => 10.0],
                        'blue-m' => ['score' => 5.0],
                    ]);
                }

                static::assertSame(['green-l'], $criteria->getIds());
                static::assertNull($criteria->getOffset());
                static::assertNull($criteria->getLimit());

                return $this->createIdSearchResult($criteria, [
                    'green-l' => ['score' => 8.0],
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
            ->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                if ($criteria->getFields() === ['id', 'displayGroup']) {
                    static::assertSame(['red-l', 'blue-m', 'green-l'], $criteria->getIds());

                    return $this->createDisplayGroupSearchResult([
                        'red-l' => 'shirt-parent-a',
                        'blue-m' => 'shirt-parent-b',
                        'green-l' => 'shirt-parent-a',
                    ]);
                }

                static::assertSame(['green-l', 'blue-m'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createProductSearchResult($criteria, ['green-l', 'blue-m']);
            });

        $loader = $this->createLoader();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('id', ['green-l']));

        $result = $loader->load($criteria, $this->salesChannelContext);

        static::assertSame(['green-l', 'blue-m'], array_values($result->getIds()));
        static::assertSame(2, $result->getTotal());
    }

    public function testLoadKeepsExplicitProductIdsAfterPreviewResolution(): void
    {
        $resolvePreviewEventSeen = false;

        $this->systemConfigService
            ->expects($this->exactly(2))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);

                return match ($key) {
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
                    'explicit-id' => ['score' => 10.0],
                    'other-id' => ['score' => 5.0],
                ]);
            });

        $this->productRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function (LoadPreviewExtension $extension): void {
                $extension->result = [
                    'explicit-id' => 'main-variant',
                    'other-id' => 'remapped-id',
                ];
                $extension->stopPropagation();
            }
        );

        $this->eventDispatcher->addListener(ProductListingResolvePreviewEvent::class, static function (ProductListingResolvePreviewEvent $event) use (&$resolvePreviewEventSeen): void {
            $resolvePreviewEventSeen = true;
            static::assertFalse($event->hasOptionFilter());
            static::assertSame([
                'explicit-id' => 'explicit-id',
                'other-id' => 'remapped-id',
            ], $event->getMapping());
        });

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertSame(['explicit-id', 'remapped-id'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createProductSearchResult($criteria, ['explicit-id', 'remapped-id']);
            });

        $loader = $this->createLoader();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.id', 'explicit-id'));

        $result = $loader->load($criteria, $this->salesChannelContext);

        static::assertTrue($resolvePreviewEventSeen);
        static::assertSame(['explicit-id', 'remapped-id'], array_values($result->getIds()));
    }

    public function testLoadStillLoadsPreviewOnSearchRouteWithOptionPostFilter(): void
    {
        $previewLoaded = false;
        $resolvePreviewEventSeen = false;

        $this->systemConfigService
            ->expects($this->exactly(3))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);

                return match ($key) {
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

        $this->eventDispatcher->addListener(ProductListingResolvePreviewEvent::class, static function (ProductListingResolvePreviewEvent $event) use (&$resolvePreviewEventSeen): void {
            $resolvePreviewEventSeen = true;
            static::assertTrue($event->hasOptionFilter());
            static::assertSame(['variant-id' => 'preview-id'], $event->getMapping());
        });

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertSame(['preview-id'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createProductSearchResult($criteria, ['preview-id']);
            });

        $loader = $this->createLoader();

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaProductSearchRoute::STATE);
        $criteria->addPostFilter(new EqualsFilter('product.options.id', 'option-id'));

        $result = $loader->load($criteria, $this->salesChannelContext);

        static::assertTrue($previewLoaded);
        static::assertTrue($resolvePreviewEventSeen);
        static::assertSame(['preview-id'], array_values($result->getIds()));
    }

    public function testLoadSkipsPreviewOnSearchRouteWhenFindBestVariantIsEnabled(): void
    {
        $previewLoaded = false;

        $this->systemConfigService
            ->expects($this->exactly(3))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);

                return match ($key) {
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

        $loader = $this->createLoader();

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaProductSearchRoute::STATE);

        $result = $loader->load($criteria, $this->salesChannelContext);

        static::assertFalse($previewLoaded);
        static::assertSame(['variant-id'], array_values($result->getIds()));
    }

    public function testLoadDoesNotLookupDisplayGroupsWhenNoExplicitProductIdsAreConfigured(): void
    {
        $this->systemConfigService
            ->expects($this->exactly(2))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $salesChannelId): bool {
                static::assertSame($this->salesChannelContext->getSalesChannelId(), $salesChannelId);

                return match ($key) {
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
            static function (LoadPreviewExtension $extension): void {
                $extension->result = array_combine($extension->ids, $extension->ids);
                $extension->stopPropagation();
            }
        );

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertNotSame(['id', 'displayGroup'], $criteria->getFields());
                static::assertSame(['variant-id'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createProductSearchResult($criteria, ['variant-id']);
            });

        $loader = $this->createLoader();

        $result = $loader->load(new Criteria(), $this->salesChannelContext);

        static::assertSame(['variant-id'], array_values($result->getIds()));
    }

    private function createLoader(): ProductListingLoader
    {
        return new ProductListingLoader(
            $this->productRepository,
            $this->systemConfigService,
            $this->connection,
            $this->eventDispatcher,
            $this->productCloseoutFilterFactory,
            new ExtensionDispatcher($this->eventDispatcher),
            new ExplicitProductListingIdMerger(
                $this->productRepository,
                $this->systemConfigService,
                $this->productCloseoutFilterFactory
            )
        );
    }

    /**
     * @param array<string, array<string, mixed>> $ids
     */
    private function createIdSearchResult(Criteria $criteria, array $ids): IdSearchResult
    {
        $data = [];
        foreach ($ids as $id => $row) {
            $data[$id] = [
                'primaryKey' => $id,
                'data' => $row,
            ];
        }

        return new IdSearchResult(\count($data), $data, $criteria, $this->salesChannelContext->getContext());
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
     * @param array<string, string> $displayGroups
     *
     * @return EntitySearchResult<EntityCollection<PartialEntity>>
     */
    private function createDisplayGroupSearchResult(array $displayGroups): EntitySearchResult
    {
        /** @var EntityCollection<PartialEntity> $products */
        $products = new EntityCollection();
        foreach ($displayGroups as $id => $displayGroup) {
            $products->add((new PartialEntity())->assign([
                'id' => $id,
                'displayGroup' => $displayGroup,
            ]));
        }

        return new EntitySearchResult('product', $products->count(), $products, null, new Criteria(), $this->salesChannelContext->getContext());
    }
}
