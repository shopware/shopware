<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Rule\DaysSinceOrderPlacedRule;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\RuleValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DaysSinceOrderPlacedRule::class)]
#[Group('rules')]
class DaysSinceOrderPlacedRuleTest extends TestCase
{
    public function testMatchesTheOrderDateUsingCalendarDays(): void
    {
        $order = static::createStub(OrderEntity::class);
        $order->method('getOrderDate')->willReturn(new \DateTimeImmutable('2024-01-01T23:59:00+00:00'));

        $scope = static::createStub(FlowRuleScope::class);
        $scope->method('getOrder')->willReturn($order);
        $scope->method('getCurrentTime')->willReturn(new \DateTimeImmutable('2024-01-31T00:01:00+00:00'));

        $rule = new DaysSinceOrderPlacedRule();
        $rule->assign(['daysPassed' => 30, 'operator' => Rule::OPERATOR_LTE]);

        static::assertTrue($rule->match($scope));
    }

    public function testDoesNotMatchAnOrderOutsideTheConfiguredWindow(): void
    {
        $order = static::createStub(OrderEntity::class);
        $order->method('getOrderDate')->willReturn(new \DateTimeImmutable('2023-12-31T23:59:00+00:00'));

        $scope = static::createStub(FlowRuleScope::class);
        $scope->method('getOrder')->willReturn($order);
        $scope->method('getCurrentTime')->willReturn(new \DateTimeImmutable('2024-01-31T00:01:00+00:00'));

        $rule = new DaysSinceOrderPlacedRule();
        $rule->assign(['daysPassed' => 30, 'operator' => Rule::OPERATOR_LTE]);

        static::assertFalse($rule->match($scope));
    }

    public function testDoesNotMatchOutsideFlowRuleScope(): void
    {
        $rule = new DaysSinceOrderPlacedRule();
        $rule->assign(['daysPassed' => 30, 'operator' => Rule::OPERATOR_LTE]);

        static::assertFalse($rule->match(static::createStub(RuleScope::class)));
    }

    public function testRequiresAnIntegerDayCountAndExposesAnIntegerField(): void
    {
        $rule = new DaysSinceOrderPlacedRule();

        static::assertEquals(RuleConstraints::int(), $rule->getConstraints()['daysPassed']);
        static::assertEquals(RuleConstraints::numericOperators(false), $rule->getConstraints()['operator']);
        static::assertSame([
            'name' => 'daysPassed',
            'type' => 'int',
            'config' => ['unit' => RuleConfig::UNIT_TIME],
        ], $rule->getConfig()->getData()['fields']['daysPassed']);
    }

    /**
     * @return iterable<string, array{float|string|null}>
     */
    public static function invalidDayCounts(): iterable
    {
        yield 'missing required value' => [null];
        yield 'fractional day count' => [30.5];
        yield 'numeric string' => ['30'];
    }

    #[DataProvider('invalidDayCounts')]
    public function testRuleValidatorRejectsMissingOrNonIntegerDayCounts(float|string|null $daysPassed): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [RuleConditionDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
        $definition = $registry->get(RuleConditionDefinition::class);
        $primaryKey = ['id' => Uuid::randomBytes()];

        $validator = new RuleValidator(
            Validation::createValidator(),
            new RuleConditionRegistry([new DaysSinceOrderPlacedRule()]),
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class)
        );

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand(
                    $definition,
                    [
                        'type' => DaysSinceOrderPlacedRule::RULE_NAME,
                        'value' => json_encode(['operator' => Rule::OPERATOR_LTE, 'daysPassed' => $daysPassed], \JSON_THROW_ON_ERROR),
                    ],
                    $primaryKey,
                    EntityExistence::createForEntity(RuleConditionDefinition::ENTITY_NAME, $primaryKey),
                    '/0'
                ),
            ]
        );

        $validator->preValidate($event);

        $violations = iterator_to_array($event->getExceptions()->getErrors());

        static::assertSame(['/0/value/daysPassed'], array_column(array_column($violations, 'source'), 'pointer'));
    }
}
