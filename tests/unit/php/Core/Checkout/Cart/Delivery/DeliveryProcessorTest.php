<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryCalculator;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor
 */
class DeliveryProcessorTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);

        $shippingMethodPriceEntity = new ShippingMethodPriceEntity();
        $shippingMethodPriceEntity->setUniqueIdentifier('test');
        $shippingMethodPriceEntity->setCurrencyPrice(new PriceCollection([new Price(Defaults::CURRENCY, 5, 5, false)]));

        $this->salesChannelContext->getShippingMethod()->setPrices(new ShippingMethodPriceCollection([$shippingMethodPriceEntity]));
    }

    /**
     * @dataProvider  createDataProvider
     */
    public function testProcessShouldSkipPriceRecalculation(CalculatedPrice $calculatedPrice, float $totalPrice): void
    {
        $container = $this->createMock(DeliveryBuilder::class);
        $calculator = $this->createMock(DeliveryCalculator::class);
        $repository = $this->createMock(EntityRepository::class);

        $deliveryProcessor = new DeliveryProcessor($container, $calculator, $repository);

        $cartDataCollection = new CartDataCollection();
        $cartDataCollection->set(
            DeliveryProcessor::buildKey($this->salesChannelContext->getShippingMethod()->getId()),
            $this->salesChannelContext->getShippingMethod()
        );
        $originalCart = new Cart('original', 'original');
        $originalCart->setDeliveries(new DeliveryCollection(
            [
                new Delivery(
                    new DeliveryPositionCollection(),
                    new DeliveryDate(new \DateTimeImmutable('now'), new \DateTimeImmutable('now')),
                    new ShippingMethodEntity(),
                    new ShippingLocation(new CountryEntity(), null, null),
                    $calculatedPrice
                ),
            ]
        ));

        $calculatedCart = new Cart('calculated', 'calculated');

        $lineItem = new LineItem('test', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setDeliveryInformation(new DeliveryInformation(5, 0, false));
        $lineItem->setPrice(new CalculatedPrice(5.0, 5.0, new CalculatedTaxCollection([
            new CalculatedTax(5, 19, 5),
        ]), new TaxRuleCollection()));

        $calculatedCart->setLineItems(new LineItemCollection([$lineItem]));

        $cartBehavior = new CartBehavior([
            DeliveryProcessor::SKIP_DELIVERY_PRICE_RECALCULATION => true,
        ]);

        static::assertCount(0, $calculatedCart->getDeliveries());

        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->salesChannelContext, $cartBehavior);

        // Deliveries were built
        static::assertCount(1, $calculatedCart->getDeliveries());

        // Price was not recalculated
        static::assertNotNull($calculatedCart->getDeliveries()->first());
        static::assertSame($totalPrice, $calculatedCart->getDeliveries()->first()->getShippingCosts()->getTotalPrice());
    }

    public function createDataProvider(): \Generator
    {
        yield [
            new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            0.0,
        ];

        yield [
            new CalculatedPrice(1.0, 1.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            1.0,
        ];
    }
}
