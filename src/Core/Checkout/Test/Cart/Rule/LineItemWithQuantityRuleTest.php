<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\ConstraintViolationException;

class LineItemWithQuantityRuleTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

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

    protected function setUp()
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithMissingParameters()
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(2, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/id', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('/conditions/' . $conditionId . '/quantity', $exception->getViolations()->get(1)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(1)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(1)->getMessage());
            }
        }
    }

    public function testValidateWithoutOptionalOperator()
    {
        $ruleId = Uuid::uuid4()->getHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::uuid4()->getHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new LineItemWithQuantityRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'quantity' => 1,
                    'id' => '0915d54fbf80423c917c61ad5a391b48',
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testValidateWithMissingQuantity()
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/quantity', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithMissingId()
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/id', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithInvalidTypeId()
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'id' => true,
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/id', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('This value should be of type string.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithInvalidIdUuidFormat()
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'id' => '12345',
                        'quantity' => 3,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/id', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('The string "12345" is not a valid uuid.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithStringQuantity()
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => '3',
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/quantity', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('ba785a8c-82cb-4283-967c-3cf342181b40', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should be of type int.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testAvailableOperators()
    {
        $ruleId = Uuid::uuid4()->getHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $conditionIdEq = Uuid::uuid4()->getHex();
        $conditionIdNEq = Uuid::uuid4()->getHex();
        $conditionIdLTE = Uuid::uuid4()->getHex();
        $conditionIdGTE = Uuid::uuid4()->getHex();
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
            ], $this->context
        );

        static::assertCount(
            4, $this->conditionRepository->search(
            new Criteria([$conditionIdEq, $conditionIdNEq, $conditionIdLTE, $conditionIdGTE]), $this->context
        )
        );
    }

    public function testValidateWithInvalidOperator()
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new LineItemWithQuantityRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'id' => '0915d54fbf80423c917c61ad5a391b48',
                        'quantity' => 3,
                        'operator' => 'Invalid',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/operator', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('8e179f1b-97aa-4560-a02f-2a8b42e49df7', $exception->getViolations()->get(0)->getCode());
                static::assertSame('The value you selected is not a valid choice.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testIfRuleIsConsistent()
    {
        $ruleId = Uuid::uuid4()->getHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::uuid4()->getHex();
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
