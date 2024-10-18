<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CampaignCodeRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CampaignCodeRule::class)]
#[Group('rules')]
class CampaignCodeRuleTest extends TestCase
{
    private CampaignCodeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CampaignCodeRule();
    }

    public function testName(): void
    {
        static::assertSame('customerCampaignCode', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('campaignCode', $constraints, 'Constraint campaignCode not found in Rule');
        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');

        static::assertEquals(RuleConstraints::stringOperators(), $constraints['operator']);
        static::assertEquals(RuleConstraints::stringArray(), $constraints['campaignCode']);
    }

    /**
     * @param ?array<string> $campaignCodeConditionValue
     */
    #[DataProvider('getMatchValues')]
    public function testRuleMatching(?string $campaignCode, string $operator, ?array $campaignCodeConditionValue, bool $expected): void
    {
        $this->rule->assign([
            'operator' => $operator,
            'campaignCode' => $campaignCodeConditionValue,
        ]);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();
        $customer->setCampaignCode($campaignCode);

        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertSame($expected, $this->rule->match($scope));
    }

    public function testInvalidCombinationOfValueAndOperator(): void
    {
        $this->expectException(UnsupportedValueException::class);
        $customer = new CustomerEntity();
        $customer->setCampaignCode('code');

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        $this->rule->match($scope);
    }

    public function testEqualsOperatorIsNotMatchingWithoutCustomer(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['campaignCode' => 'code', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testEmptyOperatorIsMatchingWithoutCustomer(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['campaignCode' => 'code', 'operator' => Rule::OPERATOR_EMPTY]);
        static::assertTrue($this->rule->match($scope));
    }

    public function testInvalidScope(): void
    {
        $invalidScope = $this->createMock(RuleScope::class);
        $this->rule->assign(['campaignCode' => 'code', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getMatchValues(): \Traversable
    {
        yield 'equal operator is matching' => ['code a', Rule::OPERATOR_EQ, ['Code a'], true];
        yield 'equal operator is matching with multiple values' => ['code a', Rule::OPERATOR_EQ, ['Code a', 'Code b'], true];
        yield 'equal operator is not matching' => ['code a', Rule::OPERATOR_EQ, ['Code b'], false];
        yield 'equal operator is not matching with multiple values' => ['code a', Rule::OPERATOR_EQ, ['Code b', 'Code C'], false];
        yield 'equal operator is not match, with empty customer code' => [null, Rule::OPERATOR_EQ, ['code a'], false];
        yield 'equal operator is not match, with empty customer code and null code' => [null, Rule::OPERATOR_EQ, [null], false];

        yield 'not equal operator is matching' => ['code a', Rule::OPERATOR_NEQ, ['Code b'], true];
        yield 'not equal operator is matching with multiple values' => ['code a', Rule::OPERATOR_NEQ, ['Code b', 'Code C'], true];
        yield 'not equal operator is not matching' => ['code a', Rule::OPERATOR_NEQ, ['Code a', 'Code b'], false];
        yield 'not equal operator is not matching with multiple values' => ['code a', Rule::OPERATOR_NEQ, ['Code a', 'Code b'], false];
        yield 'not equal operator is matching, with empty customer code' => [null, Rule::OPERATOR_NEQ, ['Code a'], true];
        yield 'not equal operator is matching, with empty customer code with multiple values' => [null, Rule::OPERATOR_NEQ, ['Code a', 'Code b'], true];

        yield 'empty operator is matching, with empty customer code' => [null, Rule::OPERATOR_EMPTY, ['Code a'], true];
        yield 'empty operator is not matching, with filled customer code' => ['code a', Rule::OPERATOR_EMPTY, ['Code a'], false];
        yield 'empty operator is not matching, with empty rule code' => ['code a', Rule::OPERATOR_EMPTY, null, false];
    }
}
