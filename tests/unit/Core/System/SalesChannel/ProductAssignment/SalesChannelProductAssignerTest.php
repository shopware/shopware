<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\ProductAssignment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityCollection;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\ProductAssignment\Api\SalesChannelProductAssignmentRequest;
use Shopware\Core\System\SalesChannel\ProductAssignment\SalesChannelProductAssigner;
use Shopware\Core\System\SalesChannel\ProductAssignment\SalesChannelProductAssignmentResult;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelProductAssigner::class)]
class SalesChannelProductAssignerTest extends TestCase
{
    private const SALES_CHANNEL_ID = '018f0b4c6d1e7f2a9b3c4d5e6f708192';

    private Context $context;

    /**
     * @var StaticEntityRepository<ProductCollection>
     */
    private StaticEntityRepository $productRepository;

    /**
     * @var StaticEntityRepository<ProductVisibilityCollection>
     */
    private StaticEntityRepository $visibilityRepository;

    /**
     * @var StaticEntityRepository<SalesChannelCollection>
     */
    private StaticEntityRepository $salesChannelRepository;

    private SalesChannelProductAssigner $assigner;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->productRepository = StaticEntityRepository::of(ProductCollection::class, definition: new ProductDefinition());
        $this->visibilityRepository = StaticEntityRepository::of(ProductVisibilityCollection::class);
        $this->salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class);

        $streamBuilder = static::createStub(AbstractProductStreamBuilder::class);
        $streamBuilder->method('enrichCriteria')->willReturnCallback(
            static function (Criteria $criteria): void {
                $criteria->addFilter(new EqualsFilter('active', true));
            }
        );

        $this->assigner = new SalesChannelProductAssigner(
            $this->productRepository,
            $this->visibilityRepository,
            $this->salesChannelRepository,
            $streamBuilder,
        );
    }

    public function testAssignsTheProductsOfEverySource(): void
    {
        $ids = $this->ids(4);

        $this->salesChannelRepository->addSearch([self::SALES_CHANNEL_ID]);
        $this->productRepository->addSearch(
            self::page([$ids[0], $ids[1]]),
            self::page([$ids[2]]),
            self::page([$ids[3]]),
        );

        $result = $this->assigner->assign(
            self::SALES_CHANNEL_ID,
            new SalesChannelProductAssignmentRequest(
                productIds: [$ids[0], $ids[1]],
                productStreamIds: ['stream-id'],
                categoryIds: ['category-id'],
            ),
            $this->context,
        );

        static::assertEquals(new SalesChannelProductAssignmentResult(4, complete: true), $result);
        static::assertCount(3, $this->visibilityRepository->upserts);
        static::assertSame(
            array_map(static fn (string $id) => [
                'id' => Uuid::fromStringToHex($id . self::SALES_CHANNEL_ID),
                'productId' => $id,
                'salesChannelId' => self::SALES_CHANNEL_ID,
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ], $ids),
            $this->visibilityRepository->getPayloads(StaticEntityRepository::UPSERT),
        );

        $searchedCriteria = $this->productRepository->searchedCriteria;
        static::assertCount(3, $searchedCriteria);
        [$byIds, $byStream, $byCategory] = $searchedCriteria;

        static::assertEquals(new EqualsAnyFilter('id', [$ids[0], $ids[1]]), $byIds->getFilters()[0]);
        static::assertEquals(new EqualsFilter('active', true), $byStream->getFilters()[0]);
        static::assertEquals(new EqualsAnyFilter('categoryIds', ['category-id']), $byCategory->getFilters()[0]);
        static::assertEquals(new EqualsFilter('parentId', null), $byCategory->getFilters()[1]);

        foreach ($searchedCriteria as $criteria) {
            static::assertContainsEquals(
                new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('visibilities.salesChannelId', self::SALES_CHANNEL_ID)]),
                $criteria->getFilters(),
            );
        }
    }

    public function testWritesEachPageBeforeFetchingTheNext(): void
    {
        $ids = $this->ids(501);
        $writesBeforeSecondPage = null;

        $this->salesChannelRepository->addSearch([self::SALES_CHANNEL_ID]);
        $this->productRepository->addSearch(
            self::page(\array_slice($ids, 0, 500)),
            function () use (&$writesBeforeSecondPage, $ids): array {
                $writesBeforeSecondPage = \count($this->visibilityRepository->upserts);

                return self::page(\array_slice($ids, 500));
            },
        );

        $result = $this->assigner->assign(
            self::SALES_CHANNEL_ID,
            new SalesChannelProductAssignmentRequest(
                productIds: $ids,
                visibility: ProductVisibilityDefinition::VISIBILITY_SEARCH,
            ),
            $this->context,
        );

        static::assertEquals(new SalesChannelProductAssignmentResult(501, complete: true), $result);
        static::assertSame(1, $writesBeforeSecondPage);
        static::assertCount(2, $this->visibilityRepository->upserts);
        static::assertCount(500, $this->visibilityRepository->upserts[0]);
        static::assertCount(1, $this->visibilityRepository->upserts[1]);
        static::assertSame(
            ProductVisibilityDefinition::VISIBILITY_SEARCH,
            $this->visibilityRepository->getPayloads(StaticEntityRepository::UPSERT)[0]['visibility'],
        );
    }

    public function testStopsAtThePageBudgetAndReportsTheCallAsIncomplete(): void
    {
        $ids = $this->ids(5000);

        // Exactly ten full pages are queued, an eleventh search would hit the empty queue and throw
        $this->salesChannelRepository->addSearch([self::SALES_CHANNEL_ID]);
        $this->productRepository->addSearch(
            ...array_map(static fn (array $page) => self::page($page), array_chunk($ids, 500)),
        );

        $result = $this->assigner->assign(
            self::SALES_CHANNEL_ID,
            new SalesChannelProductAssignmentRequest(
                productStreamIds: ['stream-id'],
                categoryIds: ['category-id'],
            ),
            $this->context,
        );

        static::assertEquals(new SalesChannelProductAssignmentResult(5000, complete: false), $result);
        static::assertCount(10, $this->visibilityRepository->upserts);
        static::assertCount(10, $this->productRepository->searchedCriteria);
    }

    public function testFetchesOnceMoreAfterAFullPageToProveNothingIsLeft(): void
    {
        $ids = $this->ids(500);

        $this->salesChannelRepository->addSearch([self::SALES_CHANNEL_ID]);
        $this->productRepository->addSearch(self::page($ids), []);

        $result = $this->assigner->assign(self::SALES_CHANNEL_ID, new SalesChannelProductAssignmentRequest(productIds: $ids), $this->context);

        static::assertEquals(new SalesChannelProductAssignmentResult(500, complete: true), $result);
        static::assertCount(1, $this->visibilityRepository->upserts);
        static::assertCount(2, $this->productRepository->searchedCriteria);
    }

    public function testQueuesTheIndexingWithoutTouchingTheCallersContext(): void
    {
        $searchContext = null;

        $this->salesChannelRepository->addSearch([self::SALES_CHANNEL_ID]);
        $this->productRepository->addSearch(
            static function (Criteria $criteria, Context $context) use (&$searchContext): array {
                $searchContext = $context;

                return [];
            },
        );

        $this->assigner->assign(self::SALES_CHANNEL_ID, new SalesChannelProductAssignmentRequest(productIds: $this->ids(1)), $this->context);

        static::assertInstanceOf(Context::class, $searchContext);
        static::assertTrue($searchContext->hasState(EntityIndexerRegistry::USE_INDEXING_QUEUE));
        static::assertFalse($this->context->hasState(EntityIndexerRegistry::USE_INDEXING_QUEUE));
    }

    public function testWritesNothingWhenNoProductIsLeftToAssign(): void
    {
        $this->salesChannelRepository->addSearch([self::SALES_CHANNEL_ID]);
        $this->productRepository->addSearch([]);

        $result = $this->assigner->assign(self::SALES_CHANNEL_ID, new SalesChannelProductAssignmentRequest(productIds: $this->ids(1)), $this->context);

        static::assertEquals(new SalesChannelProductAssignmentResult(0, complete: true), $result);
        static::assertSame([], $this->visibilityRepository->upserts);
    }

    public function testDoesNotTouchTheDatabaseWithoutSources(): void
    {
        $result = $this->assigner->assign(self::SALES_CHANNEL_ID, new SalesChannelProductAssignmentRequest(), $this->context);

        static::assertEquals(new SalesChannelProductAssignmentResult(0, complete: true), $result);
        static::assertSame([], $this->visibilityRepository->upserts);
    }

    public function testThrowsWhenTheSalesChannelDoesNotExist(): void
    {
        $this->salesChannelRepository->addSearch([]);

        $this->expectExceptionObject(SalesChannelException::salesChannelNotFound(self::SALES_CHANNEL_ID));

        $this->assigner->assign(self::SALES_CHANNEL_ID, new SalesChannelProductAssignmentRequest(productIds: $this->ids(1)), $this->context);
    }

    /**
     * @return list<string>
     */
    private function ids(int $count): array
    {
        return array_map(static fn (int $i) => Uuid::fromStringToHex('product-' . $i), range(1, $count));
    }

    /**
     * Id search result page carrying the autoIncrement cursor the iterator pages by
     *
     * @param list<string> $ids
     *
     * @return array<string, array{primaryKey: string, data: array{autoIncrement: int}}>
     */
    private static function page(array $ids): array
    {
        $page = [];
        foreach ($ids as $position => $id) {
            $page[$id] = ['primaryKey' => $id, 'data' => ['autoIncrement' => $position + 1]];
        }

        return $page;
    }
}
