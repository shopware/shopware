<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class DeliveryProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $shippingMethodPriceEntity = new ShippingMethodPriceEntity();
        $shippingMethodPriceEntity->setUniqueIdentifier('test');
        $shippingMethodPriceEntity->setCurrencyPrice(new PriceCollection([new Price(Defaults::CURRENCY, 5, 5, false)]));

        $this->salesChannelContext->getShippingMethod()->setPrices(new ShippingMethodPriceCollection([$shippingMethodPriceEntity]));
    }

    public function testProcessShouldRecalculateAll(): void
    {
        $deliveryProcessor = $this->getContainer()->get(DeliveryProcessor::class);

        $cartDataCollection = new CartDataCollection();
        $cartDataCollection->set(
            DeliveryProcessor::buildKey($this->salesChannelContext->getShippingMethod()->getId()),
            $this->salesChannelContext->getShippingMethod()
        );
        $originalCart = new Cart('original');
        $calculatedCart = new Cart('calculated');

        $lineItem = new LineItem('test', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setDeliveryInformation(new DeliveryInformation(5, 0, false));
        $lineItem->setPrice(new CalculatedPrice(5.0, 5.0, new CalculatedTaxCollection([
            new CalculatedTax(5, 19, 5),
        ]), new TaxRuleCollection()));
        $lineItem->setShippingCostAware(true);

        $calculatedCart->setLineItems(new LineItemCollection([$lineItem]));

        $cartBehavior = new CartBehavior();

        static::assertCount(0, $calculatedCart->getDeliveries());

        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->salesChannelContext, $cartBehavior);

        // Deliveries were built
        static::assertCount(1, $calculatedCart->getDeliveries());

        // Price was recalculated
        static::assertNotNull($calculatedCart->getDeliveries()->first());
        static::assertSame(5.0, $calculatedCart->getDeliveries()->first()->getShippingCosts()->getTotalPrice());

        // Tax was recalculated
        static::assertNotNull($calculatedCart->getDeliveries()->first());
        static::assertCount(1, $calculatedCart->getDeliveries()->first()->getShippingCosts()->getCalculatedTaxes());
        static::assertNotNull($calculatedCart->getDeliveries()->first()->getShippingCosts()->getCalculatedTaxes()->first());
        static::assertSame(5.0, $calculatedCart->getDeliveries()->first()->getShippingCosts()->getCalculatedTaxes()->first()->getPrice());
    }

    public function testMultiProcessWhenShippingCostEditedWithCurrencyFactor(): void
    {
        $factor = 1.1;
        $this->salesChannelContext->getContext()->assign(['currencyFactor' => $factor]);
        $deliveryProcessor = $this->getContainer()->get(DeliveryProcessor::class);

        $cartDataCollection = new CartDataCollection();
        $cartDataCollection->set(
            DeliveryProcessor::buildKey($this->salesChannelContext->getShippingMethod()->getId()),
            $this->salesChannelContext->getShippingMethod()
        );

        $originalCart = new Cart('original');
        $deliveryTime = $this->generateDeliveryTimeDummy();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');
        $shippingMethod->setName('Express');
        $shippingMethod->addTranslated('name', 'Express');
        $shippingMethod->setDeliveryTime($deliveryTime);
        $shippingMethod->setAvailabilityRuleId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $originalCart->setDeliveries(new DeliveryCollection([$delivery]));

        $calculatedCart = new Cart('calculated');

        $lineItem = new LineItem('test', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setDeliveryInformation(new DeliveryInformation(5, 0, false));
        $lineItem->setPrice(new CalculatedPrice(5.0, 5.0, new CalculatedTaxCollection([
            new CalculatedTax(5, 19, 5),
        ]), new TaxRuleCollection()));

        $calculatedCart->setLineItems(new LineItemCollection([$lineItem]));
        $cartBehavior = new CartBehavior([DeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION => true]);

        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->salesChannelContext, $cartBehavior);
        $originalCart = $calculatedCart;
        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->salesChannelContext, $cartBehavior);

        // Price was recalculated
        static::assertNotNull($originalCart->getExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS));
        static::assertNotNull($originalCart->getDeliveries()->first());
        static::assertSame(10.0, $originalCart->getDeliveries()->first()->getShippingCosts()->getTotalPrice());
    }

    public function testDeliveriesContainDiscountButSkipRecalculation(): void
    {
        $deliveryProcessor = $this->getContainer()->get(DeliveryProcessor::class);

        $cartDataCollection = new CartDataCollection();
        $cartDataCollection->set(
            DeliveryProcessor::buildKey($this->salesChannelContext->getShippingMethod()->getId()),
            $this->salesChannelContext->getShippingMethod()
        );

        $originalCart = new Cart('original');
        $deliveryTime = $this->generateDeliveryTimeDummy();

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('1');
        $shippingMethod->setName('Express');
        $shippingMethod->addTranslated('name', 'Express');
        $shippingMethod->setDeliveryTime($deliveryTime);
        $shippingMethod->setAvailabilityRuleId(Uuid::randomHex());
        $shippingMethod->setTaxType(ShippingMethodEntity::TAX_TYPE_AUTO);
        $deliveryDate = new DeliveryDate(new \DateTime(), new \DateTime());

        $delivery = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $deliveryDiscount = new Delivery(
            new DeliveryPositionCollection(),
            $deliveryDate,
            $shippingMethod,
            new ShippingLocation(new CountryEntity(), null, null),
            new CalculatedPrice(-10, -10, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $originalCart->setDeliveries(new DeliveryCollection([$delivery, $deliveryDiscount]));

        $calculatedCart = new Cart('calculated');

        $lineItem = new LineItem('test', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setDeliveryInformation(new DeliveryInformation(5, 0, false));
        $lineItem->setPrice(new CalculatedPrice(5.0, 5.0, new CalculatedTaxCollection([
            new CalculatedTax(5, 19, 5),
        ]), new TaxRuleCollection()));

        $calculatedCart->setLineItems(new LineItemCollection([$lineItem]));
        $cartBehavior = new CartBehavior([DeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION => true]);

        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->salesChannelContext, $cartBehavior);
        $originalCart = $calculatedCart;
        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->salesChannelContext, $cartBehavior);

        // Price was recalculated
        static::assertNotNull($originalCart->getExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS));
        static::assertNotNull($originalCart->getDeliveries()->first());
        static::assertCount(1, $originalCart->getDeliveries());
        static::assertInstanceOf(Delivery::class, $originalCart->getDeliveries()->first());
        static::assertSame(10.0, $originalCart->getDeliveries()->first()->getShippingCosts()->getTotalPrice());
    }

    private function generateDeliveryTimeDummy(): DeliveryTimeEntity
    {
        $deliveryTime = new DeliveryTimeEntity();
        $deliveryTime->setMin(1);
        $deliveryTime->setMax(3);
        $deliveryTime->setUnit(DeliveryTimeEntity::DELIVERY_TIME_DAY);

        return $deliveryTime;
    }
}
