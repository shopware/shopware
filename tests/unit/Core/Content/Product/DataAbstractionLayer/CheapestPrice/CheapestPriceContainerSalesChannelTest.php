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

    /**
     * Regression for #16239: the cheapest variant must not be a hidden
     * closeout variant that is out of stock when the
     * `hideCloseoutProductsWhenOutOfStock` setting is active, otherwise
     * the listing shows a "from" price that the customer can never buy.
     */
    public function testResolveHidesCloseoutOutOfStockVariantFromAggregation(): void
    {
        $currentSalesChannelId = Uuid::randomHex();

        $testData = [
            // Cheapest, but hidden closeout + out of stock -> must be skipped
            'variant1' => [
                'default' => [
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 50.0, 'net' => 42.02, 'linked' => true],
                    ],
                    'sales_channel_ids' => [$currentSalesChannelId],
                    'is_ranged' => false,
                    'rule_id' => 'default',
                    'parent_id' => 'parent1',
                    'purchase_unit' => 1.0,
                    'reference_unit' => 1.0,
                    'is_closeout' => true,
                    'available' => false,
                ],
            ],
            // Next cheapest available variant -> should be picked
            'variant2' => [
                'default' => [
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 100.0, 'net' => 84.03, 'linked' => true],
                    ],
                    'sales_channel_ids' => [$currentSalesChannelId],
                    'is_ranged' => false,
                    'rule_id' => 'default',
                    'parent_id' => 'parent1',
                    'purchase_unit' => 1.0,
                    'reference_unit' => 1.0,
                    'is_closeout' => true,
                    'available' => true,
                ],
            ],
        ];

        $context = $this->createSalesChannelContext($currentSalesChannelId);

        $container = new CheapestPriceContainer($testData);

        // Default behaviour (flag off) keeps legacy output and picks the cheapest row.
        $cheapestWithoutFlag = $container->resolve($context);
        static::assertNotNull($cheapestWithoutFlag);
        static::assertSame('variant1', $cheapestWithoutFlag->getVariantId());
        $firstPriceNoFlag = $cheapestWithoutFlag->getPrice()->first();
        static::assertNotNull($firstPriceNoFlag);
        static::assertSame(50.0, $firstPriceNoFlag->getGross());

        // With the flag enabled, the out-of-stock closeout variant is excluded.
        $cheapest = $container->resolveExcludingHiddenCloseoutVariants($context);
        static::assertNotNull($cheapest);
        static::assertSame('variant2', $cheapest->getVariantId());

        $firstPrice = $cheapest->getPrice()->first();
        static::assertNotNull($firstPrice);
        static::assertSame(100.0, $firstPrice->getGross());
    }

    public function testResolveHideCloseoutFlagKeepsAvailableCloseoutVariant(): void
    {
        $currentSalesChannelId = Uuid::randomHex();

        $testData = [
            'variant1' => [
                'default' => [
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 50.0, 'net' => 42.02, 'linked' => true],
                    ],
                    'sales_channel_ids' => [$currentSalesChannelId],
                    'is_ranged' => false,
                    'rule_id' => 'default',
                    'parent_id' => 'parent1',
                    'purchase_unit' => 1.0,
                    'reference_unit' => 1.0,
                    // closeout, but still available -> must remain in the aggregation
                    'is_closeout' => true,
                    'available' => true,
                ],
            ],
        ];

        $context = $this->createSalesChannelContext($currentSalesChannelId);

        $container = new CheapestPriceContainer($testData);
        $cheapest = $container->resolveExcludingHiddenCloseoutVariants($context);

        static::assertNotNull($cheapest);
        static::assertSame('variant1', $cheapest->getVariantId());
    }

    /**
     * Backwards compatibility: price rows serialized before the availability fix
     * (#16239) were persisted without the `is_closeout` / `available` keys. They
     * must still resolve with the hide-closeout flag enabled — i.e. fall back to
     * the previous behavior instead of being silently dropped.
     */
    public function testResolveHideCloseoutFlagFallsBackForLegacySerializedRowsWithoutFlags(): void
    {
        $currentSalesChannelId = Uuid::randomHex();

        $testData = [
            'variant1' => [
                'default' => [
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 50.0, 'net' => 42.02, 'linked' => true],
                    ],
                    'sales_channel_ids' => [$currentSalesChannelId],
                    'is_ranged' => false,
                    'rule_id' => 'default',
                    'parent_id' => 'parent1',
                    'purchase_unit' => 1.0,
                    'reference_unit' => 1.0,
                    // No is_closeout / available — shape predating #16239.
                ],
            ],
        ];

        $context = $this->createSalesChannelContext($currentSalesChannelId);

        $cheapest = (new CheapestPriceContainer($testData))->resolveExcludingHiddenCloseoutVariants($context);

        static::assertNotNull($cheapest, 'Pre-#16239 serialized rows must still resolve with the hide-closeout flag on');
        static::assertSame('variant1', $cheapest->getVariantId());
    }

    public function testResolveHideCloseoutFlagReturnsNullWhenEveryVariantHidden(): void
    {
        $currentSalesChannelId = Uuid::randomHex();

        $testData = [
            'variant1' => [
                'default' => [
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 50.0, 'net' => 42.02, 'linked' => true],
                    ],
                    'sales_channel_ids' => [$currentSalesChannelId],
                    'is_ranged' => false,
                    'rule_id' => 'default',
                    'parent_id' => 'parent1',
                    'purchase_unit' => 1.0,
                    'reference_unit' => 1.0,
                    'is_closeout' => true,
                    'available' => false,
                ],
            ],
        ];

        $context = $this->createSalesChannelContext($currentSalesChannelId);

        $container = new CheapestPriceContainer($testData);

        static::assertNull($container->resolveExcludingHiddenCloseoutVariants($context));
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
