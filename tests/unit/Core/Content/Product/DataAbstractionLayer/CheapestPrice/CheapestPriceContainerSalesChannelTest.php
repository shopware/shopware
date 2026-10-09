<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CheapestPriceContainer::class)]
class CheapestPriceContainerSalesChannelTest extends TestCase
{
    public function testResolveReturnsPriceWithoutStoredSalesChannelIds(): void
    {
        $container = new CheapestPriceContainer([
            'variant1' => [
                'default' => $this->createPrice(100.0, 84.03),
            ],
        ]);

        $cheapestPrice = $container->resolve($this->createSalesChannelContext(Uuid::randomHex()));

        static::assertNotNull($cheapestPrice);
        static::assertSame('variant1', $cheapestPrice->getVariantId());

        $firstPrice = $cheapestPrice->getPrice()->first();
        static::assertNotNull($firstPrice);
        static::assertSame(100.0, $firstPrice->getGross());
    }

    public function testResolveWithSalesChannelFiltering(): void
    {
        $currentSalesChannelId = Uuid::randomHex();
        $otherSalesChannelId = Uuid::randomHex();

        $testData = [
            'variant1' => [
                'default' => $this->createPrice(50.0, 42.02, [$otherSalesChannelId]),
            ],
            'variant2' => [
                'default' => $this->createPrice(100.0, 84.03, [$currentSalesChannelId]),
            ],
        ];

        $container = new CheapestPriceContainer($testData);
        $cheapestPrice = $container->resolve($this->createSalesChannelContext($currentSalesChannelId));

        static::assertNotNull($cheapestPrice);
        static::assertSame('variant2', $cheapestPrice->getVariantId());

        $firstPrice = $cheapestPrice->getPrice()->first();
        static::assertNotNull($firstPrice);
        static::assertSame(100.0, $firstPrice->getGross());
    }

    public function testResolveWithNoMatchingSalesChannel(): void
    {
        $currentSalesChannelId = Uuid::randomHex();
        $otherSalesChannelId = Uuid::randomHex();

        $testData = [
            'variant1' => [
                'default' => $this->createPrice(50.0, 42.02, [$otherSalesChannelId]),
            ],
        ];

        $container = new CheapestPriceContainer($testData);
        $cheapestPrice = $container->resolve($this->createSalesChannelContext($currentSalesChannelId));

        static::assertNull($cheapestPrice);
    }

    /**
     * @param list<string>|null $salesChannelIds null omits the `sales_channel_ids` key entirely,
     *                                           which marks the price as available everywhere
     *
     * @return array<string, mixed>
     */
    private function createPrice(float $gross, float $net, ?array $salesChannelIds = null): array
    {
        $price = [
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => $gross, 'net' => $net, 'linked' => true],
            ],
            'is_ranged' => false,
            'rule_id' => 'default',
            'parent_id' => 'parent1',
            'purchase_unit' => 1.0,
            'reference_unit' => 1.0,
        ];

        if ($salesChannelIds !== null) {
            $price['sales_channel_ids'] = $salesChannelIds;
        }

        return $price;
    }

    private function createSalesChannelContext(string $salesChannelId): Context
    {
        return new Context(
            new SalesChannelApiSource($salesChannelId),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM],
            Defaults::LIVE_VERSION,
            1.0,
            true,
            CartPrice::TAX_STATE_GROSS
        );
    }
}
