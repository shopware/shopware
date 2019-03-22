<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsFetchDefinition;

class CartPromotionsFetchDefinitionTest extends TestCase
{
    /**
     * This test verifies that our provided line item ID array
     * is correctly saved and passed on within the
     * fetch definition object.
     * We add a list of IDs to the object and verify we get the
     * list of IDs back when accessing it later.
     *
     * @test
     * @group promotions
     */
    public function testLineItemIDsAreCorrectlySaved()
    {
        $lineItemIDs = [];
        $lineItemIDs[] = 'P1';
        $lineItemIDs[] = 'P2';

        $definition = new CartPromotionsFetchDefinition($lineItemIDs);

        static::assertEquals($lineItemIDs, $definition->getLineItemIds());
    }
}
