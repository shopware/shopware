<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Gateway\Template;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Gateway\Template\ActiveDateRange;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class ActiveDateRangeTest extends TestCase
{
    /**
     * @var SalesChannelContext
     */
    private $context = null;

    public function setUp(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('DE');

        $this->context = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $this->context->expects(static::any())->method('getSalesChannel')->willReturn($salesChannel);
    }

    /**
     * This test verifies, that we get the
     * expected and defined criteria from the template.
     *
     * @test
     * @group promotions
     */
    public function testCriteria()
    {
        $template = new ActiveDateRange();

        static::assertEquals($this->getExpectedDateRangeFilter($this->context)->getQueries(), $template->getQueries());
    }

    /**
     * @throws \Exception
     */
    public function getExpectedDateRangeFilter(SalesChannelContext $context): MultiFilter
    {
        $today = new \DateTime();
        $today = $today->setTimezone(new \DateTimeZone('UTC'));

        $todayStart = $today->format('Y-m-d 0:0:0');
        $todayEnd = $today->format('Y-m-d 23:59:59');

        $filterNoDateRange = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('validFrom', null),
                new EqualsFilter('validUntil', null),
            ]
        );

        $filterStartedNoEndDate = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new RangeFilter('validFrom', ['lte' => $todayStart]),
                new EqualsFilter('validUntil', null),
            ]
        );

        $filterActiveNoStartDate = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('validFrom', null),
                new RangeFilter('validUntil', ['gte' => $todayEnd]),
            ]
        );

        $activeDateRangeFilter = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new RangeFilter('validFrom', ['lte' => $todayStart]),
                new RangeFilter('validUntil', ['gte' => $todayEnd]),
            ]
        );

        $dateFilter = new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                $filterNoDateRange,
                $filterActiveNoStartDate,
                $filterStartedNoEndDate,
                $activeDateRangeFilter,
            ]
        );

        return $dateFilter;
    }
}
