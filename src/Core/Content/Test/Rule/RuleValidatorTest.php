<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Rule;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\RuleValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('business-ops')]
class RuleValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var RuleValidator
     */
    private $ruleValidator;

    private Context $context;

    /**
     * @var RuleConditionRegistry|MockObject
     */
    private $conditionRegistry;

    /**
     * @var RuleConditionDefinition
     */
    private $ruleConditionDefinition;

    /**
     * @var EntityRepository
     */
    private $ruleConditionRepository;

    /**
     * @var EntityRepository
     */
    private $ruleRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->ruleConditionRepository = $this->getContainer()->get('rule_condition.repository');
    }

    public function testItCanCreateRulesOnValidInput(): void
    {
        $ruleId = Uuid::randomHex();
        $conditionId = Uuid::randomHex();

        $this->ruleRepository->create([
            [
                'id' => $ruleId,
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

        $criteria = new Criteria([$ruleId]);
        $criteria->addAssociation('conditions');

        $rule = $this->ruleRepository->search($criteria, $this->context)->getEntities()->get($ruleId);
        static::assertEquals(1, $rule->getConditions()->count());
        static::assertNotNull($rule->getConditions()->get($conditionId));
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
            static::assertEquals('/0/conditions/1/type', $violations[0]['source']['pointer']);
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
            static::assertEquals('/0/conditions/0/children/0/type', $violations[0]['source']['pointer']);
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
            static::assertEquals('/0/conditions/0/type', $violations[0]['source']['pointer']);
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
            static::assertEquals('/0/conditions/0/value/count', $violations[0]['source']['pointer']);
            static::assertEquals('/0/conditions/0/value/operator', $violations[1]['source']['pointer']);
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
            static::assertEquals('/0/conditions/0/value/thisFieldIsNotValid', $violations[0]['source']['pointer']);
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
            'operator' => '=',
            'count' => 12,
        ];

        $this->ruleConditionRepository->update([
            [
                'id' => $customerOderCountId,
                'value' => $newValue,
            ],
        ], $this->context);

        $updatedCondition = $this->ruleConditionRepository->search(new Criteria([$customerOderCountId]), $this->context)
            ->getEntities()->get($customerOderCountId);

        static::assertEquals('customerOrderCount', $updatedCondition->getType());
        static::assertEquals($newValue, $updatedCondition->getValue());
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
}
