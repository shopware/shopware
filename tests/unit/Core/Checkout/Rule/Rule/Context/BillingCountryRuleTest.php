<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\BillingCountryRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(BillingCountryRule::class)]
class BillingCountryRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $billing = new CustomerAddressEntity();
        $billing->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithNotMatch(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-2']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $billing = new CustomerAddressEntity();
        $billing->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testMultipleCountries(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-3', 'SWAG-AREA-COUNTRY-ID-2']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $billing = new CustomerAddressEntity();
        $billing->setCountry($country);

        $customer = new CustomerEntity();
        $customer->setDefaultBillingAddress($billing);

        $context
            ->method('getCustomer')
            ->willReturn($customer);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-3', 'SWAG-AREA-COUNTRY-ID-2']]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $context
            ->method('getCustomer')
            ->willReturn(null);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $ruleConstraints = (new BillingCountryRule())->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        static::assertArrayHasKey('countryIds', $ruleConstraints, 'Constraint countryIds not found in Rule');
        $countryIds = $ruleConstraints['countryIds'];
        static::assertEquals(new NotBlank(), $countryIds[0]);
        static::assertEquals(new ArrayOfUuid(), $countryIds[1]);
    }

    public function testRuleNotMatchingWithoutCountry(): void
    {
        $rule = (new BillingCountryRule())->assign(['countryIds' => ['foo'], 'operator' => Rule::OPERATOR_EQ]);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);

        static::assertFalse($rule->match(new CheckoutRuleScope($salesChannelContext)));

        $customer = new CustomerEntity();
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        static::assertFalse($rule->match(new CheckoutRuleScope($salesChannelContext)));

        $customerAddress = new CustomerAddressEntity();
        $customer->setActiveBillingAddress($customerAddress);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        static::assertFalse($rule->match(new CheckoutRuleScope($salesChannelContext)));
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $countryId, bool $noCustomer = false, bool $noCountry = false, bool $noAddress = false): void
    {
        $countryIds = ['kyln123', 'kyln456'];
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customerAddress = new CustomerAddressEntity();

        $country = new CountryEntity();
        $customer = new CustomerEntity();

        $country->setId($countryId);
        if ($noCountry) {
            $country = null;
        }

        if (!$noAddress) {
            if ($country) {
                $customerAddress->setCountry($country);
            }

            $customer->setActiveBillingAddress($customerAddress);
        }

        if ($noCustomer) {
            $customer = null;
        }

        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $rule = (new BillingCountryRule())->assign(['countryIds' => $countryIds, 'operator' => $operator]);

        $match = $rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return \Traversable<string, array<string|bool>>
     */
    public static function getMatchValues(): \Traversable
    {
        yield 'operator_eq / not match / country id' => [Rule::OPERATOR_EQ, false, Uuid::randomHex()];
        yield 'operator_eq / match / country id' => [Rule::OPERATOR_EQ, true, 'kyln123'];
        yield 'operator_neq / match / country id' => [Rule::OPERATOR_NEQ, true,  Uuid::randomHex()];
        yield 'operator_neq / not match / country id' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / not match / country id' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / match / country id' => [Rule::OPERATOR_EMPTY, true, ''];

        yield 'operator_eq / no match / no customer' => [Rule::OPERATOR_EQ, false, '', true];
        yield 'operator_eq / no match / no country' => [Rule::OPERATOR_EQ, false, '', false, true];
        yield 'operator_eq / no match / no address' => [Rule::OPERATOR_EQ, false, '', false, false, true];

        yield 'operator_empty / match / no customer' => [Rule::OPERATOR_EMPTY, true, '', true];
        yield 'operator_empty / match / no country' => [Rule::OPERATOR_EMPTY, true, '', false, true];
        yield 'operator_empty / match / no address' => [Rule::OPERATOR_EMPTY, true, '', false, false, true];

        yield 'operator_neq / match / no customer' => [Rule::OPERATOR_NEQ, true, '', true];
        yield 'operator_neq / match / no country' => [Rule::OPERATOR_NEQ, true, '', false, true];
        yield 'operator_neq / match / no address' => [Rule::OPERATOR_NEQ, true, '', false, false, true];
    }
}
