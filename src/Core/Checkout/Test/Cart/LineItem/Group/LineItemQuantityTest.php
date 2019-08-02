<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group\Exception;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;

class LineItemQuantityTest extends TestCase
{
    /**
     * This test verifies that our property is correctly
     * assigned and returned in its getter.
     *
     * @test
     * @group lineitemgroup
     */
    public function testPropertyLineItemId(): void
    {
        $item = new LineItemQuantity('ID1', 2);

        static::assertEquals('ID1', $item->getLineItemId());
    }

    /**
     * This test verifies that our property is correctly
     * assigned and returned in its getter.
     *
     * @test
     * @group lineitemgroup
     */
    public function testPropertQuantity(): void
    {
        $item = new LineItemQuantity('ID1', 2);

        static::assertEquals(2, $item->getQuantity());
    }

    /**
     * This test verifies that its possible
     * to adjust the quantity value of this object.
     *
     * @test
     * @group lineitemgroup
     */
    public function testSetQuantity(): void
    {
        $item = new LineItemQuantity('ID1', 2);

        $item->setQuantity(5);

        static::assertEquals(5, $item->getQuantity());
    }
}
