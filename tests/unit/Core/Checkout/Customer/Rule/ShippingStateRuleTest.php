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
use Shopware\Core\Checkout\Customer\Rule\ShippingStateRule;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ShippingStateRule::class)]
#[Group('rules')]
class ShippingStateRuleTest extends TestCase
{
    private ShippingStateRule $rule;

    protected function setUp(): void
    {
        $this->rule = new ShippingStateRule();
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
        static::assertArrayHasKey('stateIds', $ruleConstraints, 'Constraint stateIds not found in Rule');
        $stateIds = $ruleConstraints['stateIds'];
        static::assertEquals(new NotBlank(), $stateIds[0]);
        static::assertEquals(new ArrayOfUuid(), $stateIds[1]);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $stateId, bool $stateExists = true): void
    {
        $countryIds = ['kyln123', 'kyln456'];
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $state = new CountryStateEntity();
        $state->setId($stateId);
        $state = $stateExists ? $state : null;

        $shippingLocation = new ShippingLocation(new CountryEntity(), $state, null);
        $salesChannelContext->method('getShippingLocation')->willReturn($shippingLocation);

        $scope = new CheckoutRuleScope($salesChannelContext);
        $this->rule->assign(['stateIds' => $countryIds, 'operator' => $operator]);

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
        yield 'operator_eq / not match / state id' => [Rule::OPERATOR_EQ, false, Uuid::randomHex()];
        yield 'operator_eq / match / state id' => [Rule::OPERATOR_EQ, true, 'kyln123'];
        yield 'operator_neq / match / state id' => [Rule::OPERATOR_NEQ, true,  Uuid::randomHex()];
        yield 'operator_neq / not match / state id' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / not match / state id' => [Rule::OPERATOR_NEQ, false, 'kyln123'];
        yield 'operator_empty / match / state id' => [Rule::OPERATOR_EMPTY, true, ''];
        yield 'operator_empty / match / with not existing state' => [Rule::OPERATOR_EMPTY, true, '', false];
        yield 'operator_eq / match / with not existing state' => [Rule::OPERATOR_EQ, false, '', false];
    }

    public function testDeliveryScopeReadsTheStateOfTheDelivery(): void
    {
        $this->rule->assign(['stateIds' => ['delivery-state'], 'operator' => Rule::OPERATOR_EQ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(new ShippingLocation(new CountryEntity(), $this->createState('context-state'), null));

        $delivery = $this->createDelivery(new ShippingLocation(new CountryEntity(), $this->createState('delivery-state'), null));

        static::assertTrue($this->rule->match(new DeliveryRuleScope($delivery, new Cart('test'), $context)));
    }

    public function testCartScopeStillReadsTheStateOfTheContext(): void
    {
        $this->rule->assign(['stateIds' => ['context-state'], 'operator' => Rule::OPERATOR_EQ]);

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getShippingLocation')->willReturn(new ShippingLocation(new CountryEntity(), $this->createState('context-state'), null));

        $delivery = $this->createDelivery(new ShippingLocation(new CountryEntity(), $this->createState('delivery-state'), null));

        static::assertTrue($this->rule->match(new CartRuleScope(new Cart('test'), $context)));
        static::assertFalse($this->rule->match(new DeliveryRuleScope($delivery, new Cart('test'), $context)));
    }

    public function testDeliveryScopeDoesNotFallBackToTheCustomerAddress(): void
    {
        $this->rule->assign(['stateIds' => ['context-state'], 'operator' => Rule::OPERATOR_EQ]);

        $address = new CustomerAddressEntity();
        $address->setCountryState($this->createState('context-state'));
        $customer = new CustomerEntity();
        $customer->setActiveShippingAddress($address);
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn($customer);
        $context->method('getShippingLocation')->willReturn(ShippingLocation::createFromCountry(new CountryEntity()));

        $delivery = $this->createDelivery(ShippingLocation::createFromCountry(new CountryEntity()));

        static::assertTrue($this->rule->match(new CartRuleScope(new Cart('test'), $context)), 'The cart scope falls back to the customer address.');
        static::assertFalse($this->rule->match(new DeliveryRuleScope($delivery, new Cart('test'), $context)), 'The delivery scope reads only the delivery location.');
    }

    private function createState(string $id): CountryStateEntity
    {
        $state = new CountryStateEntity();
        $state->setId($id);

        return $state;
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
