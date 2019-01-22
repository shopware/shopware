<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\ConstraintViolationException;

class CartAmountRuleTest extends TestCase
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
                    'type' => (new CartAmountRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/amount', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
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
                'type' => (new CartAmountRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'amount' => 1.1,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
    }

    public function testValidateWithMissingAmount()
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new CartAmountRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
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
                static::assertSame('/conditions/' . $conditionId . '/amount', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithStringAmount()
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
                'type' => (new CartAmountRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'amount' => '0.1',
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
    }

    public function testValidateWithIntAmount()
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
                'type' => (new CartAmountRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'amount' => 3,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
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
                    'type' => (new CartAmountRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'amount' => 1.1,
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
                [
                    'id' => $conditionIdNEq,
                    'type' => (new CartAmountRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'amount' => 1.1,
                        'operator' => Rule::OPERATOR_NEQ,
                    ],
                ],
                [
                    'id' => $conditionIdLTE,
                    'type' => (new CartAmountRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'amount' => 1.1,
                        'operator' => Rule::OPERATOR_LTE,
                    ],
                ],
                [
                    'id' => $conditionIdGTE,
                    'type' => (new CartAmountRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'amount' => 1.1,
                        'operator' => Rule::OPERATOR_GTE,
                    ],
                ],
            ], $this->context
        );

        static::assertCount(
            4, $this->conditionRepository->read(
            new ReadCriteria([$conditionIdEq, $conditionIdNEq, $conditionIdLTE, $conditionIdGTE]), $this->context
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
                    'type' => (new CartAmountRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'amount' => 0.1,
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
                'type' => (new CartAmountRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'amount' => 0.1,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
    }
}
