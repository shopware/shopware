<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroup;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class LineItemGroupTest extends TestCase
{
    /**
     * This test verifies that we have an empty
     * list on new instances and not null.
     *
     * @group lineitemgroup
     */
    public function testItemsAreEmptyOnNewGroup(): void
    {
        $group = new LineItemGroup();

        static::assertCount(0, $group->getItems());
    }

    /**
     * This test verifies that our hasItems
     * function works correctly for empty entries.
     *
     * @group lineitemgroup
     */
    public function testHasItemsOnEmptyList(): void
    {
        $group = new LineItemGroup();

        static::assertFalse($group->hasItems());
    }

    /**
     * This test verifies that our hasItems
     * function works correctly for existing entries.
     *
     * @group lineitemgroup
     */
    public function testHasItempsOnExistingList(): void
    {
        $group = new LineItemGroup();

        $group->addItem('ID1', 5);

        static::assertTrue($group->hasItems());
    }

    /**
     * This test verifies that our items
     * are correctly added if no entry exists
     * for the item id.
     *
     * @group lineitemgroup
     */
    public function testAddInitialItem(): void
    {
        $group = new LineItemGroup();

        $group->addItem('ID1', 5);

        static::assertEquals('ID1', $group->getItems()[0]->getLineItemId());
        static::assertEquals(5, $group->getItems()[0]->getQuantity());
    }

    /**
     * This test verifies that our quantity
     * is correctly increased if we already have
     * an entry for the provided item id.
     *
     * @group lineitemgroup
     */
    public function testAddQuantityToExisting(): void
    {
        $group = new LineItemGroup();

        $group->addItem('ID1', 5);
        $group->addItem('ID1', 2);

        static::assertEquals(7, $group->getItems()[0]->getQuantity());
    }
}
