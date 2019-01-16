<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\RuleValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
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
        $this->addMockRules(new MockIntRule(), new MockOptionalStringArrayRule());
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
                    'type' => (new MockOptionalStringArrayRule())->getName(),
                    'children' => [
                        [
                            'type' => (new MockOptionalStringArrayRule())->getName(),
                            'children' => [
                                [
                                    'id' => $conditionId,
                                    'type' => (new MockIntRule())->getName(),
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
                    'type' => (new MockIntRule())->getName(),
                    'children' => [
                        [
                            'type' => (new MockOptionalStringArrayRule())->getName(),
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
                    'type' => (new MockIntRule())->getName(),
                    'children' => [
                        [
                            'id' => $conditionId2,
                            'type' => (new MockIntRule())->getName(),
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
                    'type' => (new MockIntRule())->getName(),
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
            'type' => (new MockIntRule())->getName(),
            'children' => [
                [
                    'id' => $conditionId2,
                    'ruleId' => $ruleId,
                    'type' => (new MockIntRule())->getName(),
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
            'type' => (new MockOptionalStringArrayRule())->getName(),
            'children' => [
                [
                    'id' => $conditionId2,
                    'ruleId' => $ruleId,
                    'type' => (new MockIntRule())->getName(),
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
            'type' => (new MockIntRule())->getName(),
            'children' => [
                [
                    'ruleId' => $ruleId,
                    'type' => (new MockOptionalStringArrayRule())->getName(),
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
            'type' => (new MockIntRule())->getName(),
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
                    'type' => (new MockOptionalStringArrayRule())->getName(),
                    'children' => [
                        [
                            'type' => (new MockOptionalStringArrayRule())->getName(),
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
            'type' => (new MockOptionalStringArrayRule())->getName(),
            'children' => [
                [
                    'ruleId' => $ruleId,
                    'type' => (new MockOptionalStringArrayRule())->getName(),
                ],
            ],
        ];

        $this->conditionRepository->create([$data], $this->context);
        static::assertNotNull($this->conditionRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
    }

    private function addMockRules(Rule ...$rules)
    {
        $registry = $this->getContainer()->get(RuleConditionRegistry::class);
        $reflectionClass = new \ReflectionClass(RuleConditionRegistry::class);
        $property = $reflectionClass->getProperty('rules');
        $property->setAccessible(true);
        $taggedRules = $property->getValue($registry);

        foreach ($rules as $rule) {
            $taggedRules[$rule->getName()] = $rule;
        }

        $property->setValue($registry, $taggedRules);
    }
}

class MockOptionalStringArrayRule extends Rule
{
    public function match(RuleScope $scope): Match
    {
        return new Match(true);
    }

    public function getConstraints(): array
    {
        return [
            'property' => [new ArrayOfType('string')],
        ];
    }

    public function getName(): string
    {
        return 'mockOptionalString';
    }
}

class MockIntRule extends Rule
{
    public function match(RuleScope $scope): Match
    {
        return new Match(true);
    }

    public function getConstraints(): array
    {
        return [
            'property' => [new NotBlank(), new Type('int')],
        ];
    }

    public function getName(): string
    {
        return 'mockInt';
    }
}
