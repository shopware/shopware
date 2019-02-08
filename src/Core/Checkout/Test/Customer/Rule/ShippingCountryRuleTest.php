<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Rule\ShippingCountryRule;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\ConstraintViolationException;

class ShippingCountryRuleTest extends TestCase
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

    protected function setUp(): void
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
                    'type' => (new ShippingCountryRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/countryIds', $exception->getViolations()->get(0)->getPropertyPath());
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
                'type' => (new ShippingCountryRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'countryIds' => [Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex()],
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testValidateWithMissingCountryIds()
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new ShippingCountryRule())->getName(),
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
                static::assertSame('/conditions/' . $conditionId . '/countryIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithEmptyCountryIds()
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new ShippingCountryRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'operator' => Rule::OPERATOR_EQ,
                        'countryIds' => [],
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/countryIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithInvalidCountryIdsUuid()
    {
        $conditionId = Uuid::uuid4()->getHex();
        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new ShippingCountryRule())->getName(),
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'operator' => Rule::OPERATOR_EQ,
                        'countryIds' => ['INVALID-UUID', true, 3],
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(3, $exception->getViolations());
                static::assertSame('/conditions/' . $conditionId . '/countryIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('The value "INVALID-UUID" is not a valid uuid.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('The value "1" is not a valid uuid.', $exception->getViolations()->get(1)->getMessage());
                static::assertSame('The value "3" is not a valid uuid.', $exception->getViolations()->get(2)->getMessage());
            }
        }
    }

    public function testValidateWithValidOperators()
    {
        $ruleId = Uuid::uuid4()->getHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $conditionIdEq = Uuid::uuid4()->getHex();
        $conditionIdNEq = Uuid::uuid4()->getHex();
        $this->conditionRepository->create(
            [
                [
                    'id' => $conditionIdEq,
                    'type' => (new ShippingCountryRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'countryIds' => [Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex()],
                        'operator' => Rule::OPERATOR_EQ,
                    ],
                ],
                [
                    'id' => $conditionIdNEq,
                    'type' => (new ShippingCountryRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'countryIds' => [Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex()],
                        'operator' => Rule::OPERATOR_NEQ,
                    ],
                ],
            ], $this->context
        );

        static::assertCount(
            2, $this->conditionRepository->search(
            new Criteria([$conditionIdEq, $conditionIdNEq]), $this->context
        )
        );
    }

    public function testValidateWithInvalidOperators()
    {
        $conditionId = Uuid::uuid4()->getHex();
        foreach ([Rule::OPERATOR_LTE, Rule::OPERATOR_GTE, 'Invalid'] as $operator) {
            try {
                $this->conditionRepository->create([
                    [
                        'id' => $conditionId,
                        'type' => (new ShippingCountryRule())->getName(),
                        'ruleId' => Uuid::uuid4()->getHex(),
                        'value' => [
                            'countryIds' => [Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex()],
                            'operator' => $operator,
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
                'type' => (new ShippingCountryRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'countryIds' => [Uuid::uuid4()->getHex(), Uuid::uuid4()->getHex()],
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }
}
