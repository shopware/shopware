<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\DiscountCampaignStruct;
use Shopware\Core\Framework\Store\Struct\VariantStruct;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(VariantStruct::class)]
class VariantStructTest extends TestCase
{
    public function testFromArrayBuildsTheNestedDiscountCampaign(): void
    {
        $variant = VariantStruct::fromArray([
            'id' => 7,
            'type' => VariantStruct::TYPE_RENT,
            'netPrice' => 12.0,
            'netPricePerMonth' => 1.0,
            'duration' => VariantStruct::RENT_DURATION_YEARLY,
            'discountCampaign' => ['name' => 'Summer Sale', 'discount' => 25.0],
        ]);

        static::assertInstanceOf(VariantStruct::class, $variant);
        static::assertSame(7, $variant->getId());
        static::assertSame(VariantStruct::TYPE_RENT, $variant->getType());
        static::assertSame(12.0, $variant->getNetPrice());
        static::assertSame(1.0, $variant->getNetPricePerMonth());
        static::assertSame(VariantStruct::RENT_DURATION_YEARLY, $variant->getDuration());
        static::assertSame('Summer Sale', $variant->getDiscountCampaign()?->getName());
        static::assertFalse($variant->isTrialPhaseIncluded());
    }

    public function testAccessorsRoundTrip(): void
    {
        $variant = new VariantStruct();

        $campaign = new DiscountCampaignStruct();

        $variant->setTrialPhaseIncluded(true);
        $variant->setDiscountCampaign($campaign);

        static::assertTrue($variant->isTrialPhaseIncluded());
        static::assertSame($campaign, $variant->getDiscountCampaign());
    }
}
