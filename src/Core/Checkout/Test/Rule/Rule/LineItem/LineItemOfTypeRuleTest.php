<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\LineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('business-ops')]
class LineItemOfTypeRuleTest extends TestCase
{
    public function testRuleWithProductTypeMatch(): void
    {
        $rule = (new LineItemOfTypeRule())->assign(['lineItemType' => LineItem::PRODUCT_LINE_ITEM_TYPE]);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope(new LineItem('A', 'product'), $context))
        );

        $cart = new Cart('test');
        $cart->add(new LineItem('A', 'product'));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithProductTypeNotMatch(): void
    {
        $rule = (new LineItemOfTypeRule())->assign(['lineItemType' => 'voucher']);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope(new LineItem('A', 'product'), $context))
        );

        $cart = new Cart('test');
        $cart->add(new LineItem('A', 'product'));

        $scope = new CartRuleScope($cart, $context);

        static::assertFalse($rule->match($scope));
    }
}
