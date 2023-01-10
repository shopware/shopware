<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Flow\Rule\OrderDeliveryStatusRule;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

/**
 * @package business-ops
 *
 * @internal
 * @group rules
 * @covers \Shopware\Core\Content\Flow\Rule\OrderDeliveryStatusRule
 */
class OrderDeliveryStatusRuleTest extends TestCase
{
    private OrderDeliveryStatusRule $rule;

    public function setUp(): void
    {
        $this->rule = new OrderDeliveryStatusRule();
    }

    public function testName(): void
    {
        static::assertSame('orderDeliveryStatus', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('stateName', $constraints, 'stateName constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraint not found');

        static::assertEquals(RuleConstraints::string(), $constraints['stateName']);
        static::assertEquals(RuleConstraints::stringOperators(false), $constraints['operator']);
    }

    /**
     * @dataProvider getMatchingValues
     */
    public function testOrderDeliveryStatusRuleMatching(bool $expected, string $orderState, string $selectedOrderState, string $operator): void
    {
        $state = new StateMachineStateEntity();
        $state->setId(Uuid::randomHex());
        $state->setTechnicalName($orderState);

        $orderDeliveryCollection = new OrderDeliveryCollection();
        $orderDelivery = new OrderDeliveryEntity();
        $orderDelivery->setId(Uuid::randomHex());
        $orderDelivery->setStateMachineState($state);
        $orderDeliveryCollection->add($orderDelivery);
        $order = new OrderEntity();
        $order->setDeliveries($orderDeliveryCollection);

        $cart = $this->createMock(Cart::class);
        $context = $this->createMock(SalesChannelContext::class);
        $scope = new FlowRuleScope($order, $cart, $context);

        $this->rule->assign(['stateName' => $selectedOrderState, 'operator' => $operator]);
        static::assertSame($expected, $this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = $this->createMock(RuleScope::class);
        $this->rule->assign(['salutationIds' => [uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    public function testStateNameNotExist(): void
    {
        $order = $this->createMock(OrderEntity::class);
        $cart = $this->createMock(Cart::class);
        $context = $this->createMock(SalesChannelContext::class);
        $scope = new FlowRuleScope($order, $cart, $context);

        $this->rule->assign(['stateName' => null, 'operator' => Rule::OPERATOR_EQ]);
        static::expectException(UnsupportedValueException::class);
        static::assertFalse($this->rule->match($scope));
    }

    public function testDeliveriesEmpty(): void
    {
        $order = new OrderEntity();
        $orderDeliveryCollection = new OrderDeliveryCollection();
        $order->setDeliveries($orderDeliveryCollection);
        $cart = $this->createMock(Cart::class);
        $context = $this->createMock(SalesChannelContext::class);
        $scope = new FlowRuleScope($order, $cart, $context);

        $this->rule->assign(['stateName' => OrderDeliveryStates::STATE_OPEN, 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testConfig(): void
    {
        $config = (new OrderDeliveryStatusRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;

        static::assertEquals([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    /**
     * @return array<string, array{boolean, string, string, string}>
     */
    public function getMatchingValues(): array
    {
        return [
            'EQ - true' => [true, OrderDeliveryStates::STATE_OPEN, OrderDeliveryStates::STATE_OPEN, Rule::OPERATOR_EQ],
            'EQ - false' => [false, OrderDeliveryStates::STATE_OPEN, OrderDeliveryStates::STATE_CANCELLED, Rule::OPERATOR_EQ],
            'NQ - true' => [true, OrderDeliveryStates::STATE_OPEN, OrderDeliveryStates::STATE_CANCELLED, Rule::OPERATOR_NEQ],
            'NQ - false' => [false, OrderDeliveryStates::STATE_OPEN, OrderDeliveryStates::STATE_OPEN, Rule::OPERATOR_NEQ],
        ];
    }
}
