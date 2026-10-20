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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\ProductAssignment\SalesChannelProductAssigner;
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
     * @var StaticEntityRepository<ProductVisibilityCollection>
     */
    private StaticEntityRepository $visibilityRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->visibilityRepository = StaticEntityRepository::of(ProductVisibilityCollection::class);
    }

    public function testAssignsEveryProductOnceAcrossAllSources(): void
    {
        $ids = $this->ids(4);
        /** @var list<Criteria> $searchedCriteria */
        $searchedCriteria = [];
        $remember = static function (array $result) use (&$searchedCriteria): callable {
            return static function (Criteria $criteria) use (&$searchedCriteria, $result): array {
                $searchedCriteria[] = clone $criteria;

                return $result;
            };
        };

        $productRepository = $this->productRepository([
            $remember([$ids[0], $ids[1]]), [],
            $remember([$ids[1], $ids[2]]), [],
            $remember([$ids[2], $ids[3]]), [],
        ]);

        $assigner = $this->assigner($productRepository, $this->streamBuilderAdding(new EqualsFilter('active', true)));

        $assigned = $assigner->assign(
            self::SALES_CHANNEL_ID,
            productIds: [$ids[0], $ids[1]],
            productStreamIds: ['stream-id'],
            categoryIds: ['category-id'],
            visibility: ProductVisibilityDefinition::VISIBILITY_ALL,
            context: $this->context,
        );

        static::assertSame(4, $assigned);
        static::assertSame(
            array_map(static fn (string $id) => [
                'productId' => $id,
                'salesChannelId' => self::SALES_CHANNEL_ID,
                'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
            ], $ids),
            $this->visibilityRepository->getPayloads(StaticEntityRepository::CREATE),
        );

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

    public function testWritesVisibilitiesInChunksOfFiveHundred(): void
    {
        $ids = $this->ids(1200);

        $productRepository = $this->productRepository([
            \array_slice($ids, 0, 500),
            \array_slice($ids, 500, 500),
            \array_slice($ids, 1000),
            [],
        ]);

        $assigned = $this->assigner($productRepository)->assign(
            self::SALES_CHANNEL_ID,
            productIds: $ids,
            productStreamIds: [],
            categoryIds: [],
            visibility: ProductVisibilityDefinition::VISIBILITY_SEARCH,
            context: $this->context,
        );

        static::assertSame(1200, $assigned);
        static::assertCount(3, $this->visibilityRepository->creates);
        static::assertCount(500, $this->visibilityRepository->creates[0]);
        static::assertCount(500, $this->visibilityRepository->creates[1]);
        static::assertCount(200, $this->visibilityRepository->creates[2]);
        static::assertSame(
            ProductVisibilityDefinition::VISIBILITY_SEARCH,
            $this->visibilityRepository->getPayloads(StaticEntityRepository::CREATE)[0]['visibility'],
        );
    }

    public function testWritesNothingWhenNoProductIsLeftToAssign(): void
    {
        $productRepository = $this->productRepository([[]]);

        $assigned = $this->assigner($productRepository)->assign(
            self::SALES_CHANNEL_ID,
            productIds: [Uuid::randomHex()],
            productStreamIds: [],
            categoryIds: [],
            visibility: ProductVisibilityDefinition::VISIBILITY_ALL,
            context: $this->context,
        );

        static::assertSame(0, $assigned);
        static::assertSame([], $this->visibilityRepository->creates);
    }

    public function testDoesNotTouchTheDatabaseWithoutSources(): void
    {
        $assigner = new SalesChannelProductAssigner(
            $this->productRepository([]),
            $this->visibilityRepository,
            StaticEntityRepository::of(SalesChannelCollection::class),
            $this->streamBuilderAdding(new EqualsFilter('active', true)),
        );

        $assigned = $assigner->assign(
            self::SALES_CHANNEL_ID,
            productIds: [],
            productStreamIds: [],
            categoryIds: [],
            visibility: ProductVisibilityDefinition::VISIBILITY_ALL,
            context: $this->context,
        );

        static::assertSame(0, $assigned);
        static::assertSame([], $this->visibilityRepository->creates);
    }

    public function testThrowsWhenTheSalesChannelDoesNotExist(): void
    {
        $assigner = new SalesChannelProductAssigner(
            $this->productRepository([]),
            $this->visibilityRepository,
            StaticEntityRepository::of(SalesChannelCollection::class, [[]]),
            $this->streamBuilderAdding(new EqualsFilter('active', true)),
        );

        $this->expectExceptionObject(SalesChannelException::salesChannelNotFound(self::SALES_CHANNEL_ID));

        $assigner->assign(
            self::SALES_CHANNEL_ID,
            productIds: [Uuid::randomHex()],
            productStreamIds: [],
            categoryIds: [],
            visibility: ProductVisibilityDefinition::VISIBILITY_ALL,
            context: $this->context,
        );
    }

    /**
     * @return list<string>
     */
    private function ids(int $count): array
    {
        $ids = [];
        for ($i = 0; $i < $count; ++$i) {
            $ids[] = Uuid::randomHex();
        }

        return $ids;
    }

    /**
     * @param array<mixed> $searches
     *
     * @return StaticEntityRepository<ProductCollection>
     */
    private function productRepository(array $searches): StaticEntityRepository
    {
        return StaticEntityRepository::of(ProductCollection::class, $searches, new ProductDefinition());
    }

    /**
     * @param StaticEntityRepository<ProductCollection> $productRepository
     */
    private function assigner(
        StaticEntityRepository $productRepository,
        ?AbstractProductStreamBuilder $streamBuilder = null,
    ): SalesChannelProductAssigner {
        return new SalesChannelProductAssigner(
            $productRepository,
            $this->visibilityRepository,
            StaticEntityRepository::of(SalesChannelCollection::class, [[self::SALES_CHANNEL_ID]]),
            $streamBuilder ?? $this->streamBuilderAdding(new EqualsFilter('active', true)),
        );
    }

    private function streamBuilderAdding(Filter $filter): AbstractProductStreamBuilder
    {
        $builder = $this->createMock(AbstractProductStreamBuilder::class);
        $builder->method('enrichCriteria')->willReturnCallback(
            static function (Criteria $criteria) use ($filter): void {
                $criteria->addFilter($filter);
            }
        );

        return $builder;
    }
}
