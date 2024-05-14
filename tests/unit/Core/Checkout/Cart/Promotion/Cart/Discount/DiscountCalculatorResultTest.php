<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Discount;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountCalculatorResult;

/**
 * @internal
 */
#[CoversClass(DiscountCalculatorResult::class)]
class DiscountCalculatorResultTest extends TestCase
{
    /**
     * This test verifies that the property is correctly
     * assigned as well as returned in the getter function.
     */
    #[Group('promotions')]
    public function testGetPrice(): void
    {
        $price = new CalculatedPrice(29, 29, new CalculatedTaxCollection(), new TaxRuleCollection());

        $result = new DiscountCalculatorResult(
            $price,
            []
        );

        static::assertEquals(29, $result->getPrice()->getTotalPrice());
    }

    /**
     * This test verifies that the property is correctly
     * assigned as well as returned in the getter function.
     */
    #[Group('promotions')]
    public function testCompositionItems(): void
    {
        $price = new CalculatedPrice(29, 29, new CalculatedTaxCollection(), new TaxRuleCollection());

        $compositionItems = [
            new DiscountCompositionItem('ABC', 2, 13),
        ];

        $result = new DiscountCalculatorResult($price, $compositionItems);

        static::assertSame($compositionItems, $result->getCompositionItems());
    }
}
