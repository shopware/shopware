<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Order\IdStruct;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionDeliveryCalculator;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\Rule\FalseRule;

/**
 * @internal
 */
#[CoversClass(PromotionDeliveryCalculator::class)]
class PromotionDeliveryCalculatorTest extends TestCase
{
    private IdsCollection $ids;

    private PromotionDeliveryCalculator $promotionDeliveryCalculator;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $builderMock = $this->createMock(PromotionItemBuilder::class);
        $builderMock->method('buildDeliveryPlaceholderLineItem')
            ->willReturn(new LineItem('whatever', PromotionProcessor::LINE_ITEM_TYPE));

        $this->promotionDeliveryCalculator = new PromotionDeliveryCalculator(
            $this->createMock(QuantityPriceCalculator::class),
            $this->createMock(PercentagePriceCalculator::class),
            $builderMock
        );
    }

    public function testPromotionPrioritySorting(): void
    {
        $lineItems = new LineItem($this->ids->get('line-item-1'), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItems->setPriceDefinition(new AbsolutePriceDefinition(50.0));
        $lineItems->setLabel('Product');

        $firstDiscountItem = $this->getDiscountItem('first-promotion')
            ->setPayloadValue('code', 'code-1')
            ->setPayloadValue('exclusions', ['second-promotion'])
            ->setPayloadValue('priority', 2)
            ->setRequirement(new AndRule([new FalseRule()]));

        $secondDiscountItem = $this->getDiscountItem('second-promotion')
            ->setPayloadValue('code', 'code-2')
            ->setPayloadValue('exclusions', ['first-promotion'])
            ->setPayloadValue('priority', 1)
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

        $error = $cart->getErrors()->first();
        static::assertInstanceOf(PromotionNotEligibleError::class, $error);
        static::assertEquals('Promotion first-promotion not eligible for cart!', $error->getMessage());
    }

    public function testAddingExtensionFromOriginalLineItem(): void
    {
        $discountItem = $this->getDiscountItem('promotion')
            ->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_DELIVERY)
            ->setId('discount-item-id');

        $discountItem->addExtension(OrderConverter::ORIGINAL_ID, new IdStruct('original-id'));

        $original = $this->makeCart($discountItem);
        $toCalculate = $this->makeCart();

        $originalDiscount = new LineItemCollection([$discountItem]);

        $this->promotionDeliveryCalculator->calculate(
            $originalDiscount,
            $original,
            $toCalculate,
            $this->createMock(SalesChannelContext::class)
        );

        static::assertNotNull($promotion = $toCalculate->getLineItems()->filterType(PromotionProcessor::LINE_ITEM_TYPE)->first());
        static::assertInstanceOf(IdStruct::class, $idStruct = $promotion->getExtension(OrderConverter::ORIGINAL_ID));
        static::assertEquals('original-id', $idStruct->getId());
    }

    private function makeCart(?LineItem $discountItem = null): Cart
    {
        $lineItems = new LineItem($this->ids->get('line-item-1'), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItems->setPriceDefinition(new AbsolutePriceDefinition(50.0));
        $lineItems->setLabel('Product');

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            new DeliveryDate(new \DateTimeImmutable(), new \DateTimeImmutable()),
            new ShippingMethodEntity(),
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(1.0, 1.0, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $cart = new Cart('promotion-test');
        $lineItemCollection = new LineItemCollection([$lineItems]);

        if ($discountItem !== null) {
            $lineItemCollection->add($discountItem);
        }

        $cart->addLineItems($lineItemCollection);
        $cart->setDeliveries(new DeliveryCollection([$delivery]));

        return $cart;
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
