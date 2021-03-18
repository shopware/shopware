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
    private $context;

    public function setUp(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('DE');

        $this->context = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $this->context->method('getSalesChannel')->willReturn($salesChannel);
    }

    /**
     * This test verifies, that we get the
     * expected and defined criteria from the template.
     *
     * @group promotions
     */
    public function testCriteria(): void
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

        $todayStart = $today->format('Y-m-d H:i:s');
        $todayEnd = $today->format('Y-m-d H:i:s');

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
                new RangeFilter('validUntil', ['gt' => $todayEnd]),
            ]
        );

        $activeDateRangeFilter = new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new RangeFilter('validFrom', ['lte' => $todayStart]),
                new RangeFilter('validUntil', ['gt' => $todayEnd]),
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
