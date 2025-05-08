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
