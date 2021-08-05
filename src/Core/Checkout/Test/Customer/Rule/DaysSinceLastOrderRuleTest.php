<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\DaysSinceLastOrderRule;
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
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

class DaysSinceLastOrderRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;
    use OrderFixture;

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

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    private DaysSinceLastOrderRule $rule;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->context = Context::createDefaultContext();
        $this->stateMachineRegistry = $this->getContainer()->get(StateMachineRegistry::class);
        $this->rule = new DaysSinceLastOrderRule(self::getTestTimestamp());
    }

    public function testValidateWithMissingValues(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new DaysSinceLastOrderRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/daysPassed', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);

            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithStringValue(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new DaysSinceLastOrderRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'daysPassed' => '10',
                        'operator' => DaysSinceLastOrderRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(1, $exceptions);
            static::assertSame('/0/value/daysPassed', $exceptions[0]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[0]['code']);
        }
    }

    public function testValidateWithInvalidValue(): void
    {
        try {
            $this->conditionRepository->create([
                [
                    'type' => (new DaysSinceLastOrderRule())->getName(),
                    'ruleId' => Uuid::randomHex(),
                    'value' => [
                        'daysPassed' => false,
                        'operator' => DaysSinceLastOrderRule::OPERATOR_EQ,
                    ],
                ],
            ], $this->context);
            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/daysPassed', $exceptions[0]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[0]['code']);

            static::assertSame('/0/value/daysPassed', $exceptions[1]['source']['pointer']);
            static::assertSame(Type::INVALID_TYPE_ERROR, $exceptions[1]['code']);
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
                'type' => (new DaysSinceLastOrderRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'daysPassed' => 10,
                    'operator' => DaysSinceLastOrderRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        static::assertNotNull($this->conditionRepository->search(new Criteria([$id]), $this->context)->get($id));
    }

    public function testRuleDoesNotMatchWithWrongScope(): void
    {
        $rule = new DaysSinceLastOrderRule(self::getTestTimestamp());
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_LT]);

        $result = $rule->match($this->getMockForAbstractClass(RuleScope::class));

        static::assertFalse($result);
    }

    public function testRuleMatchesWithDayBefore(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);
        $customer->method('getLastOrderDate')
            ->willReturn((self::getTestTimestamp())->modify('-1 day'));

        $rule = new DaysSinceLastOrderRule(self::getTestTimestamp());
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($rule->match(new CheckoutRuleScope($checkoutContext)));
    }

    public function testRuleMatchesWithDayBeforePlusOneMinute59(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);

        $dateTime = (self::getTestTimestamp())->setTime(11, 59);
        $orderDate = clone $dateTime;
        $orderDate->modify('-1 day +1 minute');

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);
        $customer->method('getLastOrderDate')
            ->willReturn($orderDate);

        $rule = new DaysSinceLastOrderRule($dateTime);
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($rule->match(new CheckoutRuleScope($checkoutContext)));
    }

    public function testRuleDoesNotMatchWithSameDay(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);
        $customer->method('getLastOrderDate')
            ->willReturn((self::getTestTimestamp())->setTime(0, 0));

        $rule = new DaysSinceLastOrderRule(self::getTestTimestamp());
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($rule->match(new CheckoutRuleScope($checkoutContext)));
    }

    public function testRuleDoesNotMatchOnSameDayInLastMinute(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);
        $customer->method('getLastOrderDate')
            ->willReturn((self::getTestTimestamp())->setTime(23, 59));

        $rule = new DaysSinceLastOrderRule(self::getTestTimestamp());
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($rule->match(new CheckoutRuleScope($checkoutContext)));
    }

    public function testRuleMatchesWithDayBeforePlusOneMinute(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);
        $customer->method('getLastOrderDate')
            ->willReturn((self::getTestTimestamp())->modify('-1 day')->modify('+1 minute'));

        $rule = new DaysSinceLastOrderRule(self::getTestTimestamp());
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($rule->match(new CheckoutRuleScope($checkoutContext)));
    }

    public function testRuleMatchesWithDayBeforeMinusOneMinute(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);
        $customer->method('getLastOrderDate')
            ->willReturn((self::getTestTimestamp())->modify('-1 day')->modify('-1 minute'));

        $rule = new DaysSinceLastOrderRule(self::getTestTimestamp());
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($rule->match(new CheckoutRuleScope($checkoutContext)));
    }

    public function testRuleMatchesWithSameDayButLater(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);
        $customer->method('getLastOrderDate')
            ->willReturn((self::getTestTimestamp())->modify('-30 minutes'));

        $rule = new DaysSinceLastOrderRule(self::getTestTimestamp());
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($rule->match(new CheckoutRuleScope($checkoutContext)));

        $rule->assign(['daysPassed' => 0, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($rule->match(new CheckoutRuleScope($checkoutContext)));
    }

    public function testWithRealCustomerEntity(): void
    {
        $scope = $this->createRealTestScope();

        $rule = new DaysSinceLastOrderRule(self::getTestTimestamp());
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($rule->match($scope));
    }

    public function testCustomerMetaFieldSubscriber(): void
    {
        /** @var EntityRepositoryInterface $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');
        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        $defaultContext = Context::createDefaultContext();
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $defaultContext);

        $orderRepository->create($orderData, $defaultContext);

        $this->stateMachineRegistry->transition(
            new Transition(
                'order',
                $orderId,
                StateMachineTransitionActions::ACTION_PROCESS,
                'stateId',
            ),
            Context::createDefaultContext()
        );

        $this->stateMachineRegistry->transition(
            new Transition(
                'order',
                $orderId,
                StateMachineTransitionActions::ACTION_COMPLETE,
                'stateId',
            ),
            Context::createDefaultContext()
        );

        /** @var CustomerCollection|CustomerEntity[] $result */
        $result = $customerRepository->search(
            new Criteria([$orderData[0]['orderCustomer']['customer']['id']]),
            $defaultContext
        );

        static::assertSame(1, $result->first()->getOrderCount());
        static::assertNotNull($result->first()->getLastOrderDate());
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_LTE,
            Rule::OPERATOR_GTE,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_GT,
            Rule::OPERATOR_LT,
            Rule::OPERATOR_EMPTY,
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        static::assertArrayHasKey('daysPassed', $ruleConstraints, 'Constraint daysPassed not found in Rule');
        $daysPassed = $ruleConstraints['daysPassed'];
        static::assertEquals(new NotBlank(), $daysPassed[0]);
        static::assertEquals(new Type('int'), $daysPassed[1]);
    }

    /**
     * @dataProvider getMatchValues
     */
    public function testRuleMatching(string $operator, bool $isMatching, int $daysPassed, ?\DateTime $day): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createMock(CustomerEntity::class);

        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);
        $customer->method('getLastOrderDate')
            ->willReturn($day);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['daysPassed' => $daysPassed, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    public function getMatchValues(): array
    {
        $dayTest = self::getTestTimestamp()->modify('-30 minutes');

        return [
            'operator_oq / not match / day passed / day' => [Rule::OPERATOR_EQ, false, 1, $dayTest],
            'operator_oq / match / day passed / day' => [Rule::OPERATOR_EQ, true, 0, $dayTest],
            'operator_neq / match / day passed / day' => [Rule::OPERATOR_NEQ, true, 1, $dayTest],
            'operator_neq / not match / day passed/ day' => [Rule::OPERATOR_NEQ, false, 0, $dayTest],
            'operator_lte_lt / not match / day passed / day' => [Rule::OPERATOR_LTE, false, -1, $dayTest],
            'operator_lte_lt / match / day passed/ day ' => [Rule::OPERATOR_LTE, true, 1,  $dayTest],
            'operator_lte_e / match / day passed/ day ' => [Rule::OPERATOR_LTE, true, 0, $dayTest],
            'operator_gte_gt / not match / day passed/ day' => [Rule::OPERATOR_GTE, false, 1, $dayTest],
            'operator_gte_gt / match / day passed / day' => [Rule::OPERATOR_GTE, true, -1, $dayTest],
            'operator_gte_e / match / day passed / day' => [Rule::OPERATOR_GTE, true, 0, $dayTest],
            'operator_lt / not match / day passed / day' => [Rule::OPERATOR_LT, false, 0, $dayTest],
            'operator_lt / match / day passed / day' => [Rule::OPERATOR_LT, true, 1,  $dayTest],
            'operator_gt / not match / day passed / day' => [Rule::OPERATOR_GT, false, 1, $dayTest],
            'operator_gt / match / day passed / day' => [Rule::OPERATOR_GT, true, -1, $dayTest],
            'operator_empty / not match / day passed/ day' => [Rule::OPERATOR_EMPTY, false, 0, $dayTest],
            'operator_empty / match / day passed / day' => [Rule::OPERATOR_EMPTY, true, 0, null],
        ];
    }

    private function createRealTestScope(): CheckoutRuleScope
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = $this->createTestOrderAndReturnCustomer();

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);

        return new CheckoutRuleScope($checkoutContext);
    }

    private function createTestOrderAndReturnCustomer(): CustomerEntity
    {
        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        $orderRepository = $this->getContainer()->get('order.repository');

        $orderId = Uuid::randomHex();
        $defaultContext = Context::createDefaultContext();

        $orderData = array_map(static function (array $order): array {
            $order['orderDateTime'] = self::getTestTimestamp();

            return $order;
        }, $this->getOrderData($orderId, $defaultContext));

        $orderRepository->create($orderData, $defaultContext);
        $criteria = new Criteria([$orderData[0]['orderCustomer']['customer']['id']]);

        $result = $customerRepository->search($criteria, $defaultContext);

        return $result->first();
    }

    private static function getTestTimestamp(): \DateTimeInterface
    {
        return new \DateTime('2020-03-10T15:00:00+00:00');
    }
}
