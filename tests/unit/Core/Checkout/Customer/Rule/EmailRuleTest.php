<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Rule\EmailRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(EmailRule::class)]
#[Group('rules')]
class EmailRuleTest extends TestCase
{
    private EmailRule $rule;

    protected function setUp(): void
    {
        $this->rule = new EmailRule();
    }

    public function testRuleMatchThrowsAndExceptionWhenNoCustomerEmailIsProvided(): void
    {
        $this->expectExceptionObject(CustomerException::unsupportedValue(\gettype(null), EmailRule::class));

        $customer = new CustomerEntity();
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['email' => null, 'operator' => Rule::OPERATOR_EQ]);

        $this->rule->match($scope);
    }

    public function testRuleMatchThrowsAndExceptionWhenOperatorIsNotSupported(): void
    {
        $this->expectExceptionObject(CustomerException::unsupportedOperator(Rule::OPERATOR_LTE, EmailRule::class));

        $customer = new CustomerEntity();
        $customer->setEmail('*');
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['email' => '*', 'operator' => Rule::OPERATOR_LTE]);

        $this->rule->match($scope);
    }
}
