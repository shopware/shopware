<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Content\Flow\Rule\OrderTrackingCodeRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 *
 * @group rules
 *
 * @covers \Shopware\Core\Content\Flow\Rule\OrderTrackingCodeRule
 */
#[Package('business-ops')]
class OrderTrackingCodeRuleTest extends TestCase
{
    private OrderTrackingCodeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new OrderTrackingCodeRule();
    }

    /**
     * @dataProvider getRuleTestData
     *
     * @param list<string> $trackingCodeData
     */
    public function testIfMatches(
        OrderTrackingCodeRule $rule,
        array $trackingCodeData,
        bool $expected
    ): void {
        $orderDeliveryCollection = new OrderDeliveryCollection();
        $orderDelivery = new OrderDeliveryEntity();
        $orderDelivery->setId(Uuid::randomHex());
        $orderDelivery->setTrackingCodes($trackingCodeData);
        $orderDeliveryCollection->add($orderDelivery);

        $order = new OrderEntity();
        $order->setDeliveries($orderDeliveryCollection);

        $cart = $this->createMock(Cart::class);
        $context = $this->createMock(SalesChannelContext::class);

        $match = $rule->match(new FlowRuleScope(
            $order,
            $cart,
            $context
        ));
        static::assertSame($expected, $match);
    }

    /**
     * @return iterable<string, array{OrderTrackingCodeRule, list<string>, boolean}>
     */
    public static function getRuleTestData(): iterable
    {
        yield 'Test if the rule matches with one tracking code and isSet is true' => [
            new OrderTrackingCodeRule(true),
            ['TrackingCode123'],
            true,
        ];

        yield 'Test if the rule matches with multiple tracking codes and isSet is true' => [
            new OrderTrackingCodeRule(true),
            ['TrackingCode123', 'TrackingCode456', 'TrackingCode789'],
            true,
        ];

        yield 'Test if the rule dont matches with no tracking code set and isSet is true' => [
            new OrderTrackingCodeRule(true),
            [],
            false,
        ];

        yield 'Test if the rule matches with no tracking code set and isSet is false' => [
            new OrderTrackingCodeRule(false),
            [],
            true,
        ];

        yield 'Test if the rule dont matches with an empty tracking code string and isSet is true' => [
            new OrderTrackingCodeRule(true),
            [''],
            false,
        ];

        yield 'Test if the rule matches with empty tracking code string and isSet to false' => [
            new OrderTrackingCodeRule(false),
            [''],
            true,
        ];
    }

    public function testNoOrderDeliveries(): void
    {
        $order = new OrderEntity();

        $cart = $this->createMock(Cart::class);
        $context = $this->createMock(SalesChannelContext::class);
        $scope = new FlowRuleScope($order, $cart, $context);

        $this->rule->assign(['isSet' => true]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testNotExpectedRuleScope(): void
    {
        $ruleScope = $this->createMock(RuleScope::class);

        $this->rule->assign(['isSet' => true]);
        static::assertFalse($this->rule->match($ruleScope));
    }

    public function testConstraint(): void
    {
        $constraints = (new OrderTrackingCodeRule())->getConstraints();

        static::assertArrayHasKey('isSet', $constraints);
        static::assertEquals([
            'isSet' => [new NotNull(), new Type(['type' => 'bool'])],
        ], $constraints);
    }

    public function testGetConfig(): void
    {
        $rule = new OrderTrackingCodeRule(true);
        $config = $rule->getConfig();

        static::assertEquals([
            'operatorSet' => null,
            'fields' => [
                [
                    'name' => 'isSet',
                    'type' => 'bool',
                    'config' => [],
                ],
            ],
        ], $config->getData());
    }
}
