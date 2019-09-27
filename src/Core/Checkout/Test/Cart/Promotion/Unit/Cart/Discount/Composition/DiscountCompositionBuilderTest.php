<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Cart\Discount\Composition;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;

class DiscountCompositionBuilderTest extends TestCase
{
    /**
     * This test verifies that we can combine and aggregate
     * a list of composition items.
     * the items will be compressed to 1 entry for an id,
     * and quantity and discount values will be aggregated.
     * We use this function for the final composition result
     * of the new discount line item.
     *
     * @test
     * @group promotions
     */
    public function atAggregateCompositionItems(): void
    {
        $items = [
            new DiscountCompositionItem('A', 1, 15),
            new DiscountCompositionItem('A', 3, 32.5),
            new DiscountCompositionItem('B', 6, 12),
        ];

        $builder = new DiscountCompositionBuilder();

        /** @var DiscountCompositionItem[] $aggregated */
        $aggregated = $builder->aggregateCompositionItems($items);

        static::assertCount(2, $aggregated, 'Merging from 3 into 2 items did not work');

        static::assertEquals('A', $aggregated[0]->getId());
        static::assertEquals(4, $aggregated[0]->getQuantity());
        static::assertEquals(47.5, $aggregated[0]->getDiscountValue());

        static::assertEquals('B', $aggregated[1]->getId());
        static::assertEquals(6, $aggregated[1]->getQuantity());
        static::assertEquals(12, $aggregated[1]->getDiscountValue());
    }
}
