<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
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
use Shopware\Core\Checkout\Customer\Rule\ShippingCityRule;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ShippingCityRule::class)]
#[Group('rules')]
class ShippingCityRuleTest extends TestCase
{
    private ShippingCityRule $rule;

    protected function setUp(): void
    {
        $this->rule = new ShippingCityRule();
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice(choices: $expectedOperators), $operators[1]);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        static::assertArrayHasKey('cityName', $ruleConstraints, 'Constraint cityName not found in Rule');
        $cityName = $ruleConstraints['cityName'];
        static::assertEquals(new NotBlank(), $cityName[0]);
        static::assertEquals(new Type('string'), $cityName[1]);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $billingCity): void
    {
        $cityName = 'kyln123';
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $customerAddress = new CustomerAddressEntity();
        $customerAddress->setCity($billingCity);

        $shippingLocation = new ShippingLocation(new CountryEntity(), null, $customerAddress);
        $salesChannelContext->method('getShippingLocation')->willReturn($shippingLocation);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['cityName' => $cityName, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    public function testMatchThrowsException(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(CustomerException::class);
        }

        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $shippingLocation = new ShippingLocation(new CountryEntity(), null, new CustomerAddressEntity());
        $salesChannelContext->method('getShippingLocation')->willReturn($shippingLocation);

        (new ShippingCityRule())->match(
            new CheckoutRuleScope($salesChannelContext)
        );
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getMatchValues(): \Traversable
    {
        yield 'operator_oq / not match / city' => [Rule::OPERATOR_EQ, false, 'kyln000'];
        yield 'operator_oq / match / city' => [Rule::OPERATOR_EQ, true, 'kyln123'];
        yield 'operator_neq / match / city' => [Rule::OPERATOR_NEQ, true, 'kyln000'];
        yield 'operator_neq / not match / city' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / not match / city' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / match / city' => [Rule::OPERATOR_EMPTY, true, ' '];
    }

    public function testDeliveryScopeReadsTheCityOfTheDeliveryAddress(): void
    {
        $this->rule->assign(['cityName' => 'delivery city', 'operator' => Rule::OPERATOR_EQ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(new ShippingLocation(new CountryEntity(), null, $this->createAddress('context city')));

        $delivery = $this->createDelivery(new ShippingLocation(new CountryEntity(), null, $this->createAddress('delivery city')));

        static::assertTrue($this->rule->match(new DeliveryRuleScope($delivery, new Cart('test'), $context)));
    }

    public function testCartScopeStillReadsTheCityOfTheContextAddress(): void
    {
        $this->rule->assign(['cityName' => 'context city', 'operator' => Rule::OPERATOR_EQ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(new ShippingLocation(new CountryEntity(), null, $this->createAddress('context city')));

        $delivery = $this->createDelivery(new ShippingLocation(new CountryEntity(), null, $this->createAddress('delivery city')));

        static::assertTrue($this->rule->match(new CartRuleScope(new Cart('test'), $context)));
        static::assertFalse($this->rule->match(new DeliveryRuleScope($delivery, new Cart('test'), $context)));
    }

    public function testDeliveryScopeDoesNotFallBackToTheCustomerAddress(): void
    {
        $this->rule->assign(['cityName' => 'context city', 'operator' => Rule::OPERATOR_EQ]);

        $customer = new CustomerEntity();
        $customer->setActiveShippingAddress($this->createAddress('context city'));
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromCountry(new CountryEntity()));

        $delivery = $this->createDelivery(ShippingLocation::createFromCountry(new CountryEntity()));

        static::assertTrue($this->rule->match(new CartRuleScope(new Cart('test'), $context)), 'The cart scope falls back to the customer address.');
        static::assertFalse($this->rule->match(new DeliveryRuleScope($delivery, new Cart('test'), $context)), 'The delivery scope reads only the delivery location.');
    }

    private function createAddress(string $city): CustomerAddressEntity
    {
        $address = new CustomerAddressEntity();
        $address->setCity($city);

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
