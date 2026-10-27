<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\Framework\Rule\UnknownConditionRule;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Core\Test\Stub\Rule\FalseRule;
use Shopware\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(AndRule::class)]
class AndRuleTest extends TestCase
{
    /**
     * @param list<Rule> $remainingRules
     */
    #[DataProvider('missingConditionCases')]
    public function testReplacesMissingConditionsAfterUnserialization(array $remainingRules, bool $expected): void
    {
        $original = new AndRule([new SimpleRule(), ...$remainingRules]);

        // Replace the class name with an unavailable name of the same length to keep the serialized data valid.
        $serialized = str_replace(SimpleRule::class, 'Shopware\Core\Framework\Rule\AbsentRule', serialize($original));
        $rule = Serialization::assertUnserializedInstanceOf(AndRule::class, $serialized);
        static::assertInstanceOf(UnknownConditionRule::class, $rule->getRules()[0]);

        static::assertSame($expected, $rule->match(static::createStub(RuleScope::class)));
    }

    public static function missingConditionCases(): \Generator
    {
        yield 'only the missing condition remains' => [[], false];
        yield 'remaining condition matches' => [[new TrueRule()], false];
        yield 'remaining condition does not match' => [[new FalseRule()], false];
    }

    #[DataProvider('cases')]
    public function testRuleLogic(AndRule $rule, bool $matching): void
    {
        $scope = static::createStub(RuleScope::class);
        static::assertSame($matching, $rule->match($scope));
    }

    public function testAndRuleNameIsStillTheSame(): void
    {
        static::assertSame('andContainer', (new AndRule())->getName());
    }

    public static function cases(): \Generator
    {
        yield 'Test with single matching rule' => [
            new AndRule([new TrueRule()]),
            true,
        ];

        yield 'Test with multiple matching rule' => [
            new AndRule([
                new TrueRule(),
                new TrueRule(),
            ]),
            true,
        ];

        yield 'Test with single not matching rule' => [
            new AndRule([new FalseRule()]),
            false,
        ];

        yield 'Test with multiple not matching rule' => [
            new AndRule([
                new TrueRule(),
                new FalseRule(),
            ]),
            false,
        ];

        yield 'Test with matching and not matching rule' => [
            new AndRule([
                new TrueRule(),
                new FalseRule(),
            ]),
            false,
        ];
    }
}
