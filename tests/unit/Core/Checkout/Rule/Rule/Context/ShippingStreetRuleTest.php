<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\DeliveryRuleScope;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Rule\ShippingStreetRule;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ShippingStreetRule::class)]
class ShippingStreetRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = (new ShippingStreetRule())->assign(['streetName' => 'example street']);

        $cart = new Cart('test');

        $context = static::createStub(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromAddress(
                    $this->createAddress('example street')
                )
            );

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = (new ShippingStreetRule())->assign(['streetName' => 'ExaMple StreEt']);

        $cart = new Cart('test');

        $context = static::createStub(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromAddress(
                    $this->createAddress('example street')
                )
            );

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotMatch(): void
    {
        $rule = (new ShippingStreetRule())->assign(['streetName' => 'example street']);

        $cart = new Cart('test');

        $context = static::createStub(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromAddress(
                    $this->createAddress('test street')
                )
            );

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testWithoutAddress(): void
    {
        $rule = (new ShippingStreetRule())->assign(['streetName' => 'ExaMple StreEt']);

        $cart = new Cart('test');

        $context = static::createStub(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromCountry(
                    new CountryEntity()
                )
            );

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testMatchThrowsException(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(CustomerException::class);
        }

        $context = static::createStub(SalesChannelContext::class);

        $context
            ->method('getShippingLocation')
            ->willReturn(
                ShippingLocation::createFromAddress(
                    $this->createAddress('example street')
                )
            );

        (new ShippingStreetRule())->match(
            new CartRuleScope(
                new Cart('test'),
                $context
            )
        );
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $ruleConstraints = (new ShippingStreetRule())->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice(choices: $expectedOperators), $operators[1]);

        static::assertArrayHasKey('streetName', $ruleConstraints, 'Constraint streetName not found in Rule');
        $streetName = $ruleConstraints['streetName'];
        static::assertEquals(new NotBlank(), $streetName[0]);
        static::assertEquals(new Type('string'), $streetName[1]);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $shippingStreet, bool $noAddress = false): void
    {
        $streetName = 'kyln123';
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setStreet($shippingStreet);

        if ($noAddress) {
            $customerAddress = null;
        }

        $location = new ShippingLocation(new CountryEntity(), null, $customerAddress);
        $salesChannelContext->method('getShippingLocation')->willReturn($location);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $rule = (new ShippingStreetRule())->assign(['streetName' => $streetName, 'operator' => $operator]);

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
        yield 'operator_eq / not match / street' => [Rule::OPERATOR_EQ, false, 'kyln000'];
        yield 'operator_eq / match / street' => [Rule::OPERATOR_EQ, true, 'kyln123'];
        yield 'operator_neq / match / street' => [Rule::OPERATOR_NEQ, true, 'kyln000'];
        yield 'operator_neq / not match / street' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / not match / street' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / match / street' => [Rule::OPERATOR_EMPTY, true, ' '];

        yield 'operator_neq / match / no customer' => [Rule::OPERATOR_NEQ, true, 'ky', true];
        yield 'operator_empty / match / no customer' => [Rule::OPERATOR_EMPTY, true, 'ky', true];
    }

    public function testUnsupportedValue(): void
    {
        try {
            $rule = new ShippingStreetRule();
            $salesChannelContext = static::createStub(SalesChannelContext::class);
            $location = new ShippingLocation(new CountryEntity(), null, new CustomerAddressEntity());
            $salesChannelContext->method('getShippingLocation')->willReturn($location);
            $rule->match(new CheckoutRuleScope($salesChannelContext));
            static::fail('Exception was not thrown');
        } catch (\Throwable $exception) {
            static::assertInstanceOf(CustomerException::class, $exception);
            static::assertSame('Unsupported value of type NULL in Shopware\Core\Checkout\Customer\Rule\ShippingStreetRule', $exception->getMessage());
        }
    }

    public function testDeliveryScopeReadsTheStreetOfTheDeliveryAddress(): void
    {
        $rule = (new ShippingStreetRule())->assign(['streetName' => 'delivery street']);

        $context = static::createStub(SalesChannelContext::class);
        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromAddress($this->createAddress('context street')));

        $delivery = $this->createDelivery(ShippingLocation::createFromAddress($this->createAddress('delivery street')));

        static::assertTrue($rule->match(new DeliveryRuleScope($delivery, new Cart('test'), $context)));
    }

    public function testCartScopeStillReadsTheStreetOfTheContextAddress(): void
    {
        $rule = (new ShippingStreetRule())->assign(['streetName' => 'context street']);

        $context = static::createStub(SalesChannelContext::class);
        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromAddress($this->createAddress('context street')));

        $delivery = $this->createDelivery(ShippingLocation::createFromAddress($this->createAddress('delivery street')));

        static::assertTrue($rule->match(new CartRuleScope(new Cart('test'), $context)));
        static::assertFalse($rule->match(new DeliveryRuleScope($delivery, new Cart('test'), $context)));
    }

    public function testDeliveryScopeDoesNotFallBackToTheCustomerAddress(): void
    {
        $rule = (new ShippingStreetRule())->assign(['streetName' => 'context street']);

        $customer = new CustomerEntity();
        $customer->setActiveShippingAddress($this->createAddress('context street'));
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromCountry(new CountryEntity()));

        $delivery = $this->createDelivery(ShippingLocation::createFromCountry(new CountryEntity()));

        static::assertTrue($rule->match(new CartRuleScope(new Cart('test'), $context)), 'The cart scope falls back to the customer address.');
        static::assertFalse($rule->match(new DeliveryRuleScope($delivery, new Cart('test'), $context)), 'The delivery scope reads only the delivery location.');
    }

    private function createAddress(string $street): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $state = new CountryStateEntity();
        $country = new CountryEntity();
        $state->setCountryId('SWAG-AREA-COUNTRY-ID-1');

        $address->setStreet($street);
        $address->setCountry($country);
        $address->setCountryState($state);

        return $address;
    }

    private function createDelivery(ShippingLocation $location): Delivery
    {
        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            $location,
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }
}
