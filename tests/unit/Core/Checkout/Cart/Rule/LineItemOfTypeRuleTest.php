<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package services-settings
 *
 * @internal
 */
#[CoversClass(LineItemOfTypeRule::class)]
class LineItemOfTypeRuleTest extends TestCase
{
    public function testGetName(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_EQ, 'test');

        static::assertEquals('cartLineItemOfType', $rule->getName());
    }

    public function testGetConstraints(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_EQ, 'test');

        static::assertEquals(
            [
                'lineItemType' => RuleConstraints::string(),
                'operator' => RuleConstraints::stringOperators(false),
            ],
            $rule->getConstraints()
        );
    }

    public function testGetConfig(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_EQ, 'test');

        static::assertEquals(
            (new RuleConfig())
                ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
                ->selectField('lineItemType', [LineItem::PRODUCT_LINE_ITEM_TYPE, LineItem::PROMOTION_LINE_ITEM_TYPE]),
            $rule->getConfig()
        );
    }

    public function testMatchLineItemScopeMatchesWithEquals(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_EQ, 'shirt');

        $lineItem = new LineItem(Uuid::randomHex(), 'shirt');

        $scope = new LineItemScope($lineItem, static::createMock(SalesChannelContext::class));

        static::assertTrue($rule->match($scope));
    }

    public function testMatchLineItemScopeNotMatchesWithEquals(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_EQ, 'jeans');

        $lineItem = new LineItem(Uuid::randomHex(), 'shirt');

        $scope = new LineItemScope($lineItem, static::createMock(SalesChannelContext::class));

        static::assertFalse($rule->match($scope));
    }

    public function testMatchLineItemScopeNotMatchesWithNotEquals(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_NEQ, 'shirt');

        $lineItem = new LineItem(Uuid::randomHex(), 'shirt');

        $scope = new LineItemScope($lineItem, static::createMock(SalesChannelContext::class));

        static::assertFalse($rule->match($scope));
    }

    public function testMatchLineItemScopeMatchesWithNotEquals(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_NEQ, 'jeans');

        $lineItem = new LineItem(Uuid::randomHex(), 'shirt');

        $scope = new LineItemScope($lineItem, static::createMock(SalesChannelContext::class));

        static::assertTrue($rule->match($scope));
    }

    public function testMatchCartItemScopeMatchesWithEquals(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_EQ, 'shirt');

        $lineItem = new LineItem(Uuid::randomHex(), 'shirt');

        $cart = new Cart(Uuid::randomHex());
        $cart->setLineItems(new LineItemCollection([$lineItem]));
        $scope = new CartRuleScope($cart, static::createMock(SalesChannelContext::class));

        static::assertTrue($rule->match($scope));
    }

    public function testMatchCartItemScopeNotMatchesWithEquals(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_EQ, 'jeans');

        $lineItem = new LineItem(Uuid::randomHex(), 'shirt');

        $cart = new Cart(Uuid::randomHex());
        $cart->setLineItems(new LineItemCollection([$lineItem]));
        $scope = new CartRuleScope($cart, static::createMock(SalesChannelContext::class));

        static::assertFalse($rule->match($scope));
    }

    public function testMatchCartItemScopeNotMatchesWithNotEquals(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_NEQ, 'shirt');

        $lineItem = new LineItem(Uuid::randomHex(), 'shirt');

        $cart = new Cart(Uuid::randomHex());
        $cart->setLineItems(new LineItemCollection([$lineItem]));
        $scope = new CartRuleScope($cart, static::createMock(SalesChannelContext::class));

        static::assertFalse($rule->match($scope));
    }

    public function testMatchCartItemScopeMatchesWithNotEquals(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_NEQ, 'jeans');

        $lineItem = new LineItem(Uuid::randomHex(), 'shirt');

        $cart = new Cart(Uuid::randomHex());
        $cart->setLineItems(new LineItemCollection([$lineItem]));
        $scope = new CartRuleScope($cart, static::createMock(SalesChannelContext::class));

        static::assertTrue($rule->match($scope));
    }

    public function testMatchWrongScopeAlwaysFalse(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_NEQ, 'jeans');

        $lineItem = new LineItem(Uuid::randomHex(), 'shirt');

        $cart = new Cart(Uuid::randomHex());
        $cart->setLineItems(new LineItemCollection([$lineItem]));
        $scope = new CheckoutRuleScope(static::createMock(SalesChannelContext::class));

        static::assertFalse($rule->match($scope));

        $rule = new LineItemOfTypeRule(Rule::OPERATOR_NEQ, 'jeans');

        $lineItem = new LineItem(Uuid::randomHex(), 'jeans');

        $cart = new Cart(Uuid::randomHex());
        $cart->setLineItems(new LineItemCollection([$lineItem]));
        $scope = new CheckoutRuleScope(static::createMock(SalesChannelContext::class));

        static::assertFalse($rule->match($scope));
    }

    public function testExceptionOnUnsupportedOperator(): void
    {
        $rule = new LineItemOfTypeRule(Rule::OPERATOR_GT, 'jeans');

        $lineItem = new LineItem(Uuid::randomHex(), 'shirt');

        $cart = new Cart(Uuid::randomHex());
        $cart->setLineItems(new LineItemCollection([$lineItem]));
        $scope = new CartRuleScope($cart, static::createMock(SalesChannelContext::class));

        static::expectException(UnsupportedOperatorException::class);

        $rule->match($scope);
    }
}
