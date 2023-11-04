<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('business-ops')]
class CartAmountRuleTest extends TestCase
{
    public function testRuleWithExactAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 275, 'operator' => CartAmountRule::OPERATOR_EQ]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithExactAmountNotMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 0, 'operator' => CartAmountRule::OPERATOR_EQ]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualExactAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 275, 'operator' => CartAmountRule::OPERATOR_LTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 300, 'operator' => CartAmountRule::OPERATOR_LTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualAmountNotMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 274, 'operator' => CartAmountRule::OPERATOR_LTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualExactAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 275, 'operator' => CartAmountRule::OPERATOR_GTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 100, 'operator' => CartAmountRule::OPERATOR_GTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualAmountNotMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 276, 'operator' => CartAmountRule::OPERATOR_GTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleNotEqualAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 0, 'operator' => CartAmountRule::OPERATOR_NEQ]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleNotEqualAmountNotMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 275, 'operator' => CartAmountRule::OPERATOR_NEQ]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    /**
     * @dataProvider unsupportedOperators
     */
    public function testUnsupportedOperators(string $operator): void
    {
        $this->expectException(UnsupportedOperatorException::class);

        $rule = (new CartAmountRule())->assign(['amount' => 100, 'operator' => $operator]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public static function unsupportedOperators(): array
    {
        return [
            ['random'],
            [''],
        ];
    }
}
