<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\BillingStateRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(BillingStateRule::class)]
#[Group('rules')]
class BillingStateRuleTest extends TestCase
{
    private BillingStateRule $rule;

    protected function setUp(): void
    {
        $this->rule = new BillingStateRule();
    }

    public function testName(): void
    {
        static::assertSame('customerBillingState', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');
        static::assertArrayHasKey('stateIds', $constraints, 'Constraint stateIds not found in Rule');

        static::assertEquals([new NotBlank(), new Choice([Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ, Rule::OPERATOR_EMPTY])], $constraints['operator']);
        static::assertEquals([new NotBlank(), new ArrayOfUuid()], $constraints['stateIds']);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $stateId): void
    {
        $countryIds = ['kyln123', 'kyln456'];
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setCountryStateId($stateId);
        $customer = new CustomerEntity();

        $customer->setActiveBillingAddress($customerAddress);
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['stateIds' => $countryIds, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    public function testValidationWithMissingStateIds(): void
    {
        $customer = new CustomerEntity();
        $customer->setActiveBillingAddress(new CustomerAddressEntity());

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testValidationWithEmptyStateIds(): void
    {
        $customer = new CustomerEntity();
        $address = new CustomerAddressEntity();
        $address->setCountryStateId(Uuid::randomHex());
        $customer->setActiveBillingAddress($address);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['stateIds' => [], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testValidationWithInvalidStateIds(): void
    {
        $customer = new CustomerEntity();
        $address = new CustomerAddressEntity();
        $address->setCountryStateId(Uuid::randomHex());
        $customer->setActiveBillingAddress($address);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['stateIds' => 'COUNTRY-ID-1', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testValidationWithArrayOfInvalidStateIdTypes(): void
    {
        $customer = new CustomerEntity();
        $address = new CustomerAddressEntity();
        $address->setCountryStateId(Uuid::randomHex());
        $customer->setActiveBillingAddress($address);

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['stateIds' => ['STATE-ID-1', true, 3, Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScope(): void
    {
        $invalidScope = $this->createMock(RuleScope::class);
        $this->rule->assign(['stateIds' => ['id'], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function getMatchValues(): array
    {
        return [
            'operator_oq / not match / state id' => [Rule::OPERATOR_EQ, false, Uuid::randomHex()],
            'operator_oq / match / state id' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / state id' => [Rule::OPERATOR_NEQ, true,  Uuid::randomHex()],
            'operator_neq / not match / state id' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / not match / state id' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / match / state id' => [Rule::OPERATOR_EMPTY, true, ''],
        ];
    }
}
