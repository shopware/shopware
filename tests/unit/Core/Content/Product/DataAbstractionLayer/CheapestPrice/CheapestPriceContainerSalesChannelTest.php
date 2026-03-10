<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer\CheapestPrice;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(CheapestPriceContainer::class)]
class CheapestPriceContainerSalesChannelTest extends TestCase
{
    public function testIsVariantAvailableInSalesChannelWithMatchingId(): void
    {
        $salesChannelId = Uuid::randomHex();
        $group = [
            'default' => [
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100.0, 'net' => 84.03, 'linked' => true],
                ],
                'sales_channel_ids' => [$salesChannelId],
                'is_ranged' => false,
                'rule_id' => 'default',
                'parent_id' => 'parent1',
                'purchase_unit' => 1.0,
                'reference_unit' => 1.0,
            ],
        ];

        $container = new CheapestPriceContainer([]);
        $reflection = new \ReflectionClass($container);
        $method = $reflection->getMethod('isVariantPriceAvailableInSalesChannel');
        $method->setAccessible(true);

        $price = $group['default'];
        $result = $method->invoke($container, $price, $salesChannelId);

        static::assertTrue($result);
    }

    public function testIsVariantAvailableInSalesChannelWithNonMatchingId(): void
    {
        $salesChannelId = Uuid::randomHex();
        $otherSalesChannelId = Uuid::randomHex();
        $group = [
            'default' => [
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100.0, 'net' => 84.03, 'linked' => true],
                ],
                'sales_channel_ids' => [$otherSalesChannelId],
                'is_ranged' => false,
                'rule_id' => 'default',
                'parent_id' => 'parent1',
                'purchase_unit' => 1.0,
                'reference_unit' => 1.0,
            ],
        ];

        $container = new CheapestPriceContainer([]);
        $reflection = new \ReflectionClass($container);
        $method = $reflection->getMethod('isVariantPriceAvailableInSalesChannel');
        $method->setAccessible(true);

        $price = $group['default'];
        $result = $method->invoke($container, $price, $salesChannelId);

        static::assertFalse($result);
    }

    public function testIsVariantAvailableInSalesChannelWithoutIds(): void
    {
        $salesChannelId = Uuid::randomHex();
        $group = [
            'default' => [
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 100.0, 'net' => 84.03, 'linked' => true],
                ],
                'is_ranged' => false,
                'rule_id' => 'default',
                'parent_id' => 'parent1',
                'purchase_unit' => 1.0,
                'reference_unit' => 1.0,
            ],
        ];

        $container = new CheapestPriceContainer([]);
        $reflection = new \ReflectionClass($container);
        $method = $reflection->getMethod('isVariantPriceAvailableInSalesChannel');
        $method->setAccessible(true);

        $price = $group['default'];
        $result = $method->invoke($container, $price, $salesChannelId);

        static::assertTrue($result);
    }

    public function testResolveWithSalesChannelFiltering(): void
    {
        $currentSalesChannelId = Uuid::randomHex();
        $otherSalesChannelId = Uuid::randomHex();

        $testData = [
            'variant1' => [
                'default' => [
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 50.0, 'net' => 42.02, 'linked' => true],
                    ],
                    'sales_channel_ids' => [$otherSalesChannelId],
                    'is_ranged' => false,
                    'rule_id' => 'default',
                    'parent_id' => 'parent1',
                    'purchase_unit' => 1.0,
                    'reference_unit' => 1.0,
                ],
            ],
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
                ],
            ],
        ];

        $context = new Context(
            new SalesChannelApiSource($currentSalesChannelId),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM],
            Defaults::LIVE_VERSION,
            1.0,
            true,
            CartPrice::TAX_STATE_GROSS
        );

        $container = new CheapestPriceContainer($testData);
        $cheapestPrice = $container->resolve($context);

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
                'default' => [
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 50.0, 'net' => 42.02, 'linked' => true],
                    ],
                    'sales_channel_ids' => [$otherSalesChannelId],
                    'is_ranged' => false,
                    'rule_id' => 'default',
                    'parent_id' => 'parent1',
                    'purchase_unit' => 1.0,
                    'reference_unit' => 1.0,
                ],
            ],
        ];

        $context = new Context(
            new SalesChannelApiSource($currentSalesChannelId),
            [],
            Defaults::CURRENCY,
            [Defaults::LANGUAGE_SYSTEM],
            Defaults::LIVE_VERSION,
            1.0,
            true,
            CartPrice::TAX_STATE_GROSS
        );

        $container = new CheapestPriceContainer($testData);
        $cheapestPrice = $container->resolve($context);

        static::assertNull($cheapestPrice);
    }
}
