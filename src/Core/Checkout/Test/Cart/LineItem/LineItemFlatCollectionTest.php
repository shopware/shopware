<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemFlatCollection;

class LineItemFlatCollectionTest extends TestCase
{
    /**
     * This test verifies that its possible
     * to add a line item with a specific id multiple times.
     * It must not be aggregated within an associative array in the flat list.
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     */
    public function testCanAddSameItemMultipleTimes(): void
    {
        $lineItem = new LineItem('ABC', '');
        $lineItem->setStackable(true);

        $collection = new LineItemFlatCollection();

        $collection->add($lineItem);
        $collection->add($lineItem);

        static::assertCount(2, $collection);
    }
}
