<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Rule;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Shopware\Core\Content\Rule\RuleValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class RuleValidatorTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var RuleValidator
     */
    private $ruleValidator;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var RuleConditionRegistry|MockObject
     */
    private $conditionRegistry;

    /**
     * @var RuleConditionDefinition
     */
    private $ruleConditionDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleConditionRepository;

    /**
     * @var EntityRepositoryInterface
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
            $violations = $we->getExceptions()[0]->toArray();

            static::assertCount(1, $violations);
            static::assertEquals('/0/conditions/1/type', $violations[0]['propertyPath']);
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
            $violations = $we->getExceptions()[0]->toArray();

            static::assertCount(1, $violations);
            static::assertEquals('/0/conditions/0/children/0/type', $violations[0]['propertyPath']);
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
            $violations = $we->getExceptions()[0]->toArray();

            static::assertCount(1, $violations);
            static::assertEquals('/0/conditions/0/type', $violations[0]['propertyPath']);
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
            $violations = $we->getExceptions()[0]->toArray();

            static::assertCount(2, $violations);
            static::assertEquals('/0/conditions/0/value/count', $violations[0]['propertyPath']);
            static::assertEquals('/0/conditions/0/value/operator', $violations[1]['propertyPath']);
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
            $violations = $we->getExceptions()[0]->toArray();

            static::assertCount(1, $violations);
            static::assertEquals('/0/conditions/0/value/thisFieldIsNotValid', $violations[0]['propertyPath']);
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
            $violations = array_column($we->getExceptions()[0]->toArray(), 'propertyPath');

            static::assertCount(2, $violations);
            static::assertContains('/0/value/count', $violations);
            static::assertContains('/0/value/operator', $violations);
        }
    }
}
