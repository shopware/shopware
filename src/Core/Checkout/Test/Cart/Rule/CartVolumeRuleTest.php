<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

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
use Shopware\Core\Checkout\Cart\Rule\CartVolumeRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @group rules
 */
#[Package('business-ops')]
class CartVolumeRuleTest extends TestCase
{
    use CartRuleHelperTrait;
    use IntegrationTestBehaviour;

    private CartVolumeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CartVolumeRule();
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrect(
        string $operator,
        float $volume,
        bool $expected
    ): void {
        $this->rule->assign(['volume' => $volume, 'operator' => $operator]);

        $match = $this->rule->match(new CartRuleScope(
            $this->createCartDummy(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectOnNested(
        string $operator,
        float $volume,
        bool $expected
    ): void {
        $this->rule->assign(['volume' => $volume, 'operator' => $operator]);
        $cart = $this->createCartDummy();
        $childLineItemCollection = $cart->getLineItems();

        $containerLineItem = $this->createContainerLineItem($childLineItemCollection);

        $cart->setLineItems(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public static function getMatchingRuleTestData(): array
    {
        return [
            // OPERATOR_EQ
            'match / operator equals / same volume' => [Rule::OPERATOR_EQ, 360, true],
            'no match / operator equals / different volume' => [Rule::OPERATOR_EQ, 200, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same volume' => [Rule::OPERATOR_NEQ, 360, false],
            'match / operator not equals / different volume' => [Rule::OPERATOR_NEQ, 200, true],
            // OPERATOR_GT
            'no match / operator greater than / lower volume' => [Rule::OPERATOR_GT, 400, false],
            'no match / operator greater than / same volume' => [Rule::OPERATOR_GT, 360, false],
            'match / operator greater than / higher volume' => [Rule::OPERATOR_GT, 100, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower volume' => [Rule::OPERATOR_GTE, 400, false],
            'match / operator greater than equals / same volume' => [Rule::OPERATOR_GTE, 360, true],
            'match / operator greater than equals / higher volume' => [Rule::OPERATOR_GTE, 100, true],
            // OPERATOR_LT
            'match / operator lower than / lower volume' => [Rule::OPERATOR_LT, 400, true],
            'no match / operator lower  than / same volume' => [Rule::OPERATOR_LT, 360, false],
            'no match / operator lower than / higher volume' => [Rule::OPERATOR_LT, 100, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower volume' => [Rule::OPERATOR_LTE, 400, true],
            'match / operator lower than equals / same volume' => [Rule::OPERATOR_LTE, 360, true],
            'no match / operator lower than equals / higher volume' => [Rule::OPERATOR_LTE, 100, false],
        ];
    }

    public function testIfRuleIsConsistent(): void
    {
        $ruleId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $ruleRepository = $this->getContainer()->get('rule.repository');
        $conditionRepository = $this->getContainer()->get('rule_condition.repository');

        $ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $id = Uuid::randomHex();
        $conditionRepository->create([
            [
                'id' => $id,
                'type' => (new CartVolumeRule())->getName(),
                'ruleId' => $ruleId,
                'value' => [
                    'volume' => 9000.1,
                    'operator' => Rule::OPERATOR_EQ,
                ],
            ],
        ], $context);

        $result = $conditionRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertNotNull($result);
        static::assertSame(9000.1, $result->getValue()['volume']);
        static::assertSame(Rule::OPERATOR_EQ, $result->getValue()['operator']);
    }

    private function createCartDummy(): Cart
    {
        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithDeliveryInfo(false, 3, 10, 40, 3, 0.5),
            $this->createLineItemWithDeliveryInfo(true, 3, 10, 40, 3, 0.5),
        ]);

        $cart = $this->createCart($lineItemCollection);

        $deliveryPositionCollection = new DeliveryPositionCollection();
        $calculatedPrice = new CalculatedPrice(1.0, 1.0, new CalculatedTaxCollection(), new TaxRuleCollection());
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
