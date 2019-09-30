<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class LineItemWithQuantityRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $conditionRepository;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithMissingParameters(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(3, $exception->getViolations());

                static::assertSame('/0/value/id', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());

                static::assertSame('/0/value/quantity', $exception->getViolations()->get(1)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(1)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(1)->getMessage());

                static::assertSame('/0/value/operator', $exception->getViolations()->get(2)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(2)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(2)->getMessage());
            }
        }
    }

    public function testValidateWithMissingQuantity(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/quantity', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithMissingId(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/id', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithInvalidTypeId(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'id' => true,
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/id', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('This value should be of type string.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithInvalidIdUuidFormat(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'id' => '12345',
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/id', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('The string "12345" is not a valid uuid.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithStringQuantity(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => '3',
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/quantity', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(Type::INVALID_TYPE_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should be of type int.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testAvailableOperators(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $conditionIdEq = Uuid::randomHex();
        $conditionIdNEq = Uuid::randomHex();
        $conditionIdLTE = Uuid::randomHex();
        $conditionIdGTE = Uuid::randomHex();
        $this->conditionRepository->create(
            [
                [
                    'id' => $conditionIdEq,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
                [
                    'id' => $conditionIdNEq,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
                [
                    'id' => $conditionIdLTE,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
                [
                    'id' => $conditionIdGTE,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ],
            $this->context
        );

        static::assertCount(
            4,
            $this->conditionRepository->search(
                new Criteria([$conditionIdEq, $conditionIdNEq, $conditionIdLTE, $conditionIdGTE]),
                $this->context
            )
        );
    }

    public function testValidateWithInvalidOperator(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => 3,
                        'operator' => 'Invalid',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/operator', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(Choice::NO_SUCH_CHOICE_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('The value you selected is not a valid choice.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new LineItemWithQuantityRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'id' => '0915d54fbf80423c917c61ad5a391b48',
                    'quantity' => 3,
                    'operator' => Rule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }
}
