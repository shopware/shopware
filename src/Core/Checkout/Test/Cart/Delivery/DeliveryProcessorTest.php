<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Delivery;

use Doctrine\DBAL\Connection;
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
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class DeliveryProcessorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection|object|null
     */
    private $connection;

    /**
     * @var SalesChannelContextPersister
     */
    private $contextPersister;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->contextPersister = new SalesChannelContextPersister($this->connection);
        $this->salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $shippingMethodPriceEntity = new ShippingMethodPriceEntity();
        $shippingMethodPriceEntity->setUniqueIdentifier('test');
        $shippingMethodPriceEntity->setPrice(5.0);
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
        $originalCart = new Cart('original', 'original');
        $calculatedCart = new Cart('calculated', 'calculated');

        $lineItem = new LineItem('test', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItem->setDeliveryInformation(new DeliveryInformation(5, 0, false));
        $lineItem->setPrice(new CalculatedPrice(5.0, 5.0, new CalculatedTaxCollection([
            new CalculatedTax(5, 19, 5),
        ]), new TaxRuleCollection()));

        $calculatedCart->setLineItems(new LineItemCollection([$lineItem]));

        $cartBehavior = new CartBehavior();

        static::assertCount(0, $calculatedCart->getDeliveries());

        $deliveryProcessor->process($cartDataCollection, $originalCart, $calculatedCart, $this->salesChannelContext, $cartBehavior);

        // Deliveries were built
        static::assertCount(1, $calculatedCart->getDeliveries());

        // Price was recalculated
        static::assertSame(5.0, $calculatedCart->getDeliveries()->first()->getShippingCosts()->getTotalPrice());

        // Tax was recalculated
        static::assertCount(1, $calculatedCart->getDeliveries()->first()->getShippingCosts()->getCalculatedTaxes());
        static::assertSame(5.0, $calculatedCart->getDeliveries()->first()->getShippingCosts()->getCalculatedTaxes()->first()->getPrice());
    }

    public function testProcessShouldSkipPriceRecalculation(): void
    {
        $deliveryProcessor = $this->getContainer()->get(DeliveryProcessor::class);

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
                    new CalculatedPrice(1.0, 1.0, new CalculatedTaxCollection(), new TaxRuleCollection())
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
        static::assertSame(1.0, $calculatedCart->getDeliveries()->first()->getShippingCosts()->getTotalPrice());
    }
}
