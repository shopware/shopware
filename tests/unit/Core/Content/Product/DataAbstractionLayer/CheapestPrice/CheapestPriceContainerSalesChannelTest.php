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
use Shopware\Core\Test\Assert\Serialization;

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

    public function testResolveSkipsVariantHiddenByStoredVisibility(): void
    {
        $currentSalesChannelId = Uuid::randomHex();
        $otherSalesChannelId = Uuid::randomHex();

        $container = new CheapestPriceContainer([
            'variant1' => [
                'default' => $this->createPrice(50.0, 42.02),
            ],
            'variant2' => [
                'default' => $this->createPrice(100.0, 84.03),
            ],
            'sales_channel_ids' => [
                'parent' => [$currentSalesChannelId],
                'variants' => ['variant1' => [$otherSalesChannelId]],
            ],
        ]);

        $cheapestPrice = $container->resolve($this->createSalesChannelContext($currentSalesChannelId));

        static::assertNotNull($cheapestPrice);
        static::assertSame('variant2', $cheapestPrice->getVariantId());
    }

    public function testResolveUsesVariantVisibilityOverParentVisibility(): void
    {
        $currentSalesChannelId = Uuid::randomHex();
        $otherSalesChannelId = Uuid::randomHex();

        $container = new CheapestPriceContainer([
            'variant1' => [
                'default' => $this->createPrice(50.0, 42.02),
            ],
            'variant2' => [
                'default' => $this->createPrice(100.0, 84.03),
            ],
            'sales_channel_ids' => [
                'parent' => [$otherSalesChannelId],
                'variants' => ['variant2' => [$currentSalesChannelId]],
            ],
        ]);

        $cheapestPrice = $container->resolve($this->createSalesChannelContext($currentSalesChannelId));

        static::assertNotNull($cheapestPrice);
        static::assertSame('variant2', $cheapestPrice->getVariantId());
    }

    public function testResolveReturnsNullWhenNoVariantIsVisible(): void
    {
        $container = new CheapestPriceContainer([
            'variant1' => [
                'default' => $this->createPrice(50.0, 42.02),
            ],
            'sales_channel_ids' => [
                'parent' => [],
                'variants' => [],
            ],
        ]);

        static::assertNull($container->resolve($this->createSalesChannelContext(Uuid::randomHex())));
    }

    public function testHiddenVariantDoesNotConsumeTheInheritedDefaultPrice(): void
    {
        $currentSalesChannelId = Uuid::randomHex();

        $container = new CheapestPriceContainer([
            'default' => $this->createPrice(80.0, 67.23),
            'variant1' => [
                'default' => null,
            ],
            'variant2' => [
                'default' => null,
            ],
            'sales_channel_ids' => [
                'parent' => [$currentSalesChannelId],
                'variants' => ['variant1' => [Uuid::randomHex()]],
            ],
        ]);

        $cheapestPrice = $container->resolve($this->createSalesChannelContext($currentSalesChannelId));

        static::assertNotNull($cheapestPrice);
        static::assertSame('variant2', $cheapestPrice->getVariantId());
    }

    public function testStoredVisibilityIsNotPartOfThePriceValue(): void
    {
        $container = new CheapestPriceContainer([
            'variant1' => [
                'default' => $this->createPrice(50.0, 42.02),
            ],
            'sales_channel_ids' => [
                'parent' => ['salesChannel1'],
                'variants' => [],
            ],
        ]);

        static::assertSame(['variant1'], $container->getVariantIds());
        static::assertArrayNotHasKey('sales_channel_ids', $container->getValue());
    }

    public function testGetSalesChannelIdsForVariant(): void
    {
        $container = new CheapestPriceContainer([
            'variant1' => [
                'default' => $this->createPrice(50.0, 42.02),
            ],
            'variant2' => [
                'default' => $this->createPrice(100.0, 84.03),
            ],
            'sales_channel_ids' => [
                'parent' => ['salesChannel1', 'salesChannel2'],
                'variants' => ['variant2' => ['salesChannel3']],
            ],
        ]);

        static::assertSame(['salesChannel1', 'salesChannel2'], $container->getSalesChannelIdsForVariant('variant1'));
        static::assertSame(['salesChannel3'], $container->getSalesChannelIdsForVariant('variant2'));
    }

    public function testGetSalesChannelIdsForVariantIsNullWithoutStoredVisibility(): void
    {
        $container = new CheapestPriceContainer([
            'variant1' => [
                'default' => $this->createPrice(50.0, 42.02, ['salesChannel1']),
            ],
        ]);

        static::assertNull($container->getSalesChannelIdsForVariant('variant1'));
    }

    public function testContainerSerializedBeforeVisibilityPropertyStillFiltersBySalesChannel(): void
    {
        $currentSalesChannelId = Uuid::randomHex();

        $container = new CheapestPriceContainer([
            'variant1' => [
                'default' => $this->createPrice(50.0, 42.02, [Uuid::randomHex()]),
            ],
            'variant2' => [
                'default' => $this->createPrice(100.0, 84.03, [$currentSalesChannelId]),
            ],
        ]);

        $serialized = serialize($container);
        $property = serialize("\0*\0salesChannelIds") . 'N;';
        static::assertStringContainsString($property, $serialized);

        $legacy = (string) preg_replace_callback(
            '/^(O:\d+:"[^"]+":)(\d+)/',
            static fn (array $match): string => $match[1] . ((int) $match[2] - 1),
            str_replace($property, '', $serialized)
        );

        $unserialized = Serialization::assertUnserializedInstanceOf(CheapestPriceContainer::class, $legacy);

        $cheapestPrice = $unserialized->resolve($this->createSalesChannelContext($currentSalesChannelId));

        static::assertNotNull($cheapestPrice);
        static::assertSame('variant2', $cheapestPrice->getVariantId());
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
