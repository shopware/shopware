<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\DateRangeRule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Validator\Constraints\DateTime as DateTimeConstraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
class DateRangeRuleTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private EntityRepository $ruleRepository;

    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithoutParameters(): void
    {
        $conditionId = Uuid::randomHex();

        try {
            $this->conditionRepository->create([
                [
                    'id' => $conditionId,
                    'type' => (new DateRangeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);

            static::assertSame('/0/value/fromDate', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/toDate', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);

            static::assertSame('/0/value/useTime', $exceptions[2]['source']['pointer']);
            static::assertSame(NotNull::IS_NULL_ERROR, $exceptions[2]['code']);
        }
    }

    public function testValidateWithInvalidFromDateFormat(): void
    {
        foreach ([true, 'Invalid'] as $value) {
            try {
                $this->conditionRepository->create([
                    [
                        'type' => (new DateRangeRule())->getName(),
                        'ruleId' => Uuid::randomHex(),
                        'value' => [
                            'fromDate' => $value,
                            'toDate' => '2018-12-06T10:03:35+00:00',
                            'useTime' => true,
                        ],
                    ],
                ], $this->context);
                static::fail('Exception was not thrown');
            } catch (WriteException $stackException) {
                $exceptions = iterator_to_array($stackException->getErrors());
                static::assertCount(1, $exceptions);
                static::assertSame('/0/value/fromDate', $exceptions[0]['source']['pointer']);
                static::assertSame(DateTimeConstraint::INVALID_FORMAT_ERROR, $exceptions[0]['code']);
            }
        }
    }

    public function testValidateWithInvalidToDateFormat(): void
    {
        foreach ([true, 'Invalid'] as $value) {
            try {
                $this->conditionRepository->create([
                    [
                        'type' => (new DateRangeRule())->getName(),
                        'ruleId' => Uuid::randomHex(),
                        'value' => [
                            'toDate' => $value,
                            'fromDate' => '2018-12-06T10:03:35+00:00',
                            'useTime' => true,
                        ],
                    ],
                ], $this->context);
                static::fail('Exception was not thrown');
            } catch (WriteException $stackException) {
                $exceptions = iterator_to_array($stackException->getErrors());
                static::assertCount(1, $exceptions);
                static::assertSame('/0/value/toDate', $exceptions[0]['source']['pointer']);
                static::assertSame(DateTimeConstraint::INVALID_FORMAT_ERROR, $exceptions[0]['code']);
            }
        }
    }

    public function testValidateWithInvalidUseTime(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new DateRangeRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'toDate' => '2018-12-06T10:03:35+00:00',
                        'fromDate' => '2018-12-06T10:03:35+00:00',
                        'useTime' => 'true',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/useTime', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
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
                    'toDate' => '2018-12-06T10:03:35+00:00',
                    'fromDate' => '2018-12-06T10:03:35+00:00',
                    'useTime' => true,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));

        $this->ruleRepository->delete([['id' => $ruleId]], $this->context);
        $this->conditionRepository->delete([['id' => $id]], $this->context);
    }
}
