<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class RuleValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $ruleConditionRepository;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->ruleConditionRepository = static::getContainer()->get('rule_condition.repository');
    }

    /**
     * @param array<array<string, string|array<string, mixed>>> $conditions
     */
    #[DataProvider('providerRuleCases')]
    public function testItCanCreateRulesOnValidInput(string $conditionId, array $conditions): void
    {
        $ruleId = Uuid::randomHex();

        $this->ruleRepository->create([
            [
                'id' => $ruleId,
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => $conditions,
            ],
        ], $this->context);

        $criteria = new Criteria([$ruleId]);
        $criteria->addAssociation('conditions');

        /** @var RuleEntity $rule */
        $rule = $this->ruleRepository->search($criteria, $this->context)->getEntities()->get($ruleId);

        /** @var RuleConditionCollection $ruleConditions */
        $ruleConditions = $rule->getConditions();
        static::assertCount(1, $ruleConditions);
        static::assertNotNull($ruleConditions->get($conditionId));
    }

    public function testItThrowsIfTypeIsMissing(): void
    {
        $ruleData = [
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'type' => 'customerOrderCount',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                    ], [
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->ruleRepository->create($ruleData, $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());

            static::assertCount(1, $violations);
            static::assertSame('/0/conditions/1/type', $violations[0]['source']['pointer']);
        }
    }

    public function testWithChildren(): void
    {
        $ruleData = [
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'type' => 'customerOrderCount',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                        'children' => [
                            [
                                'value' => [
                                    'operator' => '=',
                                    'count' => 6,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->ruleRepository->create($ruleData, $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());

            static::assertCount(1, $violations);
            static::assertSame('/0/conditions/0/children/0/type', $violations[0]['source']['pointer']);
        }
    }

    public function testItThrowsIfTypeIsInvalid(): void
    {
        $ruleData = [
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'type' => 'someTypeThatIsHopefullyNotRegistered',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->ruleRepository->create($ruleData, $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());

            static::assertCount(1, $violations);
            static::assertSame('/0/conditions/0/type', $violations[0]['source']['pointer']);
        }
    }

    public function testItThrowsIfValueIsMissing(): void
    {
        $ruleData = [
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'type' => 'customerOrderCount',
                    ],
                ],
            ],
        ];

        try {
            $this->ruleRepository->create($ruleData, $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());

            static::assertCount(2, $violations);
            static::assertSame('/0/conditions/0/value/count', $violations[0]['source']['pointer']);
            static::assertSame('/0/conditions/0/value/operator', $violations[1]['source']['pointer']);
        }
    }

    public function testItThrowsIfValueContainsInvalidField(): void
    {
        $ruleData = [
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'type' => 'customerOrderCount',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                            'thisFieldIsNotValid' => true,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->ruleRepository->create($ruleData, $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());

            static::assertCount(1, $violations);
            static::assertSame('/0/conditions/0/value/thisFieldIsNotValid', $violations[0]['source']['pointer']);
        }
    }

    public function testItCanUpdateValueOnly(): void
    {
        $customerOderCountId = Uuid::randomHex();

        $this->ruleRepository->create([
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'id' => $customerOderCountId,
                        'type' => 'customerOrderCount',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                    ],
                ],
            ],
        ], $this->context);

        $newValue = [
            'count' => 12,
            'operator' => '=',
        ];

        $this->ruleConditionRepository->update([
            [
                'id' => $customerOderCountId,
                'value' => $newValue,
            ],
        ], $this->context);

        /** @var RuleConditionEntity $updatedCondition */
        $updatedCondition = $this->ruleConditionRepository->search(new Criteria([$customerOderCountId]), $this->context)
            ->getEntities()->get($customerOderCountId);

        static::assertSame('customerOrderCount', $updatedCondition->getType());
        static::assertSame($newValue, $updatedCondition->getValue());
    }

    public function testItThrowsIfNewTypeMismatchesValue(): void
    {
        $customerOrderCountId = Uuid::randomHex();

        $this->ruleRepository->create([
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'id' => $customerOrderCountId,
                        'type' => 'customerOrderCount',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                    ],
                ],
            ],
        ], $this->context);

        try {
            $this->ruleConditionRepository->update([
                [
                    'id' => $customerOrderCountId,
                    'type' => 'orContainer',
                ],
            ], $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());
            $pointer = array_column(array_column($violations, 'source'), 'pointer');

            static::assertCount(2, $pointer);
            static::assertContains('/0/value/count', $pointer);
            static::assertContains('/0/value/operator', $pointer);
        }
    }

    public function testItCanClearValueWhenConditionTypeChanges(): void
    {
        $customerGroupConditionId = Uuid::randomHex();

        $this->ruleRepository->create([
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'id' => $customerGroupConditionId,
                        'type' => CustomerGroupRule::RULE_NAME,
                        'value' => [
                            'customerGroupIds' => [Uuid::randomHex()],
                            'operator' => CustomerGroupRule::OPERATOR_EQ,
                        ],
                    ],
                ],
            ],
        ], $this->context);

        $this->ruleConditionRepository->update([
            [
                'id' => $customerGroupConditionId,
                'type' => AlwaysValidRule::RULE_NAME,
                'value' => null,
            ],
        ], $this->context);

        $updatedCondition = $this->ruleConditionRepository->search(new Criteria([$customerGroupConditionId]), $this->context)
            ->getEntities()->get($customerGroupConditionId);

        static::assertInstanceOf(RuleConditionEntity::class, $updatedCondition);
        static::assertSame(AlwaysValidRule::RULE_NAME, $updatedCondition->getType());
        static::assertNull($updatedCondition->getValue());
    }

    public function testATypeAndAValueWrittenInTwoWritesOfOneRequestAreCheckedTogether(): void
    {
        $conditionId = Uuid::randomHex();

        $this->ruleRepository->create([
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'id' => $conditionId,
                        'type' => 'customerOrderCount',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                    ],
                ],
            ],
        ], $this->context);

        try {
            static::getContainer()->get(SyncService::class)->sync([
                new SyncOperation('type', 'rule_condition', SyncOperation::ACTION_UPSERT, [[
                    'id' => $conditionId,
                    'type' => CustomerGroupRule::RULE_NAME,
                    'value' => ['customerGroupIds' => [Uuid::randomHex()], 'operator' => CustomerGroupRule::OPERATOR_EQ],
                ]]),
                new SyncOperation('value', 'rule_condition', SyncOperation::ACTION_UPSERT, [[
                    'id' => $conditionId,
                    'value' => ['operator' => '=', 'count' => 6],
                ]]),
            ], $this->context, new SyncBehavior());
            static::fail('Expected the write to be rejected.');
        } catch (WriteException $exception) {
            static::assertSame(
                ['/value/0/value/count', '/value/0/value/customerGroupIds'],
                array_column(array_column(iterator_to_array($exception->getErrors()), 'source'), 'pointer')
            );
        }
    }

    /**
     * @return iterable<string, array{string, array<array<string, string|array<string, mixed>>>}>
     */
    public static function providerRuleCases(): iterable
    {
        $conditionId = Uuid::randomHex();
        yield 'customerOrderCount rule' => [$conditionId, [
            [
                'id' => $conditionId,
                'type' => 'customerOrderCount',
                'value' => [
                    'operator' => '=',
                    'count' => 6,
                ],
            ],
        ]];

        yield 'orderCustomField rule' => [$conditionId, [
            [
                'id' => $conditionId,
                'type' => 'orderCustomField',
                'value' => [
                    'operator' => '=',
                    'selectedField' => Uuid::randomHex(),
                    'selectedFieldSet' => Uuid::randomHex(),
                    'renderedFieldValue' => 'string',
                    'renderedField' => [
                        'type' => 'text',
                    ],
                ],
            ],
        ]];

        yield 'customerCustomField rule' => [$conditionId, [
            [
                'id' => $conditionId,
                'type' => 'orderCustomField',
                'value' => [
                    'operator' => '=',
                    'selectedField' => Uuid::randomHex(),
                    'selectedFieldSet' => Uuid::randomHex(),
                    'renderedFieldValue' => 'string',
                    'renderedField' => [
                        'type' => 'text',
                    ],
                ],
            ],
        ]];
    }
}
