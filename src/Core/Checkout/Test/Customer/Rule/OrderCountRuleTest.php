<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\OrderCountRule;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('business-ops')]
class OrderCountRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepository $ruleRepository;

    private EntityRepository $conditionRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testValidateWithMissingValues(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new OrderCountRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/count', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/operator', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testValidateWithEmptyValues(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new OrderCountRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'operator' => OrderCountRule::OPERATOR_EQ,
                        'count' => null,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/count', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithStringValue(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new OrderCountRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'operator' => OrderCountRule::OPERATOR_EQ,
                        'count' => '4',
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/count', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidValue(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new OrderCountRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'operator' => OrderCountRule::OPERATOR_EQ,
                        'count' => true,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/count', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
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
                'type' => (new OrderCountRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'operator' => OrderCountRule::OPERATOR_EQ,
                    'count' => 6,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testRuleDoesNotMatchWithWrongScope(): void
    {
        $rule = new OrderCountRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_LT]);

        $result = $rule->match($this->getMockForAbstractClass(RuleScope::class));

        static::assertFalse($result);
    }

    /**
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(string $operator, bool $isMatching, ?int $orderCount, int $ruleOrderCount, bool $noCustomer = false): void
    {
        $rule = new OrderCountRule();
        $rule->assign(['count' => $ruleOrderCount, 'operator' => $operator]);

        $scope = $this->createMock(CheckoutRuleScope::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $orderCollection = new OrderCollection();
        $customer = new CustomerEntity();
        $customer->setOrderCount($orderCount ?? 0);

        if ($noCustomer) {
            $customer = null;
        }

        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $entity = new OrderEntity();
        $entity->setUniqueIdentifier('test');
        $orderCollection->add($entity);

        $scope->method('getSalesChannelContext')
            ->willReturn($salesChannelContext);

        static::assertSame($isMatching, $rule->match($scope));
    }

    /**
     * @return \Traversable<string, array<string|bool|int>>
     */
    public static function getMatchValues(): \Traversable
    {
        yield 'operator_eq / no match / greater value' => [Rule::OPERATOR_EQ, false, 100, 50];
        yield 'operator_eq / match / equal value' => [Rule::OPERATOR_EQ, true, 50, 50];
        yield 'operator_eq / no match / lower value' => [Rule::OPERATOR_EQ, false, 10, 50];
        yield 'operator_eq / no match / no customer' => [Rule::OPERATOR_EQ, false, 100, 50, true];

        yield 'operator_gt / match / greater value' => [Rule::OPERATOR_GT, true, 100, 50];
        yield 'operator_gt / no match / equal value' => [Rule::OPERATOR_GT, false, 50, 50];
        yield 'operator_gt / no match / lower value' => [Rule::OPERATOR_GT, false, 10, 50];
        yield 'operator_gt / no match / no customer' => [Rule::OPERATOR_GT, false, 100, 50, true];

        yield 'operator_gte / match / greater value' => [Rule::OPERATOR_GTE, true, 100, 50];
        yield 'operator_gte / match / equal value' => [Rule::OPERATOR_GTE, true, 50, 50];
        yield 'operator_gte / no match / lower value' => [Rule::OPERATOR_GTE, false, 10, 50];
        yield 'operator_gte / no match / no customer' => [Rule::OPERATOR_GTE, false, 100, 50, true];

        yield 'operator_lt / no match / greater value' => [Rule::OPERATOR_LT, false, 100, 50];
        yield 'operator_lt / no match / equal value' => [Rule::OPERATOR_LT, false, 50, 50];
        yield 'operator_lt / match / lower value' => [Rule::OPERATOR_LT, true, 10, 50];
        yield 'operator_lt / no match / no customer' => [Rule::OPERATOR_LT, false, 10, 50, true];

        yield 'operator_lte / no match / greater value' => [Rule::OPERATOR_LTE, false, 100, 50];
        yield 'operator_lte / match / equal value' => [Rule::OPERATOR_LTE, true, 50, 50];
        yield 'operator_lte / match / lower value' => [Rule::OPERATOR_LTE, true, 10, 50];
        yield 'operator_lte / no match / no customer' => [Rule::OPERATOR_LTE, false, 10, 50, true];

        yield 'operator_neq / match / greater value' => [Rule::OPERATOR_NEQ, true, 100, 50];
        yield 'operator_neq / no match / equal value' => [Rule::OPERATOR_NEQ, false, 50, 50];
        yield 'operator_neq / match / lower value' => [Rule::OPERATOR_NEQ, true, 10, 50];

        yield 'operator_neq / match / no customer' => [Rule::OPERATOR_NEQ, true, 100, 50, true];
    }
}
