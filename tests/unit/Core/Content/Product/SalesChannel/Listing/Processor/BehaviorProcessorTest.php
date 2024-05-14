<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\BehaviorListingProcessor;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BehaviorListingProcessor::class)]
class BehaviorProcessorTest extends TestCase
{
    public function testPrepareWithNoAggregations(): void
    {
        $request = new Request(['no-aggregations' => true]);
        $criteria = new Criteria();
        $context = $this->createMock(SalesChannelContext::class);

        (new BehaviorListingProcessor())->prepare($request, $criteria, $context);

        static::assertEmpty($criteria->getAggregations());
    }

    public function testPrepareWithOnlyAggregations(): void
    {
        $request = new Request(['only-aggregations' => true]);
        $criteria = new Criteria();
        $context = $this->createMock(SalesChannelContext::class);

        (new BehaviorListingProcessor())->prepare($request, $criteria, $context);

        static::assertSame(0, $criteria->getLimit());
        static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $criteria->getTotalCountMode());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getAssociations());
    }
}
