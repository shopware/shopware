<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractRuleLoader;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryRuleMatcher;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\Rule\ShippingCountryRule;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Content\Rule\RuleEvents;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(DeliveryRuleMatcher::class)]
class DeliveryRuleMatcherTest extends TestCase
{
    private IdsCollection $ids;

    private CountryEntity $germany;

    private CountryEntity $france;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->germany = new CountryEntity();
        $this->germany->setId($this->ids->get('germany'));

        $this->france = new CountryEntity();
        $this->france->setId($this->ids->get('france'));
    }

    public function testDeliveryEqualToTheContextReusesTheContextRuleIdsWithoutLoadingRules(): void
    {
        $shippingMethod = $this->createShippingMethod('context-method', availabilityRuleId: $this->ids->get('rule-a'));
        $location = ShippingLocation::createFromCountry($this->germany);
        $context = $this->createContext($shippingMethod, $location, [$this->ids->get('rule-a'), $this->ids->get('rule-b')]);

        $delivery = $this->createDelivery($shippingMethod, $location);

        $ruleLoader = $this->createMock(AbstractRuleLoader::class);
        $ruleLoader->expects($this->never())->method('load');

        (new DeliveryRuleMatcher($ruleLoader))->match(new CartDataCollection(), new Cart('test'), new DeliveryCollection([$delivery]), $context);

        static::assertSame([$this->ids->get('rule-a'), $this->ids->get('rule-b')], $delivery->getRuleIds());
    }

    public function testLocationEqualByCountryStateAndAddressCountsAsTheContextLocation(): void
    {
        $state = new CountryStateEntity();
        $state->setId($this->ids->get('state'));
        $address = new CustomerAddressEntity();
        $address->setId($this->ids->get('address'));
        $address->setCountry($this->germany);
        $address->setCountryState($state);

        $shippingMethod = $this->createShippingMethod('context-method', availabilityRuleId: $this->ids->get('rule-a'));
        $context = $this->createContext($shippingMethod, new ShippingLocation($this->germany, $state, $address), [$this->ids->get('rule-a')]);

        $delivery = $this->createDelivery($shippingMethod, new ShippingLocation($this->germany, $state, $address));

        $ruleLoader = $this->createMock(AbstractRuleLoader::class);
        $ruleLoader->expects($this->never())->method('load');

        (new DeliveryRuleMatcher($ruleLoader))->match(new CartDataCollection(), new Cart('test'), new DeliveryCollection([$delivery]), $context);

        static::assertSame([$this->ids->get('rule-a')], $delivery->getRuleIds());
    }

    public function testOnlyRulesReferencedByTheShippingMethodAreEvaluatedInCollectionOrder(): void
    {
        $context = $this->createContext($this->createShippingMethod('context-method'), ShippingLocation::createFromCountry($this->france), [$this->ids->get('unreferenced')]);

        $shippingMethod = $this->createShippingMethod(
            'delivery-method',
            availabilityRuleId: $this->ids->get('availability'),
            priceRuleIds: [$this->ids->get('germany-price'), $this->ids->get('france-price'), $this->ids->get('without-payload')],
            calculationRuleIds: [$this->ids->get('calculation')],
        );
        $delivery = $this->createDelivery($shippingMethod, ShippingLocation::createFromCountry($this->germany));

        $rules = new RuleCollection([
            $this->createRule('calculation', new AlwaysValidRule()),
            $this->createRule('unreferenced', new AlwaysValidRule()),
            $this->createRule('availability', new AlwaysValidRule()),
            $this->createRule('germany-price', $this->createCountryRule($this->germany)),
            $this->createRule('france-price', $this->createCountryRule($this->france)),
            $this->createRule('without-payload', null),
        ]);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        (new DeliveryRuleMatcher($this->createRuleLoader($rules)))->match($data, new Cart('test'), new DeliveryCollection([$delivery]), $context);

        static::assertSame(
            [$this->ids->get('calculation'), $this->ids->get('availability'), $this->ids->get('germany-price')],
            $delivery->getRuleIds()
        );
    }

    public function testDeliveriesToDifferentCountriesGetDifferentRuleIds(): void
    {
        $context = $this->createContext($this->createShippingMethod('context-method'), ShippingLocation::createFromCountry($this->germany), []);

        $shippingMethod = $this->createShippingMethod(
            'delivery-method',
            availabilityRuleId: $this->ids->get('france-only'),
            priceRuleIds: [$this->ids->get('germany-free')],
        );
        $germanDelivery = $this->createDelivery($shippingMethod, ShippingLocation::createFromCountry($this->germany));
        $frenchDelivery = $this->createDelivery($shippingMethod, ShippingLocation::createFromCountry($this->france));

        $rules = new RuleCollection([
            $this->createRule('germany-free', $this->createCountryRule($this->germany)),
            $this->createRule('france-only', $this->createCountryRule($this->france)),
        ]);

        $data = new CartDataCollection();
        $data->set(DeliveryProcessor::buildKey($shippingMethod->getId()), $shippingMethod);

        (new DeliveryRuleMatcher($this->createRuleLoader($rules)))->match($data, new Cart('test'), new DeliveryCollection([$germanDelivery, $frenchDelivery]), $context);

        static::assertSame([$this->ids->get('germany-free')], $germanDelivery->getRuleIds());
        static::assertSame([$this->ids->get('france-only')], $frenchDelivery->getRuleIds());
    }

    public function testEveryDeliveryOfAMultiDeliveryCartIsMatchedEvenWhenItEqualsTheContext(): void
    {
        $shippingMethod = $this->createShippingMethod('context-method', availabilityRuleId: $this->ids->get('germany-only'));
        $location = ShippingLocation::createFromCountry($this->germany);
        $context = $this->createContext($shippingMethod, $location, [$this->ids->get('germany-only'), $this->ids->get('unreferenced')]);

        $first = $this->createDelivery($shippingMethod, $location);
        $second = $this->createDelivery($shippingMethod, $location);

        $rules = new RuleCollection([
            $this->createRule('unreferenced', new AlwaysValidRule()),
            $this->createRule('germany-only', $this->createCountryRule($this->germany)),
        ]);

        (new DeliveryRuleMatcher($this->createRuleLoader($rules)))->match(new CartDataCollection(), new Cart('test'), new DeliveryCollection([$first, $second]), $context);

        static::assertSame([$this->ids->get('germany-only')], $first->getRuleIds());
        static::assertSame([$this->ids->get('germany-only')], $second->getRuleIds());
    }

    public function testShippingMethodOfTheDeliveryIsUsedWhenTheCartDataHasNone(): void
    {
        $context = $this->createContext($this->createShippingMethod('context-method'), ShippingLocation::createFromCountry($this->germany), []);

        $shippingMethod = $this->createShippingMethod('delivery-method', availabilityRuleId: $this->ids->get('always'));
        $delivery = $this->createDelivery($shippingMethod, ShippingLocation::createFromCountry($this->france));

        $rules = new RuleCollection([$this->createRule('always', new AlwaysValidRule())]);

        (new DeliveryRuleMatcher($this->createRuleLoader($rules)))->match(new CartDataCollection(), new Cart('test'), new DeliveryCollection([$delivery]), $context);

        static::assertSame([$this->ids->get('always')], $delivery->getRuleIds());
    }

    public function testShippingMethodWithoutRulesGetsNoRuleIdsWithoutLoadingRules(): void
    {
        $context = $this->createContext($this->createShippingMethod('context-method'), ShippingLocation::createFromCountry($this->germany), [$this->ids->get('rule-a')]);

        $delivery = $this->createDelivery($this->createShippingMethod('delivery-method'), ShippingLocation::createFromCountry($this->france));

        $ruleLoader = $this->createMock(AbstractRuleLoader::class);
        $ruleLoader->expects($this->never())->method('load');

        (new DeliveryRuleMatcher($ruleLoader))->match(new CartDataCollection(), new Cart('test'), new DeliveryCollection([$delivery]), $context);

        static::assertSame([], $delivery->getRuleIds());
    }

    public function testRulesAreLoadedOnceUntilReset(): void
    {
        $context = $this->createContext($this->createShippingMethod('context-method'), ShippingLocation::createFromCountry($this->germany), []);

        $shippingMethod = $this->createShippingMethod('delivery-method', availabilityRuleId: $this->ids->get('always'));
        $delivery = $this->createDelivery($shippingMethod, ShippingLocation::createFromCountry($this->france));

        $ruleLoader = $this->createMock(AbstractRuleLoader::class);
        $ruleLoader
            ->expects($this->exactly(2))
            ->method('load')
            ->willReturn(new RuleCollection([$this->createRule('always', new AlwaysValidRule())]));

        $matcher = new DeliveryRuleMatcher($ruleLoader);
        $matcher->match(new CartDataCollection(), new Cart('test'), new DeliveryCollection([$delivery]), $context);
        $matcher->match(new CartDataCollection(), new Cart('test'), new DeliveryCollection([$delivery]), $context);

        $matcher->reset();
        $matcher->match(new CartDataCollection(), new Cart('test'), new DeliveryCollection([$delivery]), $context);

        static::assertSame([$this->ids->get('always')], $delivery->getRuleIds());
    }

    public function testResetsWhenRulesAreWritten(): void
    {
        static::assertSame(
            [RuleEvents::RULE_WRITTEN_EVENT => 'reset'],
            DeliveryRuleMatcher::getSubscribedEvents()
        );
    }

    /**
     * @param list<string> $ruleIds
     */
    private function createContext(ShippingMethodEntity $shippingMethod, ShippingLocation $location, array $ruleIds): SalesChannelContext
    {
        $context = Generator::generateSalesChannelContext(shippingMethod: $shippingMethod, shippingLocation: $location);
        $context->setRuleIds($ruleIds);

        return $context;
    }

    /**
     * @param list<string> $priceRuleIds
     * @param list<string> $calculationRuleIds
     */
    private function createShippingMethod(string $key, ?string $availabilityRuleId = null, array $priceRuleIds = [], array $calculationRuleIds = []): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId($this->ids->get($key));
        $shippingMethod->setAvailabilityRuleId($availabilityRuleId);

        $prices = new ShippingMethodPriceCollection();
        foreach ($priceRuleIds as $ruleId) {
            $price = new ShippingMethodPriceEntity();
            $price->setUniqueIdentifier(Uuid::randomHex());
            $price->setRuleId($ruleId);
            $prices->add($price);
        }
        foreach ($calculationRuleIds as $ruleId) {
            $price = new ShippingMethodPriceEntity();
            $price->setUniqueIdentifier(Uuid::randomHex());
            $price->setCalculationRuleId($ruleId);
            $prices->add($price);
        }
        $shippingMethod->setPrices($prices);

        return $shippingMethod;
    }

    private function createDelivery(ShippingMethodEntity $shippingMethod, ShippingLocation $location): Delivery
    {
        return new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            $shippingMethod,
            $location,
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
    }

    private function createRule(string $key, ?Rule $payload): RuleEntity
    {
        $rule = new RuleEntity();
        $rule->setId($this->ids->get($key));
        $rule->setUniqueIdentifier($this->ids->get($key));
        $rule->setName($key);
        $rule->setPriority(1);
        $rule->setPayload($payload);

        return $rule;
    }

    private function createCountryRule(CountryEntity $country): ShippingCountryRule
    {
        return new ShippingCountryRule(Rule::OPERATOR_EQ, [$country->getId()]);
    }

    private function createRuleLoader(RuleCollection $rules): AbstractRuleLoader
    {
        $ruleLoader = static::createStub(AbstractRuleLoader::class);
        $ruleLoader->method('load')->willReturn($rules);

        return $ruleLoader;
    }
}
