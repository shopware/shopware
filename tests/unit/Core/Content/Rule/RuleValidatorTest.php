<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Content\Rule\RuleValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleValidator::class)]
class RuleValidatorTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'preValidate'],
            RuleValidator::getSubscribedEvents()
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $expectedViolationPointers
     */
    #[DataProvider('updateConditionValueProvider')]
    public function testItValidatesUpdateConditionValues(array $payload, array $expectedViolationPointers): void
    {
        $context = Context::createDefaultContext();
        $conditionId = Uuid::randomHex();
        $conditionIdBytes = Uuid::fromHexToBytes($conditionId);
        $definition = $this->getRuleConditionDefinition();
        $storedCondition = $this->createCustomerGroupCondition($conditionId);

        $searchResult = new EntitySearchResult(
            RuleConditionDefinition::ENTITY_NAME,
            1,
            new RuleConditionCollection([$storedCondition]),
            null,
            new Criteria([$conditionId]),
            $context
        );

        $ruleConditionRepository = $this->createMock(EntityRepository::class);
        $ruleConditionRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $validator = new RuleValidator(
            Validation::createValidator(),
            new RuleConditionRegistry([new AlwaysValidRule(), new CustomerGroupRule()]),
            $ruleConditionRepository,
            $this->createMock(EntityRepository::class)
        );

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $definition,
                    $payload,
                    ['id' => $conditionIdBytes],
                    EntityExistence::createForEntity(
                        RuleConditionDefinition::ENTITY_NAME,
                        ['id' => $conditionIdBytes]
                    ),
                    '/0'
                ),
            ]
        );

        $validator->preValidate($event);

        $violations = iterator_to_array($event->getExceptions()->getErrors());
        $violationPointers = array_column(array_column($violations, 'source'), 'pointer');

        static::assertSame($expectedViolationPointers, $violationPointers);
    }

    /**
     * @return iterable<string, array{payload: array<string, mixed>, expectedViolationPointers: list<string>}>
     */
    public static function updateConditionValueProvider(): iterable
    {
        yield 'uses explicit null as empty value' => [
            'payload' => [
                'type' => AlwaysValidRule::RULE_NAME,
                'value' => null,
            ],
            'expectedViolationPointers' => [],
        ];

        yield 'uses explicit JSON value' => [
            'payload' => [
                'type' => CustomerGroupRule::RULE_NAME,
                'value' => json_encode([
                    'customerGroupIds' => [Uuid::randomHex()],
                    'operator' => CustomerGroupRule::OPERATOR_EQ,
                ], \JSON_THROW_ON_ERROR),
            ],
            'expectedViolationPointers' => [],
        ];

        yield 'uses stored value when update payload has no value key' => [
            'payload' => [
                'type' => AlwaysValidRule::RULE_NAME,
            ],
            'expectedViolationPointers' => [
                '/0/value/customerGroupIds',
                '/0/value/operator',
            ],
        ];

        yield 'validates explicit null as empty value for required rule fields' => [
            'payload' => [
                'type' => CustomerGroupRule::RULE_NAME,
                'value' => null,
            ],
            'expectedViolationPointers' => [
                '/0/value/customerGroupIds',
                '/0/value/operator',
            ],
        ];
    }

    private function createCustomerGroupCondition(string $id): RuleConditionEntity
    {
        $condition = new RuleConditionEntity();
        $condition->setId($id);
        $condition->setType(CustomerGroupRule::RULE_NAME);
        $condition->setValue([
            'customerGroupIds' => [Uuid::randomHex()],
            'operator' => CustomerGroupRule::OPERATOR_EQ,
        ]);

        return $condition;
    }

    private function getRuleConditionDefinition(): RuleConditionDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [RuleConditionDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $definition = $registry->get(RuleConditionDefinition::class);
        static::assertInstanceOf(RuleConditionDefinition::class, $definition);

        return $definition;
    }
}
