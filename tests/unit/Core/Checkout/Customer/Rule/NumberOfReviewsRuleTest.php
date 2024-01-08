<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\NumberOfReviewsRule;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(NumberOfReviewsRule::class)]
#[Group('rules')]
class NumberOfReviewsRuleTest extends TestCase
{
    private NumberOfReviewsRule $rule;

    protected function setUp(): void
    {
        $this->rule = new NumberOfReviewsRule();
    }

    public function testGetConfig(): void
    {
        $config = (new NumberOfReviewsRule())->getConfig();
        static::assertEquals([
            'fields' => [
                'count' => [
                    'name' => 'count',
                    'type' => 'int',
                    'config' => [],
                ],
            ],
            'operatorSet' => [
                'operators' => [
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_GT,
                    Rule::OPERATOR_GTE,
                    Rule::OPERATOR_LT,
                    Rule::OPERATOR_LTE,
                    Rule::OPERATOR_NEQ,
                ],
                'isMatchAny' => false,
            ],
        ], $config->getData());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('count', $constraints, 'count constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraints not found');

        static::assertEquals(RuleConstraints::numericOperators(false), $constraints['operator']);
        static::assertEquals(RuleConstraints::int(), $constraints['count']);
    }

    public function testRuleDoesNotMatchWithWrongScope(): void
    {
        $rule = new NumberOfReviewsRule();
        $rule->assign(['count' => 2, 'operator' => Rule::OPERATOR_LT]);

        $result = $rule->match($this->createMock(RuleScope::class));

        static::assertFalse($result);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, ?int $reviewCount, int $ruleOrderCount, bool $noCustomer = false): void
    {
        $rule = new NumberOfReviewsRule();
        $rule->assign(['count' => $ruleOrderCount, 'operator' => $operator]);

        $scope = $this->createMock(CheckoutRuleScope::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $orderCollection = new OrderCollection();
        $customer = new CustomerEntity();
        $customer->setReviewCount($reviewCount ?? 0);

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
