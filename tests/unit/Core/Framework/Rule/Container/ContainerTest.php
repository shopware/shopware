<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Shopware\Core\Test\Assert\Serialization;
use Shopware\Core\Test\Stub\Rule\FalseRule;
use Shopware\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(Container::class)]
class ContainerTest extends TestCase
{
    public function testUnavailableChildrenAreExcludedWithoutChangingSerializedData(): void
    {
        $original = new AndRule([new SimpleRule(), new TrueRule()]);
        $serialized = str_replace(SimpleRule::class, 'Shopware\Core\Framework\Rule\AbsentRule', serialize($original));
        $container = Serialization::assertUnserializedInstanceOf(AndRule::class, $serialized);

        static::assertEquals([new TrueRule()], $container->getRules());
        static::assertSame($serialized, serialize($container));
    }

    public function testICanAddRulesAfterwards(): void
    {
        $rule = self::createContainer([new TrueRule()]);
        $rule->addRule(new TrueRule());

        static::assertEquals([new TrueRule(), new TrueRule()], $rule->getRules());

        $rule->setRules([new FalseRule()]);
        static::assertEquals([new FalseRule()], $rule->getRules());
    }

    public function testConstraintsAreStillTheSame(): void
    {
        static::assertEquals(
            ['rules' => [new ArrayOfType(Rule::class)]],
            self::createContainer()->getConstraints()
        );
    }

    /**
     * @param list<Rule> $rules
     */
    private static function createContainer(array $rules = []): Container
    {
        return new class($rules) extends Container {
            final public const RULE_NAME = 'containerTest';

            public function match(RuleScope $scope): bool
            {
                return true;
            }
        };
    }
}
