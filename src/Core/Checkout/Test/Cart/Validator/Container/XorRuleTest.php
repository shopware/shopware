<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Validator\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Test\Cart\Common\FalseRule;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Framework\Rule\Container\XorRule;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class XorRuleTest extends TestCase
{
    public function testSingleTrueRule(): void
    {
        $rule = new XorRule([
            new FalseRule(),
            new TrueRule(),
            new FalseRule(),
        ]);

        static::assertEquals(
            new Match(true),
            $rule->match(
                new CheckoutRuleScope(
                    $this->createMock(SalesChannelContext::class)
                )
            )
        );
    }

    public function testWithMultipleFalse(): void
    {
        $rule = new XorRule([
            new FalseRule(),
            new FalseRule(),
        ]);

        static::assertEquals(
            new Match(false),
            $rule->match(
                new CheckoutRuleScope(
                    $this->createMock(SalesChannelContext::class)
                )
            )
        );
    }

    public function testWithMultipleTrue(): void
    {
        $rule = new XorRule([
            new TrueRule(),
            new TrueRule(),
            new FalseRule(),
        ]);

        static::assertEquals(
            new Match(false),
            $rule->match(
                new CheckoutRuleScope(
                    $this->createMock(SalesChannelContext::class)
                )
            )
        );
    }
}
