<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;

/**
 * @covers \Shopware\Core\Checkout\Cart\Rule\GoodsPriceRule
 *
 * @internal
 */
#[Package('business-ops')]
class GoodsPriceRuleTest extends TestCase
{
    use CartRuleHelperTrait;

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

    public function testMatchWithWrongScopeShouldReturnFalse(): void
    {
        $goodsCountRule = new GoodsPriceRule();
        $wrongScope = $this->createMock(RuleScope::class);

        static::assertFalse($goodsCountRule->match($wrongScope));
    }

    public function testMatchWithSimpleRule(): void
    {
        $goodsCountRule = new GoodsPriceRule(Rule::OPERATOR_EQ, 300.0);
        $goodsCountRule->setRules([new SimpleRule()]);

        static::assertTrue($goodsCountRule->match($this->createCartRuleScope()));
    }

    public function testMatchWithSimpleRuleExpectFalse(): void
    {
        $goodsCountRule = new GoodsPriceRule(Rule::OPERATOR_EQ, 300.0);
        $goodsCountRule->setRules([new SimpleRule(false)]);

        static::assertFalse($goodsCountRule->match($this->createCartRuleScope()));
    }

    public function testGetConstraints(): void
    {
        $goodsCountRule = new GoodsPriceRule();

        $result = $goodsCountRule->getConstraints();

        static::assertArrayHasKey('amount', $result);
        static::assertArrayHasKey('operator', $result);

        static::assertIsArray($result['amount']);
        static::assertIsArray($result['operator']);
    }

    /**
     * @dataProvider getLineItemScopeTestData
     */
    public function testIfMatchesAllCorrectWithLineItemScope(
        float $amount,
        string $operator,
        float $price,
        bool $expected
    ): void {
        $rule = (new GoodsPriceRule())->assign(['amount' => $amount, 'operator' => $operator]);

        $match = $rule->match(new LineItemScope(
            $this->createLineItemWithPrice($price),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function getLineItemScopeTestData(): array
    {
        return [
            'product / equal / match price' => [270.0, Rule::OPERATOR_EQ, 270.0, true],
            'product / equal / no match price' => [270.0, Rule::OPERATOR_EQ, 100.0, false],
            'product / lower than or equal / match price' => [270.0, Rule::OPERATOR_LTE, 250.0, true],
            'product / lower than or equal / no match price' => [270.0, Rule::OPERATOR_LTE, 280.0, false],
            'product / lower than or equal / match equal price' => [270.0, Rule::OPERATOR_LTE, 270.0, true],
            'product / greater than or equal / match price' => [270.0, Rule::OPERATOR_GTE, 280.0, true],
            'product / greater than or equal / no match price' => [270.0, Rule::OPERATOR_GTE, 260.0, false],
            'product / greater than or equal / match equal price' => [270.0, Rule::OPERATOR_GTE, 270.0, true],
            'product / not equal / match price' => [270.0, Rule::OPERATOR_NEQ, 250.0, true],
            'product / not equal / no match price' => [270.0, Rule::OPERATOR_NEQ, 270.0, false],
        ];
    }

    private function createCartRuleScope(): CartRuleScope
    {
        $cart = new Cart('test');
        $cart->add(
            (new LineItem('a', 'a'))
                ->setGood(true)
                ->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );
        $cart->add(
            (new LineItem('b', 'a'))
                ->setGood(true)
                ->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $cart->add(
            (new LineItem('c', 'a'))
                ->setGood(true)
                ->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()))
        );

        $context = $this->createMock(SalesChannelContext::class);

        return new CartRuleScope($cart, $context);
    }

    private function createLineItemWithPrice(float $amount): LineItem
    {
        return $this->createLineItem()->setPrice(new CalculatedPrice($amount, $amount, new CalculatedTaxCollection(), new TaxRuleCollection()));
    }
}
