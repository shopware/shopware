<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemStockRule;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\Rule\LineItemStockRule
 */
class LineItemStockRuleTest extends TestCase
{
    public function testItReturnsTheCorrectName(): void
    {
        static::assertEquals('cartLineItemStock', (new LineItemStockRule())->getName());
    }

    public function testRulesDoesNotMatchIfScopeNoLineItemScopeNorCartRuleScope(): void
    {
        $rule = new LineItemStockRule();

        static::assertFalse($rule->match($this->createMock(RuleScope::class)));
    }

    public function testItThrowsUnsupportedValueExceptionIfStockIsNotSet(): void
    {
        $rule = new LineItemStockRule();

        $ruleScope = $this->createMock(LineItemScope::class);
        $ruleScope->expects(static::once())
            ->method('getLineItem')
            ->willReturn(static::createMock(LineItem::class));

        static::expectException(UnsupportedValueException::class);
        $rule->match($ruleScope);
    }

    public function provideLineItemTestCases(): \Generator
    {
        // EQ operator
        yield 'EQ: same stock' => [Rule::OPERATOR_EQ, 5, true];
        yield 'EQ: unequal stock' => [Rule::OPERATOR_EQ, 2, false];
        // NEQ operator
        yield 'NEQ: unequal stock' => [Rule::OPERATOR_NEQ, 2, true];
        yield 'EQ: equal stock' => [Rule::OPERATOR_NEQ, 5, false];
        // GT operator
        yield 'GT: bigger stock' => [Rule::OPERATOR_GT, 6, true];
        yield 'GT: same stock' => [Rule::OPERATOR_GT, 5, false];
        yield 'GT: less stock' => [Rule::OPERATOR_GT, 3, false];
        //LT operator
        yield 'LT: less stock' => [Rule::OPERATOR_LT, 4, true];
        yield 'LT: same stock' => [Rule::OPERATOR_LT, 5, false];
        yield 'LT: bigger stock' => [Rule::OPERATOR_LT, 6, false];
        // LTE operator
        yield 'LTE: same stock' => [Rule::OPERATOR_LTE, 5, true];
        yield 'LTE: less stock' => [Rule::OPERATOR_LTE, 4, true];
        yield 'LTE: bigger stock' => [Rule::OPERATOR_LTE, 6, false];
        // GTE operator
        yield 'GTE: same stock' => [Rule::OPERATOR_GTE, 5, true];
        yield 'GTE: bigger stock' => [Rule::OPERATOR_GTE, 6, true];
        yield 'GTE: less stock' => [Rule::OPERATOR_GTE, 4, false];
    }

    /**
     * @dataProvider provideLineItemTestCases
     */
    public function testMatchWithLineItemScope(string $operator, int $lineItemStock, bool $matches): void
    {
        $ruleScope = new LineItemScope(
            $this->createLineItem($lineItemStock),
            static::createMock(SalesChannelContext::class)
        );

        $rule = new LineItemStockRule($operator, 5);

        static::assertEquals($matches, $rule->match($ruleScope));
    }

    /**
     * @dataProvider provideLineItemTestCases
     */
    public function testMatchWithCartRuleScopeWithOneItem(string $operator, int $lineItemStock, bool $matches): void
    {
        $cart = new Cart('test-cart', 'test-token');
        $cart->setLineItems(new LineItemCollection([
            $this->createLineItem($lineItemStock),
        ]));

        $ruleScope = new CartRuleScope($cart, static::createMock(SalesChannelContext::class));

        $rule = new LineItemStockRule($operator, 5);

        static::assertEquals($matches, $rule->match($ruleScope));
    }

    public function testNoMatchWithEmptyCartRuleScope(): void
    {
        $ruleScope = new CartRuleScope(
            new Cart('test-cart', 'test-token'),
            static::createMock(SalesChannelContext::class)
        );

        $rule = new LineItemStockRule(Rule::OPERATOR_EQ, 5);

        static::assertFalse($rule->match($ruleScope));
    }

    public function testMatchesIfOneLineItemMatches(): void
    {
        $matchingLineItem = $this->createLineItem(5, 'matching-line-item');
        $nonMatchingLineItem = $this->createLineItem(2, 'non-matching-line-item');

        $cartMatchingFirst = new Cart('test-cart', 'test-token');
        $cartMatchingFirst->setLineItems(new LineItemCollection([
            $matchingLineItem,
            $nonMatchingLineItem,
        ]));

        $cartMatchingLast = new Cart('test-cart', 'test-token');
        $cartMatchingLast->setLineItems(new LineItemCollection([
            $nonMatchingLineItem,
            $matchingLineItem,
        ]));

        $rule = new LineItemStockRule(Rule::OPERATOR_EQ, 5);

        static::assertTrue(
            $rule->match(new CartRuleScope($cartMatchingFirst, static::createMock(SalesChannelContext::class)))
        );

        static::assertTrue(
            $rule->match(new CartRuleScope($cartMatchingLast, static::createMock(SalesChannelContext::class)))
        );
    }

