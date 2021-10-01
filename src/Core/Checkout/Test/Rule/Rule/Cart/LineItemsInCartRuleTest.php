<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Rule\Rule\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemsInCartRule;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @major-deprecated (flag:FEATURE_NEXT_17016) This rule will be removed. Use the LineItemRule instead.
 */
class LineItemsInCartRuleTest extends TestCase
{
    protected function setUp(): void
    {
        if (Feature::isActive('FEATURE_NEXT_17016')) {
            static::markTestSkipped('Rule is deprecated NEXT-17016');
        }
    }

    public function testRuleWithExactLineItemsMatch(): void
    {
        $rule = (new LineItemsInCartRule())->assign(['identifiers' => ['A', 'B']]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLineItemsNotMatch(): void
    {
        $rule = (new LineItemsInCartRule())->assign(['identifiers' => ['C', 'D']]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLineItemsWithoutIdPayload(): void
    {
        $rule = (new LineItemsInCartRule())->assign(['identifiers' => ['A', 'B']]);

        $cart = Generator::createCart();
        foreach ($cart->getLineItems() as $lineItem) {
            $lineItem->setReferencedId(null);
        }

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLineItemSubsetMatch(): void
    {
        $rule = (new LineItemsInCartRule())->assign(['identifiers' => ['B']]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }
}
