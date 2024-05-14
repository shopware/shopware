<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\DaysSinceLastOrderRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\DaysSinceRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(DaysSinceLastOrderRule::class)]
#[CoversClass(DaysSinceRule::class)]
#[Group('rules')]
class DaysSinceLastOrderRuleTest extends TestCase
{
    private DaysSinceLastOrderRule $rule;

    protected function setUp(): void
    {
        $this->rule = new DaysSinceLastOrderRule();
    }

    public function testRuleDoesNotMatchWithWrongScope(): void
    {
        $rule = new DaysSinceLastOrderRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_LT]);

        $result = $rule->match($this->createMock(RuleScope::class));

        static::assertFalse($result);
    }

    public function testRuleMatchesWithDayBefore(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();

        $datetime = self::getTestTimestamp();

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);
        $customer->setLastOrderDate($datetime->modify('-1 day'));

        $scope = $this->createMock(CheckoutRuleScope::class);
        $scope->method('getCurrentTime')->willReturn(self::getTestTimestamp());
        $scope->method('getSalesChannelContext')->willReturn($checkoutContext);

        $rule = new DaysSinceLastOrderRule();
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($rule->match($scope));
    }

    public function testRuleMatchesWithDayBeforePlusOneMinute59(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();

        $timestamp = self::getTestTimestamp();
        $dateTime = $timestamp->setTime(11, 59);
        $orderDate = $timestamp->modify('-1 day +1 minute');

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);
        $customer->setLastOrderDate($orderDate);

        $scope = $this->createMock(CheckoutRuleScope::class);
        $scope->method('getCurrentTime')->willReturn($dateTime);
        $scope->method('getSalesChannelContext')->willReturn($checkoutContext);

        $rule = new DaysSinceLastOrderRule();
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($rule->match($scope));
    }

    public function testRuleDoesNotMatchWithSameDay(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();

        $datetime = self::getTestTimestamp();

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);

        $customer->setLastOrderDate($datetime->setTime(0, 0));

        $scope = $this->createMock(CheckoutRuleScope::class);
        $scope->method('getCurrentTime')->willReturn($datetime);
        $scope->method('getSalesChannelContext')->willReturn($checkoutContext);

        $rule = new DaysSinceLastOrderRule();
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($rule->match($scope));
    }

    public function testRuleDoesNotMatchOnSameDayInLastMinute(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();

        $datetime = self::getTestTimestamp();
        $orderDate = $datetime->setTime(23, 59);

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);
        $customer->setLastOrderDate($orderDate);

        $scope = $this->createMock(CheckoutRuleScope::class);
        $scope->method('getCurrentTime')->willReturn($datetime);
        $scope->method('getSalesChannelContext')->willReturn($checkoutContext);

        $rule = new DaysSinceLastOrderRule();
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($rule->match($scope));
    }

    public function testRuleMatchesWithDayBeforePlusOneMinute(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();

        $datetime = self::getTestTimestamp();

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);

        $customer->setLastOrderDate($datetime->modify('-1 day')->modify('+1 minute'));

        $scope = $this->createMock(CheckoutRuleScope::class);
        $scope->method('getCurrentTime')->willReturn($datetime);
        $scope->method('getSalesChannelContext')->willReturn($checkoutContext);

        $rule = new DaysSinceLastOrderRule();
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($rule->match($scope));
    }

    public function testRuleMatchesWithDayBeforeMinusOneMinute(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();

        $datetime = self::getTestTimestamp();

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);

        $customer->setLastOrderDate($datetime->modify('-1 day')->modify('-1 minute'));

        $scope = $this->createMock(CheckoutRuleScope::class);
        $scope->method('getCurrentTime')->willReturn($datetime);
        $scope->method('getSalesChannelContext')->willReturn($checkoutContext);

        $rule = new DaysSinceLastOrderRule();
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($rule->match($scope));
    }

    public function testRuleMatchesWithSameDayButLater(): void
    {
        $checkoutContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();

        $datetime = self::getTestTimestamp();

        $checkoutContext->method('getCustomer')
            ->willReturn($customer);

        $customer->setLastOrderDate($datetime->modify('-30 minutes'));

        $rule = new DaysSinceLastOrderRule();
        $rule->assign(['daysPassed' => 1, 'operator' => Rule::OPERATOR_EQ]);

        $scope = $this->createMock(CheckoutRuleScope::class);
        $scope->method('getCurrentTime')->willReturn($datetime);
        $scope->method('getSalesChannelContext')->willReturn($checkoutContext);

        static::assertFalse($rule->match($scope));

        $rule->assign(['daysPassed' => 0, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($rule->match($scope));
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
        static::assertEquals(new Type('numeric'), $daysPassed[1]);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, float $daysPassed, ?\DateTimeImmutable $day, bool $noCustomer = false): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();
        $customer->setLastOrderDate($day);

        if ($noCustomer) {
            $customer = null;
        }

        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $this->rule->assign(['daysPassed' => $daysPassed, 'operator' => $operator]);

        $scope = $this->createMock(CheckoutRuleScope::class);
        $scope->method('getCurrentTime')->willReturn(self::getTestTimestamp());
        $scope->method('getSalesChannelContext')->willReturn($salesChannelContext);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getMatchValues(): \Traversable
    {
        $datetime = self::getTestTimestamp();
        $dayTest = $datetime->modify('-30 minutes');

        yield 'operator_eq / not match / day passed / day' => [Rule::OPERATOR_EQ, false, 1.2, $dayTest];
        yield 'operator_eq / match / day passed / day' => [Rule::OPERATOR_EQ, true, 0, $dayTest];
        yield 'operator_neq / match / day passed / day' => [Rule::OPERATOR_NEQ, true, 1, $dayTest];
        yield 'operator_neq / not match / day passed/ day' => [Rule::OPERATOR_NEQ, false, 0, $dayTest];
        yield 'operator_lte_lt / not match / day passed / day' => [Rule::OPERATOR_LTE, false, -1.1, $dayTest];
        yield 'operator_lte_lt / match / day passed/ day' => [Rule::OPERATOR_LTE, true, 1,  $dayTest];
        yield 'operator_lte_e / match / day passed/ day' => [Rule::OPERATOR_LTE, true, 0, $dayTest];
        yield 'operator_gte_gt / not match / day passed/ day' => [Rule::OPERATOR_GTE, false, 1, $dayTest];
        yield 'operator_gte_gt / match / day passed / day' => [Rule::OPERATOR_GTE, true, -1, $dayTest];
        yield 'operator_gte_e / match / day passed / day' => [Rule::OPERATOR_GTE, true, 0, $dayTest];
        yield 'operator_lt / not match / day passed / day' => [Rule::OPERATOR_LT, false, 0, $dayTest];
        yield 'operator_lt / match / day passed / day' => [Rule::OPERATOR_LT, true, 1,  $dayTest];
        yield 'operator_gt / not match / day passed / day' => [Rule::OPERATOR_GT, false, 1, $dayTest];
        yield 'operator_gt / match / day passed / day' => [Rule::OPERATOR_GT, true, -1, $dayTest];
        yield 'operator_empty / not match / day passed/ day' => [Rule::OPERATOR_EMPTY, false, 0, $dayTest];
        yield 'operator_empty / match / day passed / day' => [Rule::OPERATOR_EMPTY, true, 0, null];
        yield 'operator_eq / no match / no customer' => [Rule::OPERATOR_EQ, false, 0, $dayTest, true];
        yield 'operator_neq / match / no customer' => [Rule::OPERATOR_NEQ, true, 0, $dayTest, true];
        yield 'operator_empty / match / no customer' => [Rule::OPERATOR_EMPTY, true, 0, $dayTest, true];
    }

    private static function getTestTimestamp(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2020-03-10T15:00:00+00:00');
    }
}