    public function testNoMatchIfNoLineItemMatches(): void
    {
        $cart = new Cart('test-cart', 'test-token');
        $cart->setLineItems(new LineItemCollection([
            $this->createLineItem(2, 'non-matching-with-2'),
            $this->createLineItem(9, 'non-matching-with-9'),
        ]));

        $rule = new LineItemStockRule(Rule::OPERATOR_EQ, 5);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, static::createMock(SalesChannelContext::class)))
        );
    }

    public function testNoMatchWithLineItemsWithoutStock(): void
    {
        Feature::skipTestIfActive('v6.5.0.0', $this);

        $lineItem = new LineItem('test-id', LineItem::DISCOUNT_LINE_ITEM);

        static::assertNull($lineItem->getDeliveryInformation());

        $cart = new Cart('test-cart', 'some-token');
        $cart->setLineItems(new LineItemCollection([$lineItem]));

        $rule = new LineItemStockRule(Rule::OPERATOR_EQ, 5);

        static::assertFalse(
            $rule->match(new LineItemScope($lineItem, static::createMock(SalesChannelContext::class)))
        );

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, static::createMock(SalesChannelContext::class)))
        );
    }

    public function testMatchesIfNoDeliveryStatusIsSetAndRuleUsesNegativeOperatorIn6500(): void
    {
        Feature::skipTestIfInActive('v6.5.0.0', $this);

        $lineItem = new LineItem('test-id', LineItem::DISCOUNT_LINE_ITEM);

        static::assertNull($lineItem->getDeliveryInformation());

        $cart = new Cart('test-cart', 'some-token');
        $cart->setLineItems(new LineItemCollection([$lineItem]));

        $lineItemScope = new LineItemScope($lineItem, static::createMock(SalesChannelContext::class));
        $cartScope = new CartRuleScope($cart, static::createMock(SalesChannelContext::class));

        static::assertFalse((new LineItemStockRule(Rule::OPERATOR_EQ, 5))->match($lineItemScope));
        static::assertFalse((new LineItemStockRule(Rule::OPERATOR_EQ, 5))->match($cartScope));

        static::assertTrue((new LineItemStockRule(Rule::OPERATOR_NEQ, 5))->match($lineItemScope));
        static::assertTrue((new LineItemStockRule(Rule::OPERATOR_NEQ, 5))->match($cartScope));

        static::assertTrue((new LineItemStockRule(Rule::OPERATOR_EMPTY, 5))->match($lineItemScope));
        static::assertTrue((new LineItemStockRule(Rule::OPERATOR_EMPTY, 5))->match($cartScope));
    }

    public function testConstraintsIncludesOperatorAndStock(): void
    {
        $constraints = (new LineItemStockRule())->getConstraints();

        static::assertEquals([
            'operator' => [new NotBlank(),
                new Choice([
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_LTE,
                    Rule::OPERATOR_GTE,
                    Rule::OPERATOR_NEQ,
                    Rule::OPERATOR_GT,
                    Rule::OPERATOR_LT,
                ]),
            ],
            'stock' => [
                new NotBlank(),
                new Type('int'),
            ],
        ], $constraints);
    }

    public function testConfigUsesOperatorSetNumbers(): void
    {
        $config = (new LineItemStockRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        static::assertEquals([
            'operators' => RuleConfig::OPERATOR_SET_NUMBER,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    public function testConfigHasASingleIntFieldStock(): void
    {
        $config = (new LineItemStockRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('fields', $configData);
        static::assertCount(1, $configData['fields']);
        static::assertEquals([[
            'name' => 'stock',
            'type' => 'int',
            'config' => [],
        ]], $configData['fields']);
    }

    private function createLineItem(int $stock, string $id = 'line-item-id'): LineItem
    {
        $lineItem = new LineItem(
            $id,
            LineItem::PRODUCT_LINE_ITEM_TYPE,
            'product-id'
        );

        $lineItem->setDeliveryInformation(new DeliveryInformation(
            $stock,
            1.0,
            false
        ));

        return $lineItem;
    }
}
