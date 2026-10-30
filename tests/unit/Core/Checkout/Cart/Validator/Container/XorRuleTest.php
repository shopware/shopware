<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Validator\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\XorRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\Framework\Rule\UnknownConditionRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Core\Test\Stub\Rule\FalseRule;
use Shopware\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(XorRule::class)]
class XorRuleTest extends TestCase
{
    /**
     * @param list<Rule> $remainingRules
     */
    #[DataProvider('missingConditionCases')]
    public function testReplacesMissingConditionsAfterUnserialization(array $remainingRules, bool $expected): void
    {
        $original = new XorRule([new SimpleRule(), ...$remainingRules]);

        // Replace the class name with an unavailable name of the same length to keep the serialized data valid.
        $serialized = str_replace(SimpleRule::class, 'Shopware\Core\Framework\Rule\AbsentRule', serialize($original));
        $rule = Serialization::assertUnserializedInstanceOf(XorRule::class, $serialized);
        static::assertInstanceOf(UnknownConditionRule::class, $rule->getRules()[0]);

        static::assertSame($expected, $rule->match(static::createStub(RuleScope::class)));
    }

    public static function missingConditionCases(): \Generator
    {
        yield 'only the missing condition remains' => [[], false];
        yield 'exactly one remaining condition matches' => [[new TrueRule(), new FalseRule()], true];
        yield 'no remaining condition matches' => [[new FalseRule()], false];
        yield 'two remaining conditions match' => [[new TrueRule(), new TrueRule()], false];
    }

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
                    static::createStub(SalesChannelContext::class)
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
                    static::createStub(SalesChannelContext::class)
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
                    static::createStub(SalesChannelContext::class)
                )
            )
        );
    }
}
