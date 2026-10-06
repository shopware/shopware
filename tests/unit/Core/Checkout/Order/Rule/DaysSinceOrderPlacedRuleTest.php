<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Rule\DaysSinceOrderPlacedRule;
use Shopware\Core\Content\Flow\Rule\FlowRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(DaysSinceOrderPlacedRule::class)]
#[Group('rules')]
class DaysSinceOrderPlacedRuleTest extends TestCase
{
    public function testMatchesTheOrderDateUsingCalendarDays(): void
    {
        $order = static::createStub(OrderEntity::class);
        $order->method('getOrderDate')->willReturn(new \DateTimeImmutable('2024-01-01T23:59:00+00:00'));

        $scope = static::createStub(FlowRuleScope::class);
        $scope->method('getOrder')->willReturn($order);
        $scope->method('getCurrentTime')->willReturn(new \DateTimeImmutable('2024-01-31T00:01:00+00:00'));

        $rule = new DaysSinceOrderPlacedRule();
        $rule->assign(['daysPassed' => 30, 'operator' => Rule::OPERATOR_LTE]);

        static::assertTrue($rule->match($scope));
    }

    public function testDoesNotMatchAnOrderOutsideTheConfiguredWindow(): void
    {
        $order = static::createStub(OrderEntity::class);
        $order->method('getOrderDate')->willReturn(new \DateTimeImmutable('2023-12-31T23:59:00+00:00'));

        $scope = static::createStub(FlowRuleScope::class);
        $scope->method('getOrder')->willReturn($order);
        $scope->method('getCurrentTime')->willReturn(new \DateTimeImmutable('2024-01-31T00:01:00+00:00'));

        $rule = new DaysSinceOrderPlacedRule();
        $rule->assign(['daysPassed' => 30, 'operator' => Rule::OPERATOR_LTE]);

        static::assertFalse($rule->match($scope));
    }

    public function testDoesNotMatchOutsideFlowRuleScope(): void
    {
        $rule = new DaysSinceOrderPlacedRule();
        $rule->assign(['daysPassed' => 30, 'operator' => Rule::OPERATOR_LTE]);

        static::assertFalse($rule->match(static::createStub(RuleScope::class)));
    }

    public function testRequiresAnIntegerDayCountAndExposesAnIntegerField(): void
    {
        $rule = new DaysSinceOrderPlacedRule();

        static::assertEquals(RuleConstraints::int(), $rule->getConstraints()['daysPassed']);
        static::assertEquals(RuleConstraints::numericOperators(false), $rule->getConstraints()['operator']);
        static::assertSame([
            'name' => 'daysPassed',
            'type' => 'int',
            'config' => ['unit' => RuleConfig::UNIT_TIME],
        ], $rule->getConfig()->getData()['fields']['daysPassed']);
    }
}
