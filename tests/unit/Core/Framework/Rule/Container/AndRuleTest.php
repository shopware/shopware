<?php declare(strict_types=1);

namespace Shopware\Tests\Core\Framework\Rule\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Cart\Common\FalseRule;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;

/**
 * @covers \Shopware\Core\Framework\Rule\Container\AndRule
 * @covers \Shopware\Core\Framework\Rule\Container\Container
 * @internal
 */
class AndRuleTest extends TestCase
{
    /**
     * @dataProvider cases
     */
    public function test_rule_logic(AndRule $rule, bool $matching): void
    {
        $scope = $this->createMock(RuleScope::class);
        static::assertSame($matching, $rule->match($scope));
    }

    public function test_and_rule_name_is_still_the_same(): void
    {
        static::assertSame('andContainer', (new AndRule())->getName());
    }

    public function test_i_can_add_rules_afterwards(): void
    {
        $rule = new AndRule([new TrueRule()]);
        $rule->addRule(new TrueRule());

        static::assertEquals([new TrueRule(), new TrueRule()], $rule->getRules());

        $rule->setRules([new FalseRule()]);
        static::assertEquals([new FalseRule()], $rule->getRules());
    }

    public function test_constraints_are_still_the_same(): void
    {
        static::assertEquals(
            ['rules' => [new ArrayOfType(Rule::class)]],
            (new AndRule())->getConstraints()
        );
    }

    public function cases(): \Generator
    {
        yield 'Test with single matching rule' => [
            new AndRule([new TrueRule()]),
            true
        ];

        yield 'Test with multiple matching rule' => [
            new AndRule([
                new TrueRule(),
                new TrueRule(),
            ]),
            true
        ];

        yield 'Test with single not matching rule' => [
            new AndRule([new FalseRule()]),
            false
        ];

        yield 'Test with multiple not matching rule' => [
            new AndRule([
                new TrueRule(),
                new FalseRule(),
            ]),
            false
        ];

        yield 'Test with matching and not matching rule' => [
            new AndRule([
                new TrueRule(),
                new FalseRule(),
            ]),
            false
        ];
    }
}
