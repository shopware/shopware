<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Currency;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Currency\Rule\CurrencyRule;
use Symfony\Component\Validator\Constraints\NotBlank;

class CurrencyRuleTest extends TestCase
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

    public function testValidateWithMissingCurrencyIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CurrencyRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'operator' => CurrencyRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/currencyIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithEmptyCurrencyIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CurrencyRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'currencyIds' => [],
                        'operator' => CurrencyRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/currencyIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame(NotBlank::IS_BLANK_ERROR, $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithStringCurrencyIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CurrencyRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'currencyIds' => '0915d54fbf80423c917c61ad5a391b48',
                        'operator' => CurrencyRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertSame('/0/value/currencyIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('This value should be of type array.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithInvalidArrayCurrencyIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CurrencyRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'currencyIds' => [true, 3, null, '0915d54fbf80423c917c61ad5a391b48'],
                        'operator' => CurrencyRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(3, $exception->getViolations());
                static::assertSame('/0/value/currencyIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('The value "1" is not a valid uuid.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('The value "3" is not a valid uuid.', $exception->getViolations()->get(1)->getMessage());
                static::assertSame('The value "" is not a valid uuid.', $exception->getViolations()->get(2)->getMessage());
            }
        }
    }

    public function testValidateWithInvalidCurrencyIdsUuid(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new CurrencyRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'currencyIds' => ['Invalid', '1234abcd'],
                        'operator' => CurrencyRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var WriteConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(2, $exception->getViolations());
                static::assertSame('/0/value/currencyIds', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('The value "Invalid" is not a valid uuid.', $exception->getViolations()->get(0)->getMessage());
                static::assertSame('The value "1234abcd" is not a valid uuid.', $exception->getViolations()->get(1)->getMessage());
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
                'type' => (new CurrencyRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'currencyIds' => [Uuid::randomHex(), Uuid::randomHex()],
                    'operator' => CurrencyRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }
}
