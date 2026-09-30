<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\DiscountCampaignStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DiscountCampaignStruct::class)]
class DiscountCampaignStructTest extends TestCase
{
    public function testFromArrayConvertsTheDateStrings(): void
    {
        $campaign = DiscountCampaignStruct::fromArray([
            'name' => 'Summer Sale',
            'discount' => 25.0,
            'startDate' => '2026-06-01 00:00:00',
            'endDate' => '2026-06-30 00:00:00',
        ]);

        static::assertInstanceOf(DiscountCampaignStruct::class, $campaign);
        static::assertSame('Summer Sale', $campaign->getName());
        static::assertSame(25.0, $campaign->getDiscount());
        static::assertSame('2026-06-01', $campaign->getStartDate()->format('Y-m-d'));
        static::assertSame('2026-06-30', $campaign->getEndDate()->format('Y-m-d'));
    }

    public function testAccessorsRoundTrip(): void
    {
        $campaign = new DiscountCampaignStruct();

        $start = new \DateTimeImmutable('2026-06-01');
        $end = new \DateTimeImmutable('2026-06-30');

        $campaign->setName('Summer Sale');
        $campaign->setStartDate($start);
        $campaign->setEndDate($end);
        $campaign->setDiscount(25.0);
        $campaign->setDiscountedPrice(14.25);
        $campaign->setDiscountedPricePerMonth(1.19);
        $campaign->setDiscountAppliesForMonths(3);

        static::assertSame('Summer Sale', $campaign->getName());
        static::assertSame($start, $campaign->getStartDate());
        static::assertSame($end, $campaign->getEndDate());
        static::assertSame(25.0, $campaign->getDiscount());
        static::assertSame(14.25, $campaign->getDiscountedPrice());
        static::assertSame(1.19, $campaign->getDiscountedPricePerMonth());
        static::assertSame(3, $campaign->getDiscountAppliesForMonths());
    }
}
