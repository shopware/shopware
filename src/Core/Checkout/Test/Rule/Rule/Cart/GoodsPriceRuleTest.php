<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('business-ops')]
class GoodsPriceRuleTest extends TestCase
{
    public function testRuleWithExactPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 270.0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new CalculatedPrice(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithExactPriceNotMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 1.0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualExactPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 270.0, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 300.0, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualPriceNotMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => -1.0, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualExactPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 270.0, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new CalculatedPrice(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 250.0, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new CalculatedPrice(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualPriceNotMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 300.0, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualPriceMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 200.0, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualPriceNotMatch(): void
    {
        $rule = (new GoodsPriceRule())->assign(['amount' => 270.0, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setPrice(new CalculatedPrice(270, 270, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }
}
