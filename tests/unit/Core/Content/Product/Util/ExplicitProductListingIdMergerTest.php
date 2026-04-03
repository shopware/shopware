<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\Util\ExplicitProductListingIdMerger;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[CoversClass(ExplicitProductListingIdMerger::class)]
class ExplicitProductListingIdMergerTest extends TestCase
{
    /**
     * @var MockObject&SalesChannelRepository<EntityCollection>
     */
    private MockObject&SalesChannelRepository $productRepository;

    private MockObject&SystemConfigService $systemConfigService;

    private MockObject&AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory;

    private Context $context;

    private MockObject&SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->productCloseoutFilterFactory = $this->createMock(AbstractProductCloseoutFilterFactory::class);
        $this->context = Context::createDefaultContext();

        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->salesChannelContext->method('getContext')->willReturn($this->context);
        $this->salesChannelContext->method('getSalesChannelId')->willReturn('sales-channel-id');
    }

    public function testMergeReturnsGroupedResultWhenNoExplicitProductIdsAreMissing(): void
    {
        $groupedCriteria = new Criteria();
        $groupedResult = $this->createIdSearchResult($groupedCriteria, [
            'green-l' => ['displayGroup' => 'shirt-parent-a'],
            'blue-m' => ['displayGroup' => 'shirt-parent-b'],
        ]);

        $this->productRepository->expects($this->never())->method('searchIds');
        $this->productRepository->expects($this->never())->method('search');

        $merger = $this->createMerger();

        $result = $merger->merge(
            $groupedResult,
            $groupedCriteria,
            new Criteria(),
            ['green-l'],
            $this->salesChannelContext
        );

        static::assertSame($groupedResult, $result);
    }

    public function testMergeReturnsCurrentPageWhenMissingExplicitIdsOnlyExistOnAnotherPage(): void
    {
        $groupedCriteria = new Criteria();

        $currentPageResult = $this->createIdSearchResult($groupedCriteria, [
            'blue-m' => ['displayGroup' => 'shirt-parent-b'],
        ], 2);

        $fullGroupedResult = $this->createIdSearchResult($groupedCriteria, [
            'blue-m' => ['displayGroup' => 'shirt-parent-b'],
            'green-l' => ['displayGroup' => 'shirt-parent-a'],
        ], 2);

        $originalCriteria = new Criteria();
        $originalCriteria->setLimit(1);

        $this->productRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria, SalesChannelContext $context) use ($fullGroupedResult): IdSearchResult {
                static::assertSame($this->salesChannelContext, $context);
                static::assertNull($criteria->getOffset());
                static::assertNull($criteria->getLimit());

                return $fullGroupedResult;
            });

        $this->productRepository->expects($this->never())->method('search');

        $merger = $this->createMerger();

        $result = $merger->merge(
            $currentPageResult,
            $groupedCriteria,
            $originalCriteria,
            ['green-l'],
            $this->salesChannelContext
        );

        static::assertSame($currentPageResult, $result);
    }

    public function testMergeReturnsGroupedResultWhenNoExplicitIdsMatchTheOriginalCriteria(): void
    {
        $groupedCriteria = new Criteria();
        $originalCriteria = new Criteria();
        $groupedResult = $this->createIdSearchResult($groupedCriteria, [
            'blue-m' => ['displayGroup' => 'shirt-parent-b'],
        ]);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->with('core.listing.hideCloseoutProductsWhenOutOfStock', 'sales-channel-id')
            ->willReturn(false);

        $this->productRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria, SalesChannelContext $context): IdSearchResult {
                static::assertSame($this->salesChannelContext, $context);
                static::assertSame(['green-l'], $criteria->getIds());
                static::assertNull($criteria->getOffset());
                static::assertNull($criteria->getLimit());

                return IdSearchResult::fromIds([], $criteria, $this->context);
            });

        $this->productRepository->expects($this->never())->method('search');

        $merger = $this->createMerger();

        $result = $merger->merge(
            $groupedResult,
            $groupedCriteria,
            $originalCriteria,
            ['green-l'],
            $this->salesChannelContext
        );

        static::assertSame($groupedResult, $result);
    }

    public function testMergeAddsCloseoutFilterWhenConfigured(): void
    {
        $groupedCriteria = new Criteria();
        $originalCriteria = new Criteria();
        $groupedResult = $this->createIdSearchResult($groupedCriteria, [
            'blue-m' => ['displayGroup' => 'shirt-parent-b'],
        ]);

        $closeoutFilter = new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('closeout', true),
        ]);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->with('core.listing.hideCloseoutProductsWhenOutOfStock', 'sales-channel-id')
            ->willReturn(true);

        $this->productCloseoutFilterFactory
            ->expects($this->once())
            ->method('create')
            ->with($this->salesChannelContext)
            ->willReturn($closeoutFilter);

        $this->productRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                static::assertSame(['green-l'], $criteria->getIds());
                static::assertContainsEquals(
                    new MultiFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('closeout', true)]),
                    $criteria->getFilters()
                );

                return IdSearchResult::fromIds([], $criteria, $this->context);
            });

        $merger = $this->createMerger();

        $merger->merge(
            $groupedResult,
            $groupedCriteria,
            $originalCriteria,
            ['green-l'],
            $this->salesChannelContext
        );
    }

    public function testMergeSupportsPartialEntitiesWhenLoadingDisplayGroups(): void
    {
        $groupedCriteria = new Criteria();
        $originalCriteria = new Criteria();

        $groupedResult = $this->createIdSearchResult($groupedCriteria, [
            'red-l' => ['score' => 10.0],
            'blue-m' => ['score' => 5.0],
        ]);
        $groupedResult->addState('grouped');
        $groupedResult->addExtension('grouped-extension', new ArrayStruct(['value' => 'grouped']));

        $matchingExplicitResult = $this->createIdSearchResult($originalCriteria, [
            'green-l' => ['score' => 8.0],
            'green-xl' => ['score' => 7.0],
        ]);
        $matchingExplicitResult->addState('explicit');
        $matchingExplicitResult->addExtension('explicit-extension', new ArrayStruct(['value' => 'explicit']));

        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->with('core.listing.hideCloseoutProductsWhenOutOfStock', 'sales-channel-id')
            ->willReturn(false);

        $this->productRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturn($matchingExplicitResult);

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria, SalesChannelContext $context): EntitySearchResult {
                static::assertSame($this->salesChannelContext, $context);
                static::assertSame(['id', 'displayGroup'], $criteria->getFields());
                static::assertSame(['red-l', 'blue-m', 'green-l', 'green-xl'], $criteria->getIds());

                $products = new EntityCollection([
                    (new PartialEntity())->assign(['id' => 'red-l', 'displayGroup' => 'shirt-parent-a']),
                    (new PartialEntity())->assign(['id' => 'blue-m', 'displayGroup' => 'shirt-parent-b']),
                    (new PartialEntity())->assign(['id' => 'green-l', 'displayGroup' => 'shirt-parent-a']),
                    (new PartialEntity())->assign(['id' => 'green-xl', 'displayGroup' => 'shirt-parent-a']),
                ]);

                return new EntitySearchResult('product', $products->count(), $products, null, $criteria, $this->context);
            });

        $merger = $this->createMerger();

        $result = $merger->merge(
            $groupedResult,
            $groupedCriteria,
            $originalCriteria,
            ['green-l', 'green-xl'],
            $this->salesChannelContext
        );

        static::assertSame(['green-l', 'green-xl', 'blue-m'], $result->getIds());
        static::assertSame(3, $result->getTotal());
        static::assertSame(['grouped', 'explicit'], $result->getStates());
        static::assertTrue($result->hasExtension('grouped-extension'));
        static::assertTrue($result->hasExtension('explicit-extension'));
    }

    public function testMergeDoesNotDuplicateExplicitRepresentativeAndKeepsExplicitSortOrder(): void
    {
        $groupedCriteria = new Criteria();
        $originalCriteria = new Criteria();

        $groupedResult = $this->createIdSearchResult($groupedCriteria, [
            'green-xl' => ['score' => 9.0],
            'blue-m' => ['score' => 5.0],
        ]);

        $matchingExplicitResult = $this->createIdSearchResult($originalCriteria, [
            'green-l' => ['score' => 10.0],
            'green-xl' => ['score' => 9.0],
        ]);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->willReturn(false);

        $this->productRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturn($matchingExplicitResult);

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($this->createDisplayGroupSearchResult([
                'green-xl' => 'shirt-parent-a',
                'green-l' => 'shirt-parent-a',
                'blue-m' => 'shirt-parent-b',
            ]));

        $merger = $this->createMerger();

        $result = $merger->merge(
            $groupedResult,
            $groupedCriteria,
            $originalCriteria,
            ['green-l', 'green-xl'],
            $this->salesChannelContext
        );

        static::assertSame(['green-l', 'green-xl', 'blue-m'], $result->getIds());
        static::assertSame(3, $result->getTotal());
    }

    public function testMergePaginatesMergedResultBackToOriginalOffsetAndLimit(): void
    {
        $groupedCriteria = new Criteria();
        $currentPageResult = $this->createIdSearchResult($groupedCriteria, [
            'blue-m' => ['score' => 5.0],
        ], 2);

        $fullGroupedResult = $this->createIdSearchResult($groupedCriteria, [
            'red-l' => ['score' => 10.0],
            'blue-m' => ['score' => 5.0],
        ], 2);

        $originalCriteria = new Criteria();
        $originalCriteria->setOffset(1);
        $originalCriteria->setLimit(1);

        $matchingExplicitResult = $this->createIdSearchResult($originalCriteria, [
            'green-l' => ['score' => 8.0],
            'green-xl' => ['score' => 7.0],
        ]);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->willReturn(false);

        $this->productRepository
            ->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturnOnConsecutiveCalls($fullGroupedResult, $matchingExplicitResult);

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($this->createDisplayGroupSearchResult([
                'red-l' => 'shirt-parent-a',
                'blue-m' => 'shirt-parent-b',
                'green-l' => 'shirt-parent-a',
                'green-xl' => 'shirt-parent-a',
            ]));

        $merger = $this->createMerger();

        $result = $merger->merge(
            $currentPageResult,
            $groupedCriteria,
            $originalCriteria,
            ['green-l', 'green-xl'],
            $this->salesChannelContext
        );

        static::assertSame(['green-xl'], $result->getIds());
        static::assertSame(3, $result->getTotal());
    }

    public function testMergeCanReturnAnEmptyPageAfterPagination(): void
    {
        $groupedCriteria = new Criteria();
        $currentPageResult = $this->createIdSearchResult($groupedCriteria, [], 1);

        $fullGroupedResult = $this->createIdSearchResult($groupedCriteria, [
            'blue-m' => ['score' => 5.0],
        ], 1);

        $originalCriteria = new Criteria();
        $originalCriteria->setOffset(5);
        $originalCriteria->setLimit(2);

        $matchingExplicitResult = $this->createIdSearchResult($originalCriteria, [
            'green-l' => ['score' => 8.0],
        ]);

        $this->systemConfigService
            ->expects($this->once())
            ->method('getBool')
            ->willReturn(false);

        $this->productRepository
            ->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturnOnConsecutiveCalls($fullGroupedResult, $matchingExplicitResult);

        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($this->createDisplayGroupSearchResult([
                'blue-m' => 'shirt-parent-b',
                'green-l' => 'shirt-parent-a',
            ]));

        $merger = $this->createMerger();

        $result = $merger->merge(
            $currentPageResult,
            $groupedCriteria,
            $originalCriteria,
            ['green-l'],
            $this->salesChannelContext
        );

        static::assertSame([], $result->getIds());
        static::assertSame(2, $result->getTotal());
    }

    private function createMerger(): ExplicitProductListingIdMerger
    {
        return new ExplicitProductListingIdMerger(
            $this->productRepository,
            $this->systemConfigService,
            $this->productCloseoutFilterFactory
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

        return new IdSearchResult($total ?? \count($data), $data, $criteria, $this->context);
    }

    /**
     * @param array<string, string> $displayGroups
     *
     * @return EntitySearchResult<EntityCollection>
     */
    private function createDisplayGroupSearchResult(array $displayGroups): EntitySearchResult
    {
        $products = new EntityCollection(array_map(
            static fn (string $id, string $displayGroup): PartialEntity => (new PartialEntity())->assign([
                'id' => $id,
                'displayGroup' => $displayGroup,
            ]),
            array_keys($displayGroups),
            array_values($displayGroups)
        ));

        return new EntitySearchResult('product', $products->count(), $products, null, new Criteria(), $this->context);
    }
}
