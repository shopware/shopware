<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\GoodsCountRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(GoodsCountRule::class)]
class GoodsCountRuleTest extends TestCase
{
    public function testRuleWithExactCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithExactCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualExactCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 1, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 2, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');

        $cart->add((new LineItem('A', 'test'))->setGood(true));

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualExactCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 1, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 2, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 2, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 1, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testMatchWithWrongScopeShouldReturnFalse(): void
    {
        $goodsCountRule = new GoodsCountRule();
        $wrongScope = $this->createMock(RuleScope::class);

        static::assertFalse($goodsCountRule->match($wrongScope));
    }

    public function testMatchWithSimpleRule(): void
    {
        $goodsCountRule = new GoodsCountRule(Rule::OPERATOR_EQ, 3);
        $goodsCountRule->setRules([new SimpleRule()]);

        static::assertTrue($goodsCountRule->match($this->createCartRuleScope()));
    }

    public function testMatchWithSimpleRuleExpectFalse(): void
    {
        $goodsCountRule = new GoodsCountRule(Rule::OPERATOR_EQ, 3);
        $goodsCountRule->setRules([new SimpleRule(false)]);

        static::assertFalse($goodsCountRule->match($this->createCartRuleScope()));
    }

    public function testGetConstraints(): void
    {
        $goodsCountRule = new GoodsCountRule();

        $result = $goodsCountRule->getConstraints();

        static::assertArrayHasKey('count', $result);
        static::assertArrayHasKey('operator', $result);

        static::assertIsArray($result['count']);
        static::assertIsArray($result['operator']);
    }

    private function createCartRuleScope(): CartRuleScope
    {
        $cart = new Cart('test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));
        $cart->add((new LineItem('b', 'a'))->setGood(true));
        $cart->add((new LineItem('c', 'a'))->setGood(true));

        $context = $this->createMock(SalesChannelContext::class);

        return new CartRuleScope($cart, $context);
    }
}
