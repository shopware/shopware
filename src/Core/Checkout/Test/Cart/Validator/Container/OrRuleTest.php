<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Validator\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Test\Cart\Common\FalseRule;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Match;

class OrRuleTest extends TestCase
{
    public function testTrue(): void
    {
        $rule = new OrRule([
            new TrueRule(),
            new FalseRule(),
        ]);

        static::assertEquals(
            new Match(true),
            $rule->match(
                new CheckoutRuleScope(
                    $this->createMock(CheckoutContext::class)
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

        static::assertEquals(
            new Match(false),
            $rule->match(
                new CheckoutRuleScope(
                    $this->createMock(CheckoutContext::class)
                )
            )
        );
    }
}
