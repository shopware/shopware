<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
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
}
