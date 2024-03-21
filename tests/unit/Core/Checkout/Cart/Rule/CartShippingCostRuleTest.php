<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartShippingCostRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CartShippingCostRule::class)]
#[Group('rules')]
class CartShippingCostRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private CartShippingCostRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CartShippingCostRule();
    }

    #[DataProvider('getRuleTestData')]
    public function testIfMatchesCorrectWithShippingCosts(
        CartShippingCostRule $rule,
        CalculatedPrice $calculatedPrice,
        bool $expected
    ): void {
        $cart = $this->createCartDummyWithShippingCosts($calculatedPrice);
        $childLineItemCollection = $cart->getLineItems();

        $containerLineItem = $this->createContainerLineItem($childLineItemCollection);

        $cart->setLineItems(new LineItemCollection([$containerLineItem]));

        $match = $rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return iterable <string, array{CartShippingCostRule, CalculatedPrice, bool}>
     */
    public static function getRuleTestData(): iterable
    {
        yield 'operator equals / same shipping cost / match' => [
            new CartShippingCostRule(Rule::OPERATOR_EQ, 8.99),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            true,
        ];

        yield 'operator equals / different shipping cost / no match' => [
            new CartShippingCostRule(Rule::OPERATOR_EQ, 10),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            false,
        ];

        yield 'operator not equals / different shipping cost / match' => [
            new CartShippingCostRule(Rule::OPERATOR_NEQ, 10),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            true,
        ];

        yield 'operator not equals / same shipping cost / no match' => [
            new CartShippingCostRule(Rule::OPERATOR_NEQ, 8.99),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            false,
        ];

        yield 'operator greater than / higher shipping cost / match' => [
            new CartShippingCostRule(Rule::OPERATOR_GT, 5),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            true,
        ];

        yield 'operator greater than / same shipping cost / no match' => [
            new CartShippingCostRule(Rule::OPERATOR_GT, 8.99),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            false,
        ];

        yield 'operator greater than / lower shipping cost / no match' => [
            new CartShippingCostRule(Rule::OPERATOR_GT, 10),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            false,
        ];

        yield 'operator greater than equals / higher shipping cost / match' => [
            new CartShippingCostRule(Rule::OPERATOR_GTE, 5),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            true,
        ];

        yield 'operator greater than equals / same shipping cost / match' => [
            new CartShippingCostRule(Rule::OPERATOR_GTE, 8.99),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            true,
        ];

        yield 'operator greater than equals / lower shipping cost / no match' => [
            new CartShippingCostRule(Rule::OPERATOR_GTE, 10),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            false,
        ];

        yield 'operator lower than / lower shipping cost / match' => [
            new CartShippingCostRule(Rule::OPERATOR_LT, 10),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            true,
        ];

        yield 'operator lower than / same shipping cost / no match' => [
            new CartShippingCostRule(Rule::OPERATOR_LT, 8.99),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            false,
        ];

        yield 'operator lower than / higher shipping cost / no match' => [
            new CartShippingCostRule(Rule::OPERATOR_LT, 5),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            false,
        ];

        yield 'operator lower than equals / lower shipping cost / match' => [
            new CartShippingCostRule(Rule::OPERATOR_LTE, 10),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            true,
        ];

        yield 'operator lower than equals / same shipping cost / match' => [
            new CartShippingCostRule(Rule::OPERATOR_LTE, 8.99),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            true,
        ];

        yield 'operator lower than equals / higher shipping cost / no match' => [
            new CartShippingCostRule(Rule::OPERATOR_LTE, 5),
            new CalculatedPrice(1.0, 8.99, new CalculatedTaxCollection(), new TaxRuleCollection()),
            false,
        ];
    }

    public function testMatchIfDifferentRuleScope(): void
    {
        $scope = new CheckoutRuleScope($this->createMock(SalesChannelContext::class));
        static::assertFalse($this->rule->match($scope));
    }

    public function testConstraints(): void
    {
        $constraints = (new CartShippingCostRule())->getConstraints();

        static::assertCount(2, $constraints);

        static::assertArrayHasKey('cartShippingCost', $constraints);
        static::assertEquals($constraints['cartShippingCost'], [
            new NotBlank(),
            new Type(['type' => 'numeric']),
        ]);

        static::assertArrayHasKey('operator', $constraints);
        static::assertEquals(RuleConstraints::numericOperators(false), $constraints['operator']);
    }

    public function testGetConfig(): void
    {
        $config = (new CartShippingCostRule())->getConfig();
        static::assertEquals([
            'fields' => [
                'cartShippingCost' => [
                    'name' => 'cartShippingCost',
                    'type' => 'float',
                    'config' => [
                        'digits' => RuleConfig::DEFAULT_DIGITS,
                    ],
                ],
            ],
            'operatorSet' => [
                'operators' => [
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_GT,
                    Rule::OPERATOR_GTE,
                    Rule::OPERATOR_LT,
                    Rule::OPERATOR_LTE,
                    Rule::OPERATOR_NEQ, ],
                'isMatchAny' => false,
            ],
        ], $config->getData());
    }

    private function createCartDummyWithShippingCosts(CalculatedPrice $calculatedPrice): Cart
    {
        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithDeliveryInfo(false, 3, 10, 40, 3, 0.5),
            $this->createLineItemWithDeliveryInfo(true, 3, 10, 40, 3, 0.5),
        ]);

        $cart = $this->createCart($lineItemCollection);
        $deliveryPositionCollection = new DeliveryPositionCollection();
        $deliveryDate = new DeliveryDate(new \DateTimeImmutable('now'), new \DateTimeImmutable('now'));

        foreach ($cart->getLineItems() as $lineItem) {
            $deliveryPositionCollection->add(new DeliveryPosition(
                Uuid::randomHex(),
                $lineItem,
                $lineItem->getQuantity(),
                $calculatedPrice,
                $deliveryDate
            ));
        }

        $cart->setDeliveries(new DeliveryCollection(
            [
                new Delivery(
                    $deliveryPositionCollection,
                    $deliveryDate,
                    new ShippingMethodEntity(),
                    new ShippingLocation(new CountryEntity(), null, null),
                    $calculatedPrice
                ),
            ]
        ));

        return $cart;
    }
}
