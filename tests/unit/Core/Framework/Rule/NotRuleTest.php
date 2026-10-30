<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\NotRule;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\Framework\Rule\UnknownConditionRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Assert\Serialization;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(NotRule::class)]
class NotRuleTest extends TestCase
{
    public function testMissingConditionAfterUnserializationDoesNotMatch(): void
    {
        $original = new NotRule([new SimpleRule()]);

        // Replace the class name with an unavailable name of the same length to keep the serialized data valid.
        $serialized = str_replace(SimpleRule::class, 'Shopware\Core\Framework\Rule\AbsentRule', serialize($original));
        $rule = Serialization::assertUnserializedInstanceOf(NotRule::class, $serialized);
        static::assertInstanceOf(UnknownConditionRule::class, $rule->getRules()[0]);

        static::assertFalse($rule->match(new CheckoutRuleScope(static::createStub(SalesChannelContext::class))));
    }

    public function testUnknownConditionDoesNotMatch(): void
    {
        $rule = new NotRule([new UnknownConditionRule(['_name' => 'unknownPluginRule'])]);

        static::assertFalse($rule->match(new CheckoutRuleScope(static::createStub(SalesChannelContext::class))));
    }

    public function testUnsupportedValue(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(RuleException::class);
        }
        $rule = new NotRule();
        $salesChannelContext = static::createStub(SalesChannelContext::class);
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
