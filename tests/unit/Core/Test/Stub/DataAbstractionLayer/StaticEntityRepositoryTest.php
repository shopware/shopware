<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Stub\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class StaticEntityRepositoryTest extends TestCase
{
    #[TestDox('records the criteria of every search and id search in call order')]
    public function testRecordsSearchedCriteriaInCallOrder(): void
    {
        $context = Context::createDefaultContext();
        $repository = StaticEntityRepository::of(ProductCollection::class, [new ProductCollection(), []]);
        $search = (new Criteria())->addFilter(new EqualsFilter('active', true));
        $searchIds = (new Criteria())->setLimit(5);

        $repository->search($search, $context);
        $repository->searchIds($searchIds, $context);

        static::assertEquals([$search, $searchIds], $repository->searchedCriteria);
    }

    #[TestDox('keeps the criteria as they were at call time when the caller changes them afterwards')]
    public function testRecordedCriteriaIgnoreLaterChanges(): void
    {
        $repository = StaticEntityRepository::of(ProductCollection::class, [[]]);
        $criteria = (new Criteria())->setOffset(0);

        $repository->searchIds($criteria, Context::createDefaultContext());
        $criteria->setOffset(50);

        static::assertSame(0, $repository->searchedCriteria[0]->getOffset());
    }

    #[TestDox('answers aggregate() from the queued searches and records its criteria')]
    public function testAggregateTakesTheNextQueuedResult(): void
    {
        $aggregations = new AggregationResultCollection([new CountResult('count', 3)]);
        $repository = StaticEntityRepository::of(ProductCollection::class, [
            $aggregations,
            static fn (Criteria $criteria): AggregationResultCollection => new AggregationResultCollection([new CountResult('count', $criteria->getLimit() ?? 0)]),
        ]);
        $criteria = (new Criteria())->setLimit(7);

        static::assertSame($aggregations, $repository->aggregate(new Criteria(), Context::createDefaultContext()));
        static::assertEquals(new AggregationResultCollection([new CountResult('count', 7)]), $repository->aggregate($criteria, Context::createDefaultContext()));
        static::assertCount(2, $repository->searchedCriteria);
    }

    #[TestDox('fails aggregate() when no aggregation result is queued')]
    public function testAggregateFailsWithoutAQueuedResult(): void
    {
        $repository = StaticEntityRepository::of(ProductCollection::class);

        $this->expectExceptionObject(new \RuntimeException('Invalid mock repository configuration'));

        $repository->aggregate(new Criteria(), Context::createDefaultContext());
    }
}
