<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartWeightRule;
use Shopware\Core\Checkout\Cart\Rule\DeliveryRuleScope;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CartWeightRule::class)]
class CartWeightRuleTest extends TestCase
{
    public function testMatchWithWrongScopeShouldReturnFalse(): void
    {
        $cartVolumeRule = new CartWeightRule();

        $wrongScope = static::createStub(RuleScope::class);

        static::assertFalse($cartVolumeRule->match($wrongScope));
    }

    #[DataProvider('matchTestDataProvider')]
    public function testMatch(string $operator, float $weight, bool $expectedResult): void
    {
        $cartVolumeRule = new CartWeightRule($operator, $weight);

        $cart = Generator::createCartWithDelivery();
        $context = static::createStub(SalesChannelContext::class);

        $cartRuleScope = new CartRuleScope($cart, $context);

        static::assertSame($expectedResult, $cartVolumeRule->match($cartRuleScope));
    }

    /**
     * @return \Generator<array{0:string, 1:float, 2:bool}>
     */
    public static function matchTestDataProvider(): \Generator
    {
        yield '>= 200.0 true' => [
            Rule::OPERATOR_GTE,
            200.0,
            true,
        ];

        yield '>= 270.0 true' => [
            Rule::OPERATOR_GTE,
            270.0,
            true,
        ];

        yield '>= 300.0 false' => [
            Rule::OPERATOR_GTE,
            300.0,
            false,
        ];

        yield '<= 200.0 false' => [
            Rule::OPERATOR_LTE,
            200.0,
            false,
        ];

        yield '<= 270.0 true' => [
            Rule::OPERATOR_LTE,
            270.0,
            true,
        ];

        yield '<= 300.0 true' => [
            Rule::OPERATOR_LTE,
            300.0,
            true,
        ];

        yield '> 200.0 true' => [
            Rule::OPERATOR_GT,
            200.0,
            true,
        ];

        yield '> 270.0 false' => [
            Rule::OPERATOR_GT,
            270.0,
            false,
        ];

        yield '> 300.0 false' => [
            Rule::OPERATOR_GT,
            300.0,
            false,
        ];

        yield '< 200.0 false' => [
            Rule::OPERATOR_LT,
            200.0,
            false,
        ];

        yield '< 270.0 false' => [
            Rule::OPERATOR_LT,
            270.0,
            false,
        ];

        yield '< 300.0 true' => [
            Rule::OPERATOR_LT,
            300.0,
            true,
        ];

        yield '= 200.0 false' => [
            Rule::OPERATOR_EQ,
            200.0,
            false,
        ];

        yield '= 270.0 true' => [
            Rule::OPERATOR_EQ,
            270.0,
            true,
        ];

        yield '= 300.0 false' => [
            Rule::OPERATOR_EQ,
            300.0,
            false,
        ];

        yield '!= 200.0 true' => [
            Rule::OPERATOR_NEQ,
            200.0,
            true,
        ];

        yield '!= 270.0 false' => [
            Rule::OPERATOR_NEQ,
            270.0,
            false,
        ];

        yield '!= 300.0 true' => [
            Rule::OPERATOR_NEQ,
            300.0,
            true,
        ];

        yield 'empty 270.0 false' => [
            Rule::OPERATOR_EMPTY,
            270.0,
            false,
        ];
    }

    public function testGetConstraints(): void
    {
        $cartVolumeRule = new CartWeightRule();

        $result = $cartVolumeRule->getConstraints();

        static::assertArrayHasKey('weight', $result);
        static::assertArrayHasKey('operator', $result);

        static::assertIsArray($result['weight']);
        static::assertIsArray($result['operator']);
    }

    public function testGetConfig(): void
    {
        $cartVolumeRule = new CartWeightRule();

        $result = $cartVolumeRule->getConfig()->getData();

        static::assertIsArray($result['operatorSet']['operators']);
        static::assertSame('weight', $result['fields']['weight']['config']['unit']);
    }

    public function testDeliveryScopeReadsTheWeightOfTheDelivery(): void
    {
        $rule = new CartWeightRule(Rule::OPERATOR_EQ, 3.0);

        $cart = Generator::createCartWithDelivery();
        $delivery = $this->createDeliveryWithWeight($cart, 3.0);

        static::assertTrue($rule->match(new DeliveryRuleScope($delivery, $cart, static::createStub(SalesChannelContext::class))));
    }

    public function testCartScopeStillReadsTheWeightOfTheCartGoods(): void
    {
        $rule = new CartWeightRule(Rule::OPERATOR_EQ, 270.0);

        $cart = Generator::createCartWithDelivery();
        $delivery = $this->createDeliveryWithWeight($cart, 3.0);

        static::assertTrue($rule->match(new CartRuleScope($cart, static::createStub(SalesChannelContext::class))));
        static::assertFalse($rule->match(new DeliveryRuleScope($delivery, $cart, static::createStub(SalesChannelContext::class))));
    }

    private function createDeliveryWithWeight(Cart $cart, float $weight): Delivery
    {
        $lineItem = new LineItem('heavy', 'product', 'heavy', 1);
        $lineItem->setDeliveryInformation(new DeliveryInformation(1, $weight, false));
        $price = new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection());
        $lineItem->setPrice($price);

        $delivery = new Delivery(
            new DeliveryPositionCollection([
                new DeliveryPosition('heavy', $lineItem, 1, $price, new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable())),
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
