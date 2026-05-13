<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\PaymentMethodRule;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class PaymentMethodRuleTest extends TestCase
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

    /**
     * @return array<array<string|bool|array<string, string|array<string>>>>
     */
    public static function matchDataProvider(): array
    {
        return [
            [
                [
                    'operator' => Rule::OPERATOR_EQ,
                    'paymentMethodIds' => [],
                ],
                '965a0713093841ceb86b0f83edd7dab4',
                false,
            ],
            [
                [
                    'operator' => Rule::OPERATOR_EQ,
                    'paymentMethodIds' => ['ff5a0713093841ceb86b0f83edd7dab4'],
                ],
                '965a0713093841ceb86b0f83edd7dab4',
                false,
            ],
            [
                [
                    'operator' => Rule::OPERATOR_NEQ,
                    'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4'],
                ],
                '965a0713093841ceb86b0f83edd7dab4',
                false,
            ],
            [
                [
                    'operator' => Rule::OPERATOR_NEQ,
                    'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
                ],
                'ff5a0713093841ceb86b0f83edd7dab4',
                false,
            ],
            [
                [
                    'operator' => Rule::OPERATOR_EQ,
                    'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4'],
                ],
                '965a0713093841ceb86b0f83edd7dab4',
                true,
            ],
            [
                [
                    'operator' => Rule::OPERATOR_EQ,
                    'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
                ],
                'ff5a0713093841ceb86b0f83edd7dab4',
                true,
            ],
            [
                [
                    'operator' => Rule::OPERATOR_NEQ,
                    'paymentMethodIds' => ['965a0713093841ceb86b0f83edd7dab4', 'ff5a0713093841ceb86b0f83edd7dab4'],
                ],
                'ee5a0713093841ceb86b0f83edd7dab4',
                true,
            ],
        ];
    }

    /**
     * @param array<string, string|array<string>> $ruleProperties
     */
    #[DataProvider('matchDataProvider')]
    public function testMatch(array $ruleProperties, string $paymentMethodId, bool $expected): void
    {
        $paymentMethodRule = new PaymentMethodRule();
        $paymentMethodRule->assign($ruleProperties);

        $paymentMethodEntity = new PaymentMethodEntity();
        $paymentMethodEntity->setId($paymentMethodId);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getPaymentMethod')->willReturn($paymentMethodEntity);

        $ruleScope = new CartRuleScope(
            new Cart('test'),
            $salesChannelContext
        );

        static::assertSame($expected, $paymentMethodRule->match($ruleScope));
    }

    public function testExpectUnsupportedOperatorException(): void
    {
        $paymentMethodRule = new PaymentMethodRule();
        $paymentMethodRule->assign(['operator' => 'FOO', 'paymentMethodIds' => []]);

        $paymentMethodEntity = new PaymentMethodEntity();
        $paymentMethodEntity->setId('965a0713093841ceb86b0f83edd7dab4');

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getPaymentMethod')->willReturn($paymentMethodEntity);

        $ruleScope = new CartRuleScope(
            new Cart('test'),
            $salesChannelContext
        );

        $this->expectExceptionObject(RuleException::unsupportedOperator('FOO', RuleComparison::class));

        $paymentMethodRule->match($ruleScope);
    }
}
