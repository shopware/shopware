<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Validator\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Rule\FalseRule;
use Shopware\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[CoversClass(OrRule::class)]
class OrRuleTest extends TestCase
{
    public function testTrue(): void
    {
        $rule = new OrRule([
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

    public function testFalse(): void
    {
        $rule = new OrRule([
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
}
