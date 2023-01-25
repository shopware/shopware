<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\LineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('business-ops')]
class LineItemWithQuantityRuleTest extends TestCase
{
    private LineItem $lineItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lineItem = (new LineItem('A', 'product', 'A', 2))
            ->setPrice(new CalculatedPrice(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection(), 2));
    }

    public function testRuleWithExactAmountMatch(): void
    {
        $rule = (new LineItemWithQuantityRule())->assign(['id' => 'A', 'quantity' => 2, 'operator' => Rule::OPERATOR_EQ]);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithExactAmountNotMatch(): void
    {
        $rule = (new LineItemWithQuantityRule())->assign(['id' => 'A', 'quantity' => 0, 'operator' => Rule::OPERATOR_EQ]);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualExactAmountMatch(): void
    {
        $rule = (new LineItemWithQuantityRule())->assign(['id' => 'A', 'quantity' => 2, 'operator' => Rule::OPERATOR_LTE]);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualAmountMatch(): void
    {
        $rule = (new LineItemWithQuantityRule())->assign(['id' => 'A', 'quantity' => 3, 'operator' => Rule::OPERATOR_LTE]);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualAmountNotMatch(): void
    {
        $rule = (new LineItemWithQuantityRule())->assign(['id' => 'A', 'quantity' => 1, 'operator' => Rule::OPERATOR_LTE]);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualExactAmountMatch(): void
    {
        $rule = (new LineItemWithQuantityRule())->assign(['id' => 'A', 'quantity' => 2, 'operator' => Rule::OPERATOR_GTE]);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualMatch(): void
    {
        $rule = (new LineItemWithQuantityRule())->assign(['id' => 'A', 'quantity' => 1, 'operator' => Rule::OPERATOR_GTE]);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualNotMatch(): void
    {
        $rule = (new LineItemWithQuantityRule())->assign(['id' => 'A', 'quantity' => 3, 'operator' => Rule::OPERATOR_GTE]);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualMatch(): void
    {
        $rule = (new LineItemWithQuantityRule())->assign(['id' => 'A', 'quantity' => 1, 'operator' => Rule::OPERATOR_NEQ]);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualNotMatch(): void
    {
        $rule = (new LineItemWithQuantityRule())->assign(['id' => 'A', 'quantity' => 2, 'operator' => Rule::OPERATOR_NEQ]);

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope($this->lineItem, $context))
        );

        $cart = new Cart('test');
        $cart->add($this->lineItem);
        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }
}
