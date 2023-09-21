<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Validator\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Container\NotRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\Common\FalseRule;
use Shopware\Tests\Unit\Core\Checkout\Cart\Common\TrueRule;

/**
 * @covers \Shopware\Core\Framework\Rule\Container\NotRule
 *
 * @internal
 */
class NotRuleTest extends TestCase
{
    public function testTrue(): void
    {
        $rule = new NotRule([
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

    public function testExceptionByMultipleRules(): void
    {
        $this->expectException(\RuntimeException::class);
        new NotRule([
            new FalseRule(),
            new FalseRule(),
            new FalseRule(),
        ]);
    }

    public function testFalse(): void
    {
        $rule = new NotRule([
            new TrueRule(),
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
