<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\NotRule;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(NotRule::class)]
class NotRuleTest extends TestCase
{
    public function testUnsupportedValue(): void
    {
        $this->expectException(UnsupportedValueException::class);
        $rule = new NotRule();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $rule->match(new CheckoutRuleScope($salesChannelContext));
    }

    public function testAddRuleOnlyAllowsOneRule(): void
    {
        $this->expectException(\RuntimeException::class);

        $rule = new NotRule();
        $rule->addRule(new SimpleRule());
        $rule->addRule(new SimpleRule());
    }

    public function testSetRulesOnlyAllowsOneRule(): void
    {
        $this->expectException(\RuntimeException::class);

        $rule = new NotRule();
        $rule->setRules([new SimpleRule(), new SimpleRule()]);
    }
}
