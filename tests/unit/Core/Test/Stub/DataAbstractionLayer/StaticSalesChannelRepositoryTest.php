<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Test\Stub\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class StaticSalesChannelRepositoryTest extends TestCase
{
    #[TestDox('answers aggregate() from the queued searches in call order')]
    public function testAggregateTakesTheNextQueuedResult(): void
    {
        $context = Generator::generateSalesChannelContext();
        $aggregations = new AggregationResultCollection([new CountResult('count', 3)]);
        $repository = StaticSalesChannelRepository::of(ProductCollection::class, [[], $aggregations]);

        $repository->searchIds(new Criteria(), $context);

        static::assertSame($aggregations, $repository->aggregate(new Criteria(), $context));
    }

    #[TestDox('fails aggregate() when no aggregation result is queued')]
    public function testAggregateFailsWithoutAQueuedResult(): void
    {
        $repository = StaticSalesChannelRepository::of(ProductCollection::class);

        $this->expectExceptionObject(new \RuntimeException('Invalid mock repository configuration'));

        $repository->aggregate(new Criteria(), Generator::generateSalesChannelContext());
    }
}
