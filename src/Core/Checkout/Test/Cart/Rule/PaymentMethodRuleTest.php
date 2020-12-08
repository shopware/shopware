<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class PaymentMethodRuleTest extends TestCase
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

    public function testValidateWithMissingPaymentMethodIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new PaymentMethodRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/paymentMethodIds', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithEmptyPaymentMethodIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new PaymentMethodRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'paymentMethodIds' => [],
                        'operator' => PaymentMethodRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/paymentMethodIds', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithStringPaymentMethodIds(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new PaymentMethodRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'paymentMethodIds' => '0915d54fbf80423c917c61ad5a391b48',
                        'operator' => PaymentMethodRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/paymentMethodIds', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidPaymentMethodIdsUuid(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new PaymentMethodRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'paymentMethodIds' => [true, 3, null, '0915d54fbf80423c917c61ad5a391b48'],
                        'operator' => PaymentMethodRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(3, $exceptions);
            static::assertSame('/0/value/paymentMethodIds', $exceptions[0]['source']['pointer']);
            static::assertSame('/0/value/paymentMethodIds', $exceptions[1]['source']['pointer']);
            static::assertSame('/0/value/paymentMethodIds', $exceptions[2]['source']['pointer']);

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
                    'type' => (new PaymentMethodRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'operator' => Rule::OPERATOR_EQ,
                        'paymentMethodIds' => [Uuid::randomHex()],
                    ],
                ],
                [
                    'id' => $conditionIdNEq,
                    'type' => (new PaymentMethodRule())->getName(),
                    'ruleId' => $ruleId,
                    'value' => [
                        'operator' => Rule::OPERATOR_EQ,
                        'paymentMethodIds' => [Uuid::randomHex()],
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
                        'type' => (new PaymentMethodRule())->getName(),
                        'ruleId' => Uuid::randomHex(),
                        'value' => [
                            'operator' => $operator,
                            'paymentMethodIds' => [Uuid::randomHex(), Uuid::randomHex()],
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
                'type' => (new PaymentMethodRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => Rule::OPERATOR_EQ,
                    'paymentMethodIds' => [Uuid::randomHex(), Uuid::randomHex()],
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function matchDataProvider(): array
    {
        return [
            [
                [
                    'operator' => PaymentMethodRule::OPERATOR_EQ,
                    'paymentMethodIds' => [],
                ],
                '965a0713093841ceb86b0f83edd7dab4',
                false,
            ],
            [
                [
                    'operator' => PaymentMethodRule::OPERATOR_EQ,
                    'paymentMethodIds' => ['ff5a0713093841ceb86b0f83edd7dab4'],
                ],
                '965a0713093841ceb86b0f83edd7dab4',
                false,
            ],
            [
                [
                    'operator' => PaymentMethodRule::OPERATOR_NEQ,
                    'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4'],
                ],
                '965a0713093841ceb86b0f83edd7dab4',
                false,
            ],
            [
                [
                    'operator' => PaymentMethodRule::OPERATOR_NEQ,
                    'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
                ],
                'ff5a0713093841ceb86b0f83edd7dab4',
                false,
            ],
            [
                [
                    'operator' => PaymentMethodRule::OPERATOR_EQ,
                    'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4'],
                ],
                '965a0713093841ceb86b0f83edd7dab4',
                true,
            ],
            [
                [
                    'operator' => PaymentMethodRule::OPERATOR_EQ,
                    'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
                ],
                'ff5a0713093841ceb86b0f83edd7dab4',
                true,
            ],
            [
                [
                    'operator' => PaymentMethodRule::OPERATOR_NEQ,
                    'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
                ],
                'ee5a0713093841ceb86b0f83edd7dab4',
                true,
            ],
        ];
    }

    /**
     * @dataProvider matchDataProvider
     */
    public function testMatch(array $ruleProperties, string $paymentMethodId, bool $expected): void
    {
        $paymentMethodRule = new PaymentMethodRule();
        $paymentMethodRule->assign($ruleProperties);

        $paymentMethodEntity = $this->createMock(PaymentMethodEntity::class);
        $paymentMethodEntity->method('getId')->willReturn($paymentMethodId);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getPaymentMethod')->willReturn($paymentMethodEntity);

        $ruleScope = new CartRuleScope(
            $this->createMock(Cart::class),
            $salesChannelContext
        );

        static::assertSame($expected, $paymentMethodRule->match($ruleScope));
    }

    public function testExpectUnsupportedOperatorException(): void
    {
        $paymentMethodRule = new PaymentMethodRule();
        $paymentMethodRule->assign(['operator' => 'FOO', 'paymentMethodsIds' => []]);

        $paymentMethodEntity = $this->createMock(PaymentMethodEntity::class);
        $paymentMethodEntity->method('getId')->willReturn(Uuid::randomHex());

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getPaymentMethod')->willReturn($paymentMethodEntity);

        $ruleScope = new CartRuleScope(
            $this->createMock(Cart::class),
            $salesChannelContext
        );

        $this->expectException(UnsupportedOperatorException::class);
        $this->expectExceptionMessage('Unsupported operator FOO in Shopware\\Core\\Checkout\\Cart\\Rule\\PaymentMethodRule');

        $paymentMethodRule->match($ruleScope);
    }
}
