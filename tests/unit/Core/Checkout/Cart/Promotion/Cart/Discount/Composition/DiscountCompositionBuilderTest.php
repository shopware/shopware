<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Discount\Composition;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DiscountCompositionBuilder::class)]
class DiscountCompositionBuilderTest extends TestCase
{
    /**
     * This test verifies that we can combine and aggregate
     * a list of composition items.
     * the items will be compressed to 1 entry for an id,
     * and quantity and discount values will be aggregated.
     * We use this function for the final composition result
     * of the new discount line item.
     */
    #[Group('promotions')]
    public function testAtAggregateCompositionItems(): void
    {
        $items = [
            new DiscountCompositionItem('A', 1, 15),
            new DiscountCompositionItem('A', 3, 32.5),
            new DiscountCompositionItem('B', 6, 12),
        ];

        $aggregated = (new DiscountCompositionBuilder())->aggregateCompositionItems($items);

        static::assertCount(2, $aggregated, 'Merging from 3 into 2 items did not work');

        static::assertSame('A', $aggregated[0]->getId());
        static::assertSame(4, $aggregated[0]->getQuantity());
        static::assertSame(47.5, $aggregated[0]->getDiscountValue());

        static::assertSame('B', $aggregated[1]->getId());
        static::assertSame(6, $aggregated[1]->getQuantity());
        static::assertSame(12.0, $aggregated[1]->getDiscountValue());
    }

    #[Group('promotions')]
    public function testAdjustCompositionItemValuesForNegativeTargetPrice(): void
    {
        $targetPrice = new CalculatedPrice(
            -20,
            -20,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        );

        $items = [
            new DiscountCompositionItem('A', 1, 10),
            new DiscountCompositionItem('B', 1, 30),
        ];

        $adjusted = (new DiscountCompositionBuilder())->adjustCompositionItemValues($targetPrice, $items);

        static::assertSame(5.0, $adjusted[0]->getDiscountValue());
        static::assertSame(15.0, $adjusted[1]->getDiscountValue());
    }
}
