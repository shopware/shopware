<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\CartPromotionsDataDefinition;
use Shopware\Core\Checkout\Promotion\PromotionEntity;

class CartPromotionsDataDefinitionTest extends TestCase
{
    /**
     * This test verifies that our provided promotions array
     * is correctly saved and passed on within the
     * promotions data definition object.
     * We add a list of entities to the object and
     * verify we really get the same objects back when
     * accessing it later.
     *
     * @test
     * @group promotions
     */
    public function testPromotionsAreCorrectlySaved()
    {
        $promotions = [];
        $promotions[] = new PromotionEntity();
        $promotions[] = new PromotionEntity();

        $definition = new CartPromotionsDataDefinition($promotions);

        static::assertSame($promotions, $definition->getPromotions());
    }
}
