<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Rule\Container;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\Container;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\Framework\Rule\UnknownConditionRule;
use Shopware\Core\Framework\Struct\ArrayStruct;
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
    public function testUnavailableChildrenAreReplacedDuringUnserialization(): void
    {
        $original = new AndRule([new SimpleRule(), new TrueRule()]);
        $serialized = str_replace(SimpleRule::class, 'Shopware\Core\Framework\Rule\AbsentRule', serialize($original));
        $container = Serialization::assertUnserializedInstanceOf(AndRule::class, $serialized);

        static::assertEquals([new UnknownConditionRule(), new TrueRule()], $container->getRules());
        static::assertFalse($container->match(static::createStub(RuleScope::class)));
        static::assertEquals($container, Serialization::assertUnserializedInstanceOf(AndRule::class, serialize($container)));
    }

    public function testUnserializationPreservesSubclassAndInheritedState(): void
    {
        $original = new ContainerTestRule([new TrueRule()], 'plugin state');
        $original->addExtension('plugin', new ArrayStruct(['enabled' => true]));
        $serialized = serialize($original);

        $container = Serialization::assertUnserializedInstanceOf(ContainerTestRule::class, $serialized);

        static::assertEquals($original, $container);
        static::assertSame($serialized, serialize($container));
        static::assertTrue($container->match(static::createStub(RuleScope::class)));
    }

    public function testSubclassCanEvaluateRulesDirectlyAfterUnserialization(): void
    {
        $original = new ContainerTestRule([new SimpleRule()], 'plugin state');
        $serialized = str_replace(SimpleRule::class, 'Shopware\Core\Framework\Rule\AbsentRule', serialize($original));

        $container = Serialization::assertUnserializedInstanceOf(ContainerTestRule::class, $serialized);

        static::assertInstanceOf(UnknownConditionRule::class, $container->getRules()[0]);
        static::assertFalse($container->match(static::createStub(RuleScope::class)));
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

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ContainerTestRule extends Container
{
    final public const RULE_NAME = 'containerTestRule';

    /**
     * @param list<Rule> $rules
     */
    public function __construct(array $rules, private readonly string $pluginState)
    {
        parent::__construct($rules);
    }

    public function getConfig(): RuleConfig
    {
        return new RuleConfig();
    }

    public function match(RuleScope $scope): bool
    {
        foreach ($this->rules as $rule) {
            if (!$rule->match($scope)) {
                return false;
            }
        }

        return $this->pluginState === 'plugin state';
    }
}
