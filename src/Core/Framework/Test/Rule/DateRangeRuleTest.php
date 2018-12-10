<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Validation\ConstraintViolationException;

class DateRangeRuleTest extends TestCase
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour;

    /**
     * @var RepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var RepositoryInterface
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

    public function testValidateWithoutParameters()
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => DateRangeRule::class,
                    'ruleId' => Uuid::uuid4()->getHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(2, $exception->getViolations());
                static::assertStringStartsWith(DateRangeRule::class, $exception->getViolations()->get(0)->getPropertyPath());
                static::assertStringEndsWith(' (fromDate)', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
                static::assertStringEndsWith(' (toDate)', $exception->getViolations()->get(1)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(1)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(1)->getMessage());
            }
        }
    }

    public function testValidateWithoutFromDate()
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => DateRangeRule::class,
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'toDate' => '2018-12-06T10:03:35+00:00',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertStringStartsWith(DateRangeRule::class, $exception->getViolations()->get(0)->getPropertyPath());
                static::assertStringEndsWith(' (fromDate)', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithoutToDate()
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => DateRangeRule::class,
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'fromDate' => '2018-12-06T10:03:35+00:00',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertStringStartsWith(DateRangeRule::class, $exception->getViolations()->get(0)->getPropertyPath());
                static::assertStringEndsWith(' (toDate)', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('c1051bb4-d103-4f74-8988-acbcafc7fdc3', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should not be blank.', $exception->getViolations()->get(0)->getMessage());
            }
        }
    }

    public function testValidateWithInvalidFromDateFormat()
    {
        foreach ([true, 'Invalid'] as $value) {
            try {
                $this->conditionRepository->create([
                    [
                        'type' => DateRangeRule::class,
                        'ruleId' => Uuid::uuid4()->getHex(),
                        'value' => [
                            'fromDate' => $value,
                            'toDate' => '2018-12-06T10:03:35+00:00',
                        ],
                    ],
                ], $this->context);
                static::fail('Exception was not thrown');
            } catch (WriteStackException $stackException) {
                static::assertGreaterThan(0, count($stackException->getExceptions()));
                /** @var ConstraintViolationException $exception */
                foreach ($stackException->getExceptions() as $exception) {
                    static::assertCount(1, $exception->getViolations());
                    static::assertStringStartsWith(DateRangeRule::class, $exception->getViolations()->get(0)->getPropertyPath());
                    static::assertStringEndsWith(' (fromDate)', $exception->getViolations()->get(0)->getPropertyPath());
                    static::assertSame('1a9da513-2640-4f84-9b6a-4d99dcddc628', $exception->getViolations()->get(0)->getCode());
                    static::assertSame('This value is not a valid datetime.', $exception->getViolations()->get(0)->getMessage());
                }
            }
        }
    }

    public function testValidateWithInvalidToDateFormat()
    {
        foreach ([true, 'Invalid'] as $value) {
            try {
                $this->conditionRepository->create([
                    [
                        'type' => DateRangeRule::class,
                        'ruleId' => Uuid::uuid4()->getHex(),
                        'value' => [
                            'toDate' => $value,
                            'fromDate' => '2018-12-06T10:03:35+00:00',
                        ],
                    ],
                ], $this->context);
                static::fail('Exception was not thrown');
            } catch (WriteStackException $stackException) {
                static::assertGreaterThan(0, count($stackException->getExceptions()));
                /** @var ConstraintViolationException $exception */
                foreach ($stackException->getExceptions() as $exception) {
                    static::assertCount(1, $exception->getViolations());
                    static::assertStringStartsWith(DateRangeRule::class, $exception->getViolations()->get(0)->getPropertyPath());
                    static::assertStringEndsWith(' (toDate)', $exception->getViolations()->get(0)->getPropertyPath());
                    static::assertSame('1a9da513-2640-4f84-9b6a-4d99dcddc628', $exception->getViolations()->get(0)->getCode());
                    static::assertSame('This value is not a valid datetime.', $exception->getViolations()->get(0)->getMessage());
                }
            }
        }
    }

    public function testValidateWithInvalidUseTime()
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => DateRangeRule::class,
                    'ruleId' => Uuid::uuid4()->getHex(),
                    'value' => [
                        'toDate' => '2018-12-06T10:03:35+00:00',
                        'fromDate' => '2018-12-06T10:03:35+00:00',
                        'useTime' => 'true',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteStackException $stackException) {
            static::assertGreaterThan(0, count($stackException->getExceptions()));
            /** @var ConstraintViolationException $exception */
            foreach ($stackException->getExceptions() as $exception) {
                static::assertCount(1, $exception->getViolations());
                static::assertStringStartsWith(DateRangeRule::class, $exception->getViolations()->get(0)->getPropertyPath());
                static::assertStringEndsWith(' (useTime)', $exception->getViolations()->get(0)->getPropertyPath());
                static::assertSame('ba785a8c-82cb-4283-967c-3cf342181b40', $exception->getViolations()->get(0)->getCode());
                static::assertSame('This value should be of type bool.', $exception->getViolations()->get(0)->getMessage());
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
                'type' => DateRangeRule::class,
                'ruleId' => $ruleId,
                'value' => [
                    'toDate' => '2018-12-06T10:03:35+00:00',
                    'fromDate' => '2018-12-06T10:03:35+00:00',
                    'useTime' => true,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->read(new ReadCriteria([$id]), $this->context)->get($id));
    }
}
