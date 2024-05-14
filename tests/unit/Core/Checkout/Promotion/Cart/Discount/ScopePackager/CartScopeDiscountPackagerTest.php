<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemRule;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\CartScopeDiscountPackager;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(CartScopeDiscountPackager::class)]
class CartScopeDiscountPackagerTest extends TestCase
{
    public function testGetMatchingItems(): void
    {
        $context = Generator::createSalesChannelContext();

        $matchingLineItem = (new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex(), 2))->setStackable(true);
        $cart = new Cart('foo');
        $cart->setLineItems(
            new LineItemCollection([
                $matchingLineItem,
                (new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, Uuid::randomHex()))->setStackable(true),
            ])
        );

        $priceDefinition = new AbsolutePriceDefinition(42, new LineItemRule(Rule::OPERATOR_EQ, [$matchingLineItem->getReferencedId() ?? '']));
        $discount = new DiscountLineItem('foo', $priceDefinition, ['discountScope' => 'foo', 'discountType' => 'bar'], null);

        $packager = new CartScopeDiscountPackager();
        $items = $packager->getMatchingItems($discount, $cart, $context);

        $expected = new DiscountPackageCollection([
            new DiscountPackage(
                new LineItemQuantityCollection([
                    new LineItemQuantity($matchingLineItem->getId(), 1),
                    new LineItemQuantity($matchingLineItem->getId(), 1),
                ])
            ),
        ]);

        static::assertEquals($expected, $items);

        $priceDefinition = new PercentagePriceDefinition(42, new LineItemRule(Rule::OPERATOR_EQ, [Uuid::randomHex()]));
        $discount = new DiscountLineItem('foo', $priceDefinition, ['discountScope' => 'foo', 'discountType' => 'bar'], null);

        $items = $packager->getMatchingItems($discount, $cart, $context);

        static::assertEquals(new DiscountPackageCollection([]), $items);
    }
}
