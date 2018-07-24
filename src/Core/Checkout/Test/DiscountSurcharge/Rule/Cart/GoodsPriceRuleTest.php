<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Rule\Rule;

class GoodsPriceRuleTest extends TestCase
{
    public function testRuleWithExactPriceMatch(): void
    {
        $rule = new GoodsPriceRule(270, Rule::OPERATOR_EQ);

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new Price(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithExactPriceNotMatch(): void
    {
        $rule = new GoodsPriceRule(1, Rule::OPERATOR_EQ);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualExactPriceMatch(): void
    {
        $rule = new GoodsPriceRule(270, Rule::OPERATOR_LTE);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualPriceMatch(): void
    {
        $rule = new GoodsPriceRule(300, Rule::OPERATOR_LTE);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithLowerThanEqualPriceNotMatch(): void
    {
        $rule = new GoodsPriceRule(-1, Rule::OPERATOR_LTE);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualExactPriceMatch(): void
    {
        $rule = new GoodsPriceRule(270, Rule::OPERATOR_GTE);

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new Price(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualPriceMatch(): void
    {
        $rule = new GoodsPriceRule(250, Rule::OPERATOR_GTE);

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new Price(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithGreaterThanEqualPriceNotMatch(): void
    {
        $rule = new GoodsPriceRule(300, Rule::OPERATOR_GTE);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithNotEqualPriceMatch(): void
    {
        $rule = new GoodsPriceRule(200, Rule::OPERATOR_NEQ);

        $cart = new Cart('test', 'test');
        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testRuleWithNotEqualPriceNotMatch(): void
    {
        $rule = new GoodsPriceRule(270, Rule::OPERATOR_NEQ);

        $cart = new Cart('test', 'test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new Price(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}
