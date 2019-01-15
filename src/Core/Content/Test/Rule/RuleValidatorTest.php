<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\RuleValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Rule\Collector\RuleConditionCollectorInterface;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Rule\Match;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfType;
use Shopware\Core\Framework\Validation\ConstraintViolationException;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class RuleValidatorTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var RepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var RepositoryInterface
     */
    private $conditionRepository;

    /**
     * @var RuleValidator
     */
    private $ruleValidator;

    protected function setUp()
    {
        $this->context = Context::createDefaultContext();
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->ruleValidator = $this->getContainer()->get(RuleValidator::class);
        $this->addMockCollections(new TestConditionCollector());
    }

    public function testWriteRuleWithInconsistentSubChild(): void
    {
        $id = Uuid::uuid4()->getHex();
        $conditionId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test rule',
            'priority' => 1,
            'conditions' => [
                [
                    'type' => MockOptionalStringArrayRule::class,
                    'children' => [
                        [
                            'type' => MockOptionalStringArrayRule::class,
                            'children' => [
                                [
                                    'id' => $conditionId,
                                    'type' => MockIntRule::class,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        try {
            $this->ruleRepository->create([$data], $this->context);
            static::fail('Exception should be thrown');
        } catch (WriteStackException $stackException) {
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertInstanceOf(ConstraintViolationException::class, $exception);
                static::assertCount(1, $exception->getViolations());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('/conditions/' . $conditionId . '/property', $exception->getViolations()->get(0)->getPropertyPath());
            }
        }

        static::assertNull($this->ruleRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
    }

    public function testWriteRuleWithInconsistentRootCondition(): void
    {
        $id = Uuid::uuid4()->getHex();
        $conditionId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test rule',
            'priority' => 1,
            'conditions' => [
                [
                    'id' => $conditionId,
                    'type' => MockIntRule::class,
                    'children' => [
                        [
                            'type' => MockOptionalStringArrayRule::class,
                        ],
                    ],
                ],
            ],
        ];
        try {
            $this->ruleRepository->create([$data], $this->context);
            static::fail('Exception should be thrown');
        } catch (WriteStackException $stackException) {
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertInstanceOf(ConstraintViolationException::class, $exception);
                static::assertCount(1, $exception->getViolations());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('/conditions/' . $conditionId . '/property', $exception->getViolations()->get(0)->getPropertyPath());
            }
        }

        static::assertNull($this->ruleRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
    }

    public function testWriteRuleWithInconsistentCondition(): void
    {
        $id = Uuid::uuid4()->getHex();
        $conditionId1 = Uuid::uuid4()->getHex();
        $conditionId2 = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test rule',
            'priority' => 1,
            'conditions' => [
                [
                    'id' => $conditionId1,
                    'type' => MockIntRule::class,
                    'children' => [
                        [
                            'id' => $conditionId2,
                            'type' => MockIntRule::class,
                        ],
                    ],
                ],
            ],
        ];
        try {
            $this->ruleRepository->create([$data], $this->context);
            static::fail('Exception should be thrown');
        } catch (WriteStackException $stackException) {
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertInstanceOf(ConstraintViolationException::class, $exception);
                static::assertCount(2, $exception->getViolations());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('/conditions/' . $conditionId1 . '/property', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(1)->getMessage());
                static::assertSame('/conditions/' . $conditionId2 . '/property', $exception->getViolations()->get(1)->getPropertyPath());
            }
        }

        static::assertNull($this->ruleRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
    }

    public function testWriteMultiRulesWithOneInconsistentCondition(): void
    {
        $ruleId = Uuid::uuid4()->getHex();
        $ruleId2 = Uuid::uuid4()->getHex();
        $conditionId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $ruleId,
            'name' => 'test rule',
            'priority' => 1,
            'conditions' => [
                [
                    'id' => $conditionId,
                    'type' => MockIntRule::class,
                ],
            ],
        ];

        $data2 = [
            'id' => $ruleId2,
            'name' => 'test rule 2',
            'priority' => 2,
        ];

        try {
            $this->ruleRepository->create([$data, $data2], $this->context);
            static::fail('Exception should be thrown');
        } catch (WriteStackException $stackException) {
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertInstanceOf(ConstraintViolationException::class, $exception);
                static::assertCount(1, $exception->getViolations());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('/conditions/' . $conditionId . '/property', $exception->getViolations()->get(0)->getPropertyPath());
            }
        }
        $result = $this->ruleRepository->read(new ReadCriteria([$ruleId, $ruleId2]), $this->context);

        static::assertNull($result->get($ruleId));
        static::assertNull($result->get($ruleId2));
    }

    public function testWriteConditionWithInconsistentConditions(): void
    {
        $ruleId = Uuid::uuid4()->getHex();
        $this->ruleRepository->create(
            [
                [
                    'id' => $ruleId,
                    'name' => 'test rule',
                    'priority' => 1,
                ],
            ], $this->context
        );
        $conditionId1 = Uuid::uuid4()->getHex();
        $conditionId2 = Uuid::uuid4()->getHex();

        $data = [
            'id' => $conditionId1,
            'ruleId' => $ruleId,
            'type' => MockIntRule::class,
            'children' => [
                [
                    'id' => $conditionId2,
                    'ruleId' => $ruleId,
                    'type' => MockIntRule::class,
                ],
            ],
        ];
        try {
            $this->conditionRepository->create([$data], $this->context);
            static::fail('Exception should be thrown');
        } catch (WriteStackException $stackException) {
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertInstanceOf(ConstraintViolationException::class, $exception);
                static::assertCount(2, $exception->getViolations());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('/conditions/' . $conditionId1 . '/property', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(1)->getMessage());
                static::assertSame('/conditions/' . $conditionId2 . '/property', $exception->getViolations()->get(1)->getPropertyPath());
            }
        }

        static::assertNull($this->conditionRepository->read(new ReadCriteria([$conditionId1]), $this->context)->get($conditionId1));
    }

    public function testWriteConditionWithInconsistentChildCondition(): void
    {
        $ruleId = Uuid::uuid4()->getHex();
        $this->ruleRepository->create(
            [
                [
                    'id' => $ruleId,
                    'name' => 'test rule',
                    'priority' => 1,
                ],
            ], $this->context
        );

        $id = Uuid::uuid4()->getHex();
        $conditionId2 = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'ruleId' => $ruleId,
            'type' => MockOptionalStringArrayRule::class,
            'children' => [
                [
                    'id' => $conditionId2,
                    'ruleId' => $ruleId,
                    'type' => MockIntRule::class,
                ],
            ],
        ];
        try {
            $this->conditionRepository->create([$data], $this->context);
            static::fail('Exception should be thrown');
        } catch (WriteStackException $stackException) {
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertInstanceOf(ConstraintViolationException::class, $exception);
                static::assertCount(1, $exception->getViolations());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('/conditions/' . $conditionId2 . '/property', $exception->getViolations()->get(0)->getPropertyPath());
            }
        }

        static::assertNull($this->conditionRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
    }

    public function testWriteConditionWithInconsistentRootCondition(): void
    {
        $ruleId = Uuid::uuid4()->getHex();
        $this->ruleRepository->create(
            [
                [
                    'id' => $ruleId,
                    'name' => 'test rule',
                    'priority' => 1,
                ],
            ], $this->context
        );

        $conditionId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $conditionId,
            'ruleId' => $ruleId,
            'type' => MockIntRule::class,
            'children' => [
                [
                    'ruleId' => $ruleId,
                    'type' => MockOptionalStringArrayRule::class,
                ],
            ],
        ];
        try {
            $this->conditionRepository->create([$data], $this->context);
            static::fail('Exception should be thrown');
        } catch (WriteStackException $stackException) {
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertInstanceOf(ConstraintViolationException::class, $exception);
                static::assertCount(1, $exception->getViolations());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('/conditions/' . $conditionId . '/property', $exception->getViolations()->get(0)->getPropertyPath());
            }
        }

        static::assertNull($this->conditionRepository->read(new ReadCriteria([$conditionId]), $this->context)->get($conditionId));
    }

    public function testWriteConditionWithAdditionalFields(): void
    {
        $ruleId = Uuid::uuid4()->getHex();
        $this->ruleRepository->create(
            [
                [
                    'id' => $ruleId,
                    'name' => 'test rule',
                    'priority' => 1,
                ],
            ], $this->context
        );

        $conditionId = Uuid::uuid4()->getHex();

        $data = [
            'id' => $conditionId,
            'ruleId' => $ruleId,
            'type' => MockIntRule::class,
            'value' => [
                'property' => 42,
                'invalidProp' => 23,
            ],
        ];
        try {
            $this->conditionRepository->create([$data], $this->context);
            static::fail('Exception should be thrown');
        } catch (WriteStackException $stackException) {
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertInstanceOf(ConstraintViolationException::class, $exception);
                static::assertCount(1, $exception->getViolations());
                static::assertSame('The property "invalidProp" is not allowed.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('/conditions/' . $conditionId . '/invalidProp', $exception->getViolations()->get(0)->getPropertyPath());
            }
        }

        static::assertNull($this->conditionRepository->read(new ReadCriteria([$conditionId]), $this->context)->get($conditionId));
    }

    public function testWriteRuleWithConsistentConditions(): void
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'test rule',
            'priority' => 1,
            'conditions' => [
                [
                    'type' => MockOptionalStringArrayRule::class,
                    'children' => [
                        [
                            'type' => MockOptionalStringArrayRule::class,
                        ],
                    ],
                ],
            ],
        ];

        $this->ruleRepository->create([$data], $this->context);
        static::assertNotNull($this->ruleRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
    }

    public function testWriteConditionWithConsistentChildren(): void
    {
        $ruleId = Uuid::uuid4()->getHex();
        $this->ruleRepository->create(
            [
                [
                    'id' => $ruleId,
                    'name' => 'test rule',
                    'priority' => 1,
                ],
            ], $this->context
        );

        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'ruleId' => $ruleId,
            'type' => MockOptionalStringArrayRule::class,
            'children' => [
                [
                    'ruleId' => $ruleId,
                    'type' => MockOptionalStringArrayRule::class,
                ],
            ],
        ];

        $this->conditionRepository->create([$data], $this->context);
        static::assertNotNull($this->conditionRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
    }

    private function addMockCollections(RuleConditionCollectorInterface $collector)
    {
        $registry = $this->getContainer()->get(RuleConditionRegistry::class);

        $collected = $registry->collect();

        $collected = array_merge($collected, $collector->getClasses());

        $reflectionClass = new \ReflectionClass(RuleConditionRegistry::class);
        $property = $reflectionClass->getProperty('classes');
        $property->setAccessible(true);
        $property->setValue($registry, $collected);
    }
}

class MockOptionalStringArrayRule extends Rule
{
    public function match(RuleScope $scope): Match
    {
        return new Match(true);
    }

    public static function getConstraints(): array
    {
        return [
            'property' => [new ArrayOfType('string')],
        ];
    }
}

class MockIntRule extends Rule
{
    public function match(RuleScope $scope): Match
    {
        return new Match(true);
    }

    public static function getConstraints(): array
    {
        return [
            'property' => [new NotBlank(), new Type('int')],
        ];
    }
}

class TestConditionCollector implements RuleConditionCollectorInterface
{
    public function getClasses(): array
    {
        return [
            MockOptionalStringArrayRule::class,
            MockIntRule::class,
        ];
    }
}
