<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartVolumeRule;
use Shopware\Core\Checkout\Cart\Rule\DeliveryRuleScope;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CartVolumeRule::class)]
class CartVolumeRuleTest extends TestCase
{
    public function testMatchWithWrongScopeShouldReturnFalse(): void
    {
        $cartVolumeRule = new CartVolumeRule();

        $wrongScope = static::createStub(RuleScope::class);

        static::assertFalse($cartVolumeRule->match($wrongScope));
    }

    public function testMatchWithNullVolumeShouldThrowException(): void
    {
        $cartVolumeRule = new CartVolumeRule();

        $cartRuleScope = static::createStub(CartRuleScope::class);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectExceptionObject(new UnsupportedValueException('NULL', CartVolumeRule::class));
        } else {
            $this->expectExceptionObject(CartException::unsupportedValue('NULL', CartVolumeRule::class));
        }

        $cartVolumeRule->match($cartRuleScope);
    }

    #[DataProvider('matchTestDataProvider')]
    public function testMatch(string $operator, float $ruleVolume, bool $expectedResult): void
    {
        $cartVolumeRule = new CartVolumeRule($operator, $ruleVolume);

        $cart = Generator::createCartWithDelivery();
        $context = static::createStub(SalesChannelContext::class);

        $cartRuleScope = new CartRuleScope($cart, $context);

        static::assertSame($expectedResult, $cartVolumeRule->match($cartRuleScope));
    }

    /**
     * More context: The ruleVolume is stored in cubic meters and the product volume is calculated in cubic millimeters.
     *
     * @return \Generator<array{operator:string, ruleVolume:float, expectedResult:bool}>
     */
    public static function matchTestDataProvider(): \Generator
    {
        yield 'Check with >= operator and 4000.0 mm^3 volume, should return true' => [
            'operator' => Rule::OPERATOR_GTE,
            'ruleVolume' => 0.000004,
            'expectedResult' => true,
        ];

        yield 'Check with >= operator and 5000.0 mm^3 volume, should return true' => [
            'operator' => Rule::OPERATOR_GTE,
            'ruleVolume' => 0.000005,
            'expectedResult' => true,
        ];

        yield 'Check with >= operator and 6000.0 mm^3 volume, should return false' => [
            'operator' => Rule::OPERATOR_GTE,
            'ruleVolume' => 0.000006,
            'expectedResult' => false,
        ];

        yield 'Check with <= operator and 4000.0 mm^3 volume, should return false' => [
            'operator' => Rule::OPERATOR_LTE,
            'ruleVolume' => 0.000004,
            'expectedResult' => false,
        ];

        yield 'Check with <= operator and 5000.0 mm^3 volume, should return true' => [
            'operator' => Rule::OPERATOR_LTE,
            'ruleVolume' => 0.000005,
            'expectedResult' => true,
        ];

        yield 'Check with <= operator and 6000.0 mm^3 volume, should return true' => [
            'operator' => Rule::OPERATOR_LTE,
            'ruleVolume' => 0.000006,
            'expectedResult' => true,
        ];

        yield 'Check with > operator and 4000.0 mm^3 volume, should return true' => [
            'operator' => Rule::OPERATOR_GT,
            'ruleVolume' => 0.000004,
            'expectedResult' => true,
        ];

        yield 'Check with > operator and 5000.0 mm^3 volume, should return false' => [
            'operator' => Rule::OPERATOR_GT,
            'ruleVolume' => 0.000005,
            'expectedResult' => false,
        ];

        yield 'Check with > operator and 6000.0 mm^3 volume, should return false' => [
            'operator' => Rule::OPERATOR_GT,
            'ruleVolume' => 6000.0,
            'expectedResult' => false,
        ];

        yield 'Check with < operator and 4000.0 mm^3 volume, should return false' => [
            'operator' => Rule::OPERATOR_LT,
            'ruleVolume' => 0.000004,
            'expectedResult' => false,
        ];

        yield 'Check with < operator and 5000.0 mm^3 volume, should return false' => [
            'operator' => Rule::OPERATOR_LT,
            'ruleVolume' => 0.000005,
            'expectedResult' => false,
        ];

        yield 'Check with < operator and 6000.0 mm^3 volume, should return true' => [
            'operator' => Rule::OPERATOR_LT,
            'ruleVolume' => 0.000006,
            'expectedResult' => true,
        ];

        yield 'Check with = operator and 4000.0 mm^3 volume, should return false' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleVolume' => 0.000004,
            'expectedResult' => false,
        ];

        yield 'Check with = operator and 5000.0 mm^3 volume, should return true' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleVolume' => 0.000005,
            'expectedResult' => true,
        ];

        yield 'Check with = operator and 6000.0 mm^3 volume, should return false' => [
            'operator' => Rule::OPERATOR_EQ,
            'ruleVolume' => 0.000006,
            'expectedResult' => false,
        ];

        yield 'Check with != operator and 4000.0 mm^3 volume, should return true' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleVolume' => 0.000004,
            'expectedResult' => true,
        ];

        yield 'Check with != operator and 5000.0 mm^3 volume, should return false' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleVolume' => 0.000005,
            'expectedResult' => false,
        ];

        yield 'Check with != operator and 6000.0 mm^3 volume, should return true' => [
            'operator' => Rule::OPERATOR_NEQ,
            'ruleVolume' => 0.000006,
            'expectedResult' => true,
        ];

        yield 'Check with empty operator and 5000.0 mm^3 volume, should return false' => [
            'operator' => Rule::OPERATOR_EMPTY,
            'ruleVolume' => 0.000005,
            'expectedResult' => false,
        ];
    }

    public function testGetConstraints(): void
    {
        $cartVolumeRule = new CartVolumeRule();

        $result = $cartVolumeRule->getConstraints();

        static::assertArrayHasKey('volume', $result);
        static::assertArrayHasKey('operator', $result);

        static::assertIsArray($result['volume']);
        static::assertIsArray($result['operator']);
    }

    public function testGetConfig(): void
    {
        $cartVolumeRule = new CartVolumeRule();

        $result = $cartVolumeRule->getConfig()->getData();

        static::assertIsArray($result['operatorSet']['operators']);
        static::assertSame('volume', $result['fields']['volume']['config']['unit']);
    }

    public function testDeliveryScopeReadsTheVolumeOfTheDelivery(): void
    {
        $rule = new CartVolumeRule(Rule::OPERATOR_EQ, 1.0);

        $cart = Generator::createCartWithDelivery();
        $delivery = $this->createDeliveryWithCubicMeter($cart);

        static::assertTrue($rule->match(new DeliveryRuleScope($delivery, $cart, static::createStub(SalesChannelContext::class))));
    }

    public function testCartScopeStillReadsTheVolumeOfAllDeliveries(): void
    {
        $rule = new CartVolumeRule(Rule::OPERATOR_GT, 1.0);

        $cart = Generator::createCartWithDelivery();
        $delivery = $this->createDeliveryWithCubicMeter($cart);

        static::assertTrue($rule->match(new CartRuleScope($cart, static::createStub(SalesChannelContext::class))));
        static::assertFalse($rule->match(new DeliveryRuleScope($delivery, $cart, static::createStub(SalesChannelContext::class))));
    }

    /**
     * Adds a second delivery of exactly one cubic meter (1,000 x 1,000 x 1,000 mm) to the cart
     */
    private function createDeliveryWithCubicMeter(Cart $cart): Delivery
    {
        $lineItem = new LineItem('cube', 'product', 'cube', 1);
        $lineItem->setDeliveryInformation(new DeliveryInformation(1, 1.0, false, null, null, 1000.0, 1000.0, 1000.0));
        $price = new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection());
        $lineItem->setPrice($price);

        $delivery = new Delivery(
            new DeliveryPositionCollection([
                new DeliveryPosition('cube', $lineItem, 1, $price, new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable())),
            ]),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );
        $cart->getDeliveries()->add($delivery);

        return $delivery;
    }
}
