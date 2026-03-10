<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionDeliveryCalculator;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\Rule\FalseRule;
use Shopware\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[CoversClass(PromotionDeliveryCalculator::class)]
#[Package('checkout')]
class PromotionDeliveryCalculatorTest extends TestCase
{
    private const CART_PROMOTION = 'cart-promotion';

    private const DELIVERY_PROMOTION = 'delivery-promotion';

    private IdsCollection $ids;

    private QuantityPriceCalculator&MockObject $quantityPriceCalculator;

    private PercentagePriceCalculator&MockObject $percentagePriceCalculator;

    private PromotionDeliveryCalculator $promotionDeliveryCalculator;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->quantityPriceCalculator = $this->createMock(QuantityPriceCalculator::class);
        $this->percentagePriceCalculator = $this->createMock(PercentagePriceCalculator::class);

        $this->promotionDeliveryCalculator = new PromotionDeliveryCalculator(
            $this->quantityPriceCalculator,
            $this->percentagePriceCalculator,
            $this->createMock(PromotionItemBuilder::class)
        );
    }

    public function testNoDelivery(): void
    {
        $lineItems = new LineItem($this->ids->get('line-item-1'), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItems->setPriceDefinition(new AbsolutePriceDefinition(50.0));
        $lineItems->setLabel('Product');

        $promotion = $this->getDiscountItem('promotion')
            ->setPayloadValue('code', 'code-1');

        $cart = new Cart('promotion-test');
        $cart->addLineItems(new LineItemCollection([$lineItems]));

        $this->promotionDeliveryCalculator->calculate(
            new LineItemCollection([$promotion]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertCount(1, $cart->getLineItems());
    }

    public function testPromotionRequirement(): void
    {
        $this->quantityPriceCalculator
            ->method('calculate')
            ->willReturnCallback(static function (QuantityPriceDefinition $definition, SalesChannelContext $context) {
                return new CalculatedPrice($definition->getPrice(), $definition->getPrice(), new CalculatedTaxCollection(), new TaxRuleCollection());
            });

        $this->percentagePriceCalculator
            ->method('calculate')
            ->willReturnCallback(static function (float $percentage, PriceCollection $prices, SalesChannelContext $context) {
                $price = $prices->getTotalPriceAmount() * ($percentage / 100);

                return new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection());
            });

        $noRequirement = $this->getDiscountItem('no-requirement')
            ->setPayloadValue('code', 'code-1')
            ->setPayloadValue('value', -10.0)
            ->setPriceDefinition(new QuantityPriceDefinition(-10.0, new TaxRuleCollection(), 2));

        $falseRequirement = $this->getDiscountItem('false-requirement')
            ->setPayloadValue('code', 'code-1')
            ->setRequirement(new FalseRule())
            ->setPriceDefinition(new AbsolutePriceDefinition(-5.0));

        $trueRequirement = $this->getDiscountItem('true-requirement')
            ->setPayloadValue('code', 'code-1')
            ->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_PERCENTAGE)
            ->setRequirement(new TrueRule())
            ->setPriceDefinition(new PercentagePriceDefinition(10));

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(40.0, 40.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $cart = new Cart('promotion-test');
        $cart->setDeliveries(new DeliveryCollection([$delivery]));

        $this->promotionDeliveryCalculator->calculate(
            new LineItemCollection([$noRequirement, $falseRequirement, $trueRequirement]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertCount(3, $cart->getDeliveries());
        static::assertCount(3, $cart->getErrors());
        static::assertSame(26.0, $cart->getShippingCosts()->getTotalPrice());

        static::assertNotNull($cart->getErrors()->get('promotion-discount-added-no-requirement'));
        static::assertNotNull($cart->getErrors()->get('promotion-discount-added-true-requirement'));
        static::assertNotNull($cart->getErrors()->get('promotion-not-eligible'));
    }

    public function testPromotionPrioritySorting(): void
    {
        $lineItems = new LineItem($this->ids->get('line-item-1'), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItems->setPriceDefinition(new AbsolutePriceDefinition(50.0));
        $lineItems->setLabel('Product');

        $firstDiscountItem = $this->getDiscountItem('first-promotion')
            ->setPayloadValue('code', 'code-1')
            ->setPayloadValue('exclusions', ['second-promotion'])
            ->setPayloadValue('priority', 1);

        $secondDiscountItem = $this->getDiscountItem('second-promotion')
            ->setPayloadValue('code', 'code-2')
            ->setPayloadValue('exclusions', ['first-promotion'])
            ->setPayloadValue('priority', 2)
            ->setPriceDefinition(new AbsolutePriceDefinition(-20.0));

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(1.0, 1.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $cart = new Cart('promotion-test');
        $cart->addLineItems(new LineItemCollection([$lineItems]));
        $cart->setDeliveries(new DeliveryCollection([$delivery]));

        $this->promotionDeliveryCalculator->calculate(
            new LineItemCollection([$secondDiscountItem, $firstDiscountItem]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertCount(2, $cart->getLineItems());
        static::assertCount(2, $cart->getErrors());
        static::assertTrue($cart->getErrors()->has('promotion-discount-added-second-promotion'));
        static::assertTrue($cart->getErrors()->has('promotion-not-eligible'));
    }

    /**
     * Test that fixed delivery discounts don't bypass exclusion checks
     * This test reproduces the bug where a fixed delivery discount is applied
     * even when excluded by a higher priority cart discount
     */
    public function testFixedDeliveryDiscountRespectsExclusion(): void
    {
        $this->quantityPriceCalculator
            ->method('calculate')
            ->willReturnCallback(static function (QuantityPriceDefinition $definition, SalesChannelContext $context) {
                return new CalculatedPrice($definition->getPrice(), $definition->getPrice(), new CalculatedTaxCollection(), new TaxRuleCollection());
            });

        // Prepare products to the cart
        $product1 = new LineItem($this->ids->get('product-1'), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $product1->setPriceDefinition(new AbsolutePriceDefinition(150.0));
        $product1->setLabel('Product 1');
        $product1->setPrice(new CalculatedPrice(150.0, 150.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        // Higher priority cart discount that excludes the delivery discount
        $cartDiscount = new LineItem(self::CART_PROMOTION, PromotionProcessor::LINE_ITEM_TYPE);
        $cartDiscount->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_CART);
        $cartDiscount->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_ABSOLUTE);
        $cartDiscount->setPayloadValue('exclusions', [self::DELIVERY_PROMOTION]);
        $cartDiscount->setPayloadValue('promotionId', self::CART_PROMOTION);
        $cartDiscount->setPayloadValue('priority', 10); // Higher priority
        $cartDiscount->setPayloadValue('code', 'CART50');
        $cartDiscount->setReferencedId(self::CART_PROMOTION);
        $cartDiscount->setLabel('Cart Discount');

        // Lower priority delivery discount with TYPE_FIXED_UNIT that should be excluded
        $deliveryDiscount = $this->getDiscountItem(self::DELIVERY_PROMOTION)
            ->setPayloadValue('code', 'FREESHIP')
            ->setPayloadValue('exclusions', [self::CART_PROMOTION])
            ->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_FIXED_UNIT)
            ->setPayloadValue('value', 0) // Free shipping
            ->setPayloadValue('priority', 1) // Lower priority
            ->setPriceDefinition(new AbsolutePriceDefinition(0));

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $cart = new Cart('promotion-test');
        $cart->setDeliveries(new DeliveryCollection([$delivery]));

        // Add product to cart
        $cart->addLineItems(new LineItemCollection([$product1]));

        // Add cart promotion
        $cart->addLineItems(new LineItemCollection([$cartDiscount]));

        // Calculate delivery promotions - the delivery discount should be excluded
        $this->promotionDeliveryCalculator->calculate(
            new LineItemCollection([$cartDiscount, $deliveryDiscount]),
            $cart,
            $cart,
            Generator::generateSalesChannelContext(),
        );

        // The delivery discount should NOT be applied due to exclusion
        static::assertSame(100.0, $cart->getShippingCosts()->getTotalPrice(), 'Shipping should still be 100 (not free) due to exclusion');

        // Check for promotion not eligible error
        static::assertTrue($cart->getErrors()->has('promotion-not-eligible'), 'Delivery promotion should be marked as not eligible due to exclusion');
    }

    /**
     * Test that when multiple fixed delivery discounts exist without exclusions,
     * only the lowest one is applied (existing behavior should be preserved)
     */
    public function testMultipleFixedDeliveryDiscountsSelectsLowest(): void
    {
        $this->quantityPriceCalculator
            ->method('calculate')
            ->willReturnCallback(static function (QuantityPriceDefinition $definition, SalesChannelContext $context) {
                return new CalculatedPrice($definition->getPrice(), $definition->getPrice(), new CalculatedTaxCollection(), new TaxRuleCollection());
            });

        // First fixed delivery discount - sets shipping to 50
        $firstFixedDiscount = $this->getDiscountItem('first-fixed')
            ->setPayloadValue('code', 'SHIP50')
            ->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_FIXED_UNIT)
            ->setPayloadValue('value', 50)
            ->setPayloadValue('priority', 1)
            ->setPriceDefinition(new AbsolutePriceDefinition(50));

        // Second fixed delivery discount - sets shipping to 20 (lower, should be selected)
        $secondFixedDiscount = $this->getDiscountItem('second-fixed')
            ->setPayloadValue('code', 'SHIP20')
            ->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_FIXED_UNIT)
            ->setPayloadValue('value', 20)
            ->setPayloadValue('priority', 2)
            ->setPriceDefinition(new AbsolutePriceDefinition(20));

        // Third fixed delivery discount - sets shipping to 30
        $thirdFixedDiscount = $this->getDiscountItem('third-fixed')
            ->setPayloadValue('code', 'SHIP30')
            ->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_FIXED_UNIT)
            ->setPayloadValue('value', 30)
            ->setPayloadValue('priority', 3)
            ->setPriceDefinition(new AbsolutePriceDefinition(30));

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(100.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        // Prepare products to the cart
        $product1 = new LineItem($this->ids->get('product-1'), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $product1->setPriceDefinition(new AbsolutePriceDefinition(150.0));
        $product1->setLabel('Product 1');
        $product1->setPrice(new CalculatedPrice(150.0, 150.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $cart = new Cart('promotion-test');
        $cart->addLineItems(new LineItemCollection([$product1]));
        $cart->setDeliveries(new DeliveryCollection([$delivery]));

        // Calculate with all three fixed discounts
        $this->promotionDeliveryCalculator->calculate(
            new LineItemCollection([$firstFixedDiscount, $secondFixedDiscount, $thirdFixedDiscount]),
            $cart,
            $cart,
            $this->createMock(SalesChannelContext::class)
        );

        // Should apply the lowest fixed price (20)
        static::assertSame(20.0, $cart->getShippingCosts()->getTotalPrice(), 'Should set shipping to lowest fixed price of 20');
    }

    private function getDiscountItem(string $promotionId): LineItem
    {
        $discountItemToBeExcluded = new LineItem($promotionId, PromotionProcessor::LINE_ITEM_TYPE);
        $discountItemToBeExcluded->setRequirement(null);
        $discountItemToBeExcluded->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_DELIVERY);
        $discountItemToBeExcluded->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discountItemToBeExcluded->setPayloadValue('exclusions', []);
        $discountItemToBeExcluded->setPayloadValue('promotionId', $promotionId);
        $discountItemToBeExcluded->setReferencedId($promotionId);
        $discountItemToBeExcluded->setLabel($promotionId);
        $discountItemToBeExcluded->setPriceDefinition(new AbsolutePriceDefinition(-10.0));

        return $discountItemToBeExcluded;
    }
}
