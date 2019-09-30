<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\OrderCountRule;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class OrderCountRuleTest extends TestCase
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

    public function testMatchWithEquals(): void
    {
        $rule = new OrderCountRule();
        $rule->assign(['count' => 1, 'operator' => Rule::OPERATOR_EQ]);

        $scope = $this->createTestScope();

        static::assertTrue($rule->match($scope));
    }

    public function testMatchWithNotEquals(): void
    {
        $rule = new OrderCountRule();
        $rule->assign(['count' => 1, 'operator' => Rule::OPERATOR_NEQ]);

        $scope = $this->createTestScope();

        static::assertFalse($rule->match($scope));
    }

    public function testMatchWithLowerThan(): void
    {
        $rule = new OrderCountRule();
        $rule->assign(['count' => 1, 'operator' => Rule::OPERATOR_LT]);

        $scope = $this->createTestScope();

        static::assertFalse($rule->match($scope));
    }

    public function testMatchWithGreaterThan(): void
    {
        $rule = new OrderCountRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_GT]);

        $scope = $this->createTestScope();

        static::assertFalse($rule->match($scope));
    }

    public function testMatchWithLowerEquals(): void
    {
        $rule = new OrderCountRule();
        $rule->assign(['count' => 1, 'operator' => Rule::OPERATOR_LTE]);

        $scope = $this->createTestScope();

        static::assertTrue($rule->match($scope));
    }

    public function testMatchWithGreaterEquals(): void
    {
        $rule = new OrderCountRule();
        $rule->assign(['count' => 1, 'operator' => Rule::OPERATOR_GTE]);

        $scope = $this->createTestScope();

        static::assertTrue($rule->match($scope));
    }

    private function createTestScope(): CheckoutRuleScope
    {
        $scope = $this->createMock(CheckoutRuleScope::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);
        $orderCollection = new OrderCollection();

        $orderCollection->add($this->createMock(OrderEntity::class));

        $scope->method('getSalesChannelContext')
            ->willReturn($salesChannelContext);
        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $customer->method('getOrderCount')
            ->willReturn(1);

        return $scope;
    }
}
