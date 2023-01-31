<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\IsCompanyRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('business-ops')]
class IsCompanyCustomerRuleTest extends TestCase
{
    public function testThatNonExistingCustomerDoesNotMatch(): void
    {
        $this->matchRuleWithCustomer(new IsCompanyRule(true), null, false);
        $this->matchRuleWithCustomer(new IsCompanyRule(false), null, false);
    }

    public function testThatCustomerWithCompanyMatchesCorrectly(): void
    {
        $customer = new CustomerEntity();
        $customer->setCompany('shopware AG');

        $this->matchRuleWithCustomer(new IsCompanyRule(true), $customer, true);
        $this->matchRuleWithCustomer(new IsCompanyRule(false), $customer, false);
    }

    public function testThatCustomerWithoutCompanyMatchesCorrectly(): void
    {
        $customer = new CustomerEntity();

        $this->matchRuleWithCustomer(new IsCompanyRule(true), $customer, false);
        $this->matchRuleWithCustomer(new IsCompanyRule(false), $customer, true);
    }

    public function testThatCustomerWithEmptyStringCompanyMatchesCorrectly(): void
    {
        $customer = new CustomerEntity();
        $customer->setCompany('');

        $this->matchRuleWithCustomer(new IsCompanyRule(true), $customer, false);
        $this->matchRuleWithCustomer(new IsCompanyRule(false), $customer, true);
    }

    private function matchRuleWithCustomer(IsCompanyRule $isCompanyRule, ?CustomerEntity $customer, bool $isMatchExpected): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')
            ->willReturn($customer);

        $scope = new CheckoutRuleScope($salesChannelContext);

        static::assertSame($isCompanyRule->match($scope), $isMatchExpected);
    }
}
