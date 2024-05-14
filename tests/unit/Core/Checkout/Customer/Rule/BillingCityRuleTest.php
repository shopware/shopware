<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\BillingCityRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
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
#[CoversClass(BillingCityRule::class)]
#[Group('rules')]
class BillingCityRuleTest extends TestCase
{
    private BillingCityRule $rule;

    protected function setUp(): void
    {
        $this->rule = new BillingCityRule();
    }

    public function testName(): void
    {
        static::assertSame('customerBillingCity', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('cityName', $constraints, 'Constraint cityName not found in Rule');
        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');

        static::assertEquals([new NotBlank(), new Choice([
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ])], $constraints['operator']);
        static::assertEquals([new NotBlank(), new Type('string')], $constraints['cityName']);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $billingCity): void
    {
        $cityName = 'kyln123';
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setCity($billingCity);

        $customer = new CustomerEntity();
        $customer->setActiveBillingAddress($customerAddress);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['cityName' => $cityName, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    public function testValidationWithMissingCityName(): void
    {
        $customer = new CustomerEntity();
        $customer->setActiveBillingAddress(new CustomerAddressEntity());

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        $this->expectException(UnsupportedValueException::class);
        static::assertFalse($this->rule->match($scope));
    }

    public function testValidationWithEmptyCityName(): void
    {
        $customer = new CustomerEntity();
        $address = new CustomerAddressEntity();
        $address->setCity('test');
        $customer->setActiveBillingAddress($address);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['cityName' => '', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testValidateWithInvalidCityNameType(): void
    {
        $customer = new CustomerEntity();
        $address = new CustomerAddressEntity();
        $address->setCity('test');
        $customer->setActiveBillingAddress($address);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['cityName' => true, 'operator' => Rule::OPERATOR_EQ]);
        $this->expectException(UnsupportedValueException::class);
        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScope(): void
    {
        $invalidScope = $this->createMock(RuleScope::class);
        $this->rule->assign(['cityName' => 'test', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    public function testMissingCustomer(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['cityName' => 'test', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testMissingCustomerActiveAddress(): void
    {
        $customer = new CustomerEntity();
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['cityName' => 'test', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function getMatchValues(): array
    {
        return [
            'operator_oq / not match / city' => [Rule::OPERATOR_EQ, false, 'kyln000'],
            'operator_oq / match / city' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / city' => [Rule::OPERATOR_NEQ, true, 'kyln000'],
            'operator_neq / not match / city' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / not match / city' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / match / city' => [Rule::OPERATOR_EMPTY, true, ' '],
        ];
    }
}
