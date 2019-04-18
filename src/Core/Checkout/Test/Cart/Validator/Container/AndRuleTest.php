<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Validator\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Test\Cart\Common\FalseRule;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class AndRuleTest extends TestCase
{
    public function testTrue(): void
    {
        $rule = new AndRule([
            new TrueRule(),
            new TrueRule(),
        ]);

        static::assertTrue(
            $rule->match(
                new CheckoutRuleScope(
                    $this->createMock(SalesChannelContext::class)
                )
            )
        );
    }

    public function testFalse(): void
    {
        $rule = new AndRule([
            new TrueRule(),
            new FalseRule(),
        ]);

        static::assertFalse(
            $rule->match(
                new CartRuleScope(
                    $this->createMock(Cart::class),
                    $this->createMock(SalesChannelContext::class)
                )
            )
        );
    }
}
