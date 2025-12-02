<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Symfony\Component\Validator\Constraints\DateTime as DateTimeConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Timezone;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class DateRangeRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->conditionRepository = static::getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithoutParameters(): void
    {
        $conditionId = Uuid::randomHex();

        $exception = new WriteException();
        $exception->add(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    'This value should not be blank.',
                    'This value should not be blank.',
                    ['{{ value }}' => 'null'],
                    null,
                    '/value/fromDate',
                    null,
                    null,
                    NotBlank::IS_BLANK_ERROR,
                ),
                new ConstraintViolation(
                    'This value should not be blank.',
                    'This value should not be blank.',
                    ['{{ value }}' => 'null'],
                    null,
                    '/value/toDate',
                    null,
                    null,
                    NotBlank::IS_BLANK_ERROR,
                ),
                new ConstraintViolation(
                    'This value should not be null.',
                    'This value should not be null.',
                    ['{{ value }}' => 'null'],
                    null,
                    '/value/useTime',
                    null,
                    null,
                    NotNull::IS_NULL_ERROR,
                ),
            ]),
            '/0',
        ));
        static::expectExceptionObject($exception);

        $this->conditionRepository->create([
            [
                'id' => $conditionId,
                'type' => (new DateRangeRule())->getName(),
                'ruleId' => Uuid::randomHex(),
            ],
        ], $this->context);
    }

    public function testValidateWithInvalidFromDateFormat(): void
    {
        foreach ([true, 'Invalid'] as $value) {
            $exception = new WriteException();
            $exception->add(new WriteConstraintViolationException(
                new ConstraintViolationList([
                    new ConstraintViolation(
                        'This value is not a valid datetime.',
                        'This value is not a valid datetime.',
                        ['{{ value }}' => $value, '{{ format }}' => '"Y-m-d\TH:i:s"'],
                        null,
                        '/value/fromDate',
                        $value,
                        null,
                        DateTimeConstraint::INVALID_FORMAT_ERROR,
                    ),
                ]),
                '/0',
            ));
            static::expectExceptionObject($exception);

            $this->conditionRepository->create([
                [
                    'type' => (new DateRangeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'fromDate' => $value,
                        'toDate' => '2018-12-06T10:03:35',
                        'useTime' => true,
                    ],
                ],
            ], $this->context);
        }
    }

    public function testValidateWithInvalidToDateFormat(): void
    {
        foreach ([true, 'Invalid'] as $value) {
            $exception = new WriteException();
            $exception->add(new WriteConstraintViolationException(
                new ConstraintViolationList([
                    new ConstraintViolation(
                        'This value is not a valid datetime.',
                        'This value is not a valid datetime.',
                        ['{{ value }}' => $value, '{{ format }}' => '"Y-m-d\TH:i:s"'],
                        null,
                        '/value/toDate',
                        $value,
                        null,
                        DateTimeConstraint::INVALID_FORMAT_ERROR,
                    ),
                ]),
                '/0',
            ));
            static::expectExceptionObject($exception);

            $this->conditionRepository->create([
                [
                    'type' => (new DateRangeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'toDate' => $value,
                        'fromDate' => '2018-12-06T10:03:35',
                        'useTime' => true,
                    ],
                ],
            ], $this->context);
        }
    }

    public function testValidateWithInvalidUseTime(): void
    {
        $exception = new WriteException();
        $exception->add(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    'This value should be of type bool.',
                    'This value should be of type {{ type }}.',
                    ['{{ value }}' => '"true"', '{{ type }}' => 'bool'],
                    null,
                    '/value/useTime',
                    'true',
                    null,
                    Type::INVALID_TYPE_ERROR,
                ),
            ]),
            '/0',
        ));
        static::expectExceptionObject($exception);

        $this->conditionRepository->create([
            [
                'type' => (new DateRangeRule())->getName(),
                'ruleId' => Uuid::randomHex(),
                'value' => [
                    'toDate' => '2018-12-06T10:03:35',
                    'fromDate' => '2018-12-06T10:03:35',
                    'useTime' => 'true',
                ],
            ],
        ], $this->context);
    }

    public function testValidateWithInvalidTimezone(): void
    {
        $exception = new WriteException();
        $exception->add(new WriteConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    'This value is not a valid timezone.',
                    'This value is not a valid timezone.',
                    ['count' => 1],
                    null,
                    '/value/timezone',
                    'Invalid/Timezone',
                    null,
                    Timezone::TIMEZONE_IDENTIFIER_ERROR,
                ),
            ]),
            '/0',
        ));
        static::expectExceptionObject($exception);

        $this->conditionRepository->create([
            [
                'type' => (new DateRangeRule())->getName(),
                'ruleId' => Uuid::randomHex(),
                'value' => [
                    'toDate' => '2018-12-06T10:03:35',
                    'fromDate' => '2018-12-06T10:03:35',
                    'useTime' => true,
                    'timezone' => 'Invalid/Timezone',
                ],
            ],
        ], $this->context);
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            $this->context
        );

        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new DateRangeRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'toDate' => '2018-12-06T10:03:35',
                    'fromDate' => '2018-12-06T10:03:35',
                    'useTime' => true,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));

        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }
}
