<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Shopware\Core\Test\Stub\Rule\FalseRule;
use Shopware\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(AndRule::class)]
#[CoversClass(Container::class)]
class AndRuleTest extends TestCase
{
    #[DataProvider('cases')]
    public function testRuleLogic(AndRule $rule, bool $matching): void
    {
        $scope = $this->createMock(RuleScope::class);
        static::assertSame($matching, $rule->match($scope));
    }

    public function testAndRuleNameIsStillTheSame(): void
    {
        static::assertSame('andContainer', (new AndRule())->getName());
    }

    public function testICanAddRulesAfterwards(): void
    {
        $rule = new AndRule([new TrueRule()]);
        $rule->addRule(new TrueRule());

        static::assertEquals([new TrueRule(), new TrueRule()], $rule->getRules());

        $rule->setRules([new FalseRule()]);
        static::assertEquals([new FalseRule()], $rule->getRules());
    }

    public function testConstraintsAreStillTheSame(): void
    {
        static::assertEquals(
            ['rules' => [new ArrayOfType(Rule::class)]],
            (new AndRule())->getConstraints()
        );
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
