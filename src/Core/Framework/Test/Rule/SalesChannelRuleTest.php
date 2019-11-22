<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\SalesChannelRule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class SalesChannelRuleTest extends TestCase
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

    public function testValidateWithMissingSalesChannelIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new SalesChannelRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/salesChannelIds', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithEmptySalesChannelIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new SalesChannelRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'salesChannelIds' => [],
                        'operator' => SalesChannelRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/salesChannelIds', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithStringSalesChannelIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new SalesChannelRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'salesChannelIds' => '0915d54fbf80423c917c61ad5a391b48',
                        'operator' => SalesChannelRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/salesChannelIds', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidSalesChannelIdsUuid(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new SalesChannelRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'salesChannelIds' => [true, 3, null, '0915d54fbf80423c917c61ad5a391b48'],
                        'operator' => SalesChannelRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);
            static::assertSame('/0/value/salesChannelIds', $exceptions[0]['source']['pointer']);
            static::assertSame('/0/value/salesChannelIds', $exceptions[1]['source']['pointer']);
            static::assertSame('/0/value/salesChannelIds', $exceptions[2]['source']['pointer']);

            static::assertSame(ArrayOfUuid::INVALID_TYPE_CODE, $exceptions[0]['code']);
            static::assertSame(ArrayOfUuid::INVALID_TYPE_CODE, $exceptions[1]['code']);
            static::assertSame(ArrayOfUuid::INVALID_TYPE_CODE, $exceptions[2]['code']);
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
        $this->conditionRepository->create(
            [
                [
                    'id' => $conditionIdEq,
                    'type' => (new SalesChannelRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'operator' => Rule::OPERATOR_EQ,
                        'salesChannelIds' => [Uuid::randomHex()],
                    ],
                ],
                [
                    'id' => $conditionIdNEq,
                    'type' => (new SalesChannelRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'operator' => Rule::OPERATOR_EQ,
                        'salesChannelIds' => [Uuid::randomHex()],
                    ],
                ],
            ],
            $this->context
        );

        static::assertCount(
            2,
            $this->conditionRepository->search(
                new Criteria([$conditionIdEq, $conditionIdNEq]),
                $this->context
            )
        );
    }

    public function testValidateWithInvalidOperators(): void
    {
        foreach ([Rule::OPERATOR_LTE, Rule::OPERATOR_GTE, 'Invalid', true, 1.1] as $operator) {
            try {
                $this->conditionRepository->create([
                    [
                        'type' => (new SalesChannelRule())->getName(),
                        'ruleId' => Uuid::randomHex(),
                        'value' => [
                            'operator' => $operator,
                            'salesChannelIds' => [Uuid::randomHex(), Uuid::randomHex()],
                        ],
                    ],
                ], $this->context);
                static::fail('Exception was not thrown');
            } catch (WriteException $stackException) {
                $exceptions = iterator_to_array($stackException->getErrors());
                static::assertCount(1, $exceptions);
                static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
                static::assertSame(Choice::NO_SUCH_CHOICE_ERROR, $exceptions[0]['code']);
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
                'type' => (new SalesChannelRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'salesChannelIds' => [Uuid::randomHex(), Uuid::randomHex()],
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }
}
