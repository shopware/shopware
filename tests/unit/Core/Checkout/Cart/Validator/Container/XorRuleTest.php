<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Validator\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Container\XorRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\Common\FalseRule;
use Shopware\Tests\Unit\Core\Checkout\Cart\Common\TrueRule;

/**
 * @covers \Shopware\Core\Framework\Rule\Container\XorRule
 *
 * @internal
 */
class XorRuleTest extends TestCase
{
    public function testSingleTrueRule(): void
    {
        $rule = new XorRule([
            new FalseRule(),
            new TrueRule(),
            new FalseRule(),
        ]);

        static::assertTrue(
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

        static::assertFalse(
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

        static::assertFalse(
            $rule->match(
                new CheckoutRuleScope(
                    $this->createMock(SalesChannelContext::class)
                )
            )
        );
    }
}
