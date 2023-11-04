<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class DeliveryBuilderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var DeliveryBuilder
     */
    private $builder;

    /**
     * @var SalesChannelContext
     */
    private $context;

    /**
     * @var DeliveryProcessor
     */
    private $processor;

    protected function setUp(): void
    {
        $this->builder = $this->getContainer()->get(DeliveryBuilder::class);

        $this->processor = $this->getContainer()->get(DeliveryProcessor::class);

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    public function testIndependenceOfLineItemAndDeliveryPositionPrices(): void
    {
        $cart = $this->createCart();
        $firstDelivery = $this->getDeliveries($cart)->first();
        static::assertNotNull($firstDelivery);

        $firstPosition = $firstDelivery->getPositions()->first();

        static::assertNotSame($firstPosition->getPrice(), $cart->getLineItems()->first()->getPrice());
    }

    public function testEmptyCart(): void
    {
        $cart = $this->createCart(true);
        $deliveries = $this->getDeliveries($cart);

        static::assertCount(0, $deliveries);
    }

    public function testBuildDeliveryWithEqualMinAndMaxDeliveryDateThatLatestHasOneDayMore(): void
    {
        $cart = $this->createCart();
        $firstDelivery = $this->getDeliveries($cart)->first();
        static::assertNotNull($firstDelivery);

        $deliveryDate = $firstDelivery->getDeliveryDate();
        $earliestDeliveryDate = $deliveryDate->getEarliest();
        $earliestDeliveryDate = $earliestDeliveryDate->add(new \DateInterval('P1D'));
        $latestDeliveryDate = $deliveryDate->getLatest();

        static::assertSame(
            $latestDeliveryDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $earliestDeliveryDate->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        );
    }

    private function getDeliveries(Cart $cart): DeliveryCollection
    {
        $data = new CartDataCollection();
        $cartBehaviour = new CartBehavior();

        $this->processor->collect($data, $cart, $this->context, $cartBehaviour);

        return $this->builder->build($cart, $data, $this->context, $cartBehaviour);
    }

    private function createCart(bool $withoutLineItems = false): Cart
    {
        $cart = new Cart('test');
        if ($withoutLineItems) {
            return $cart;
        }

        $lineItems = $this->createLineItems();
        $cart->addLineItems($lineItems);

        return $cart;
    }

    private function createLineItems(): LineItemCollection
    {
        $lineItems = new LineItemCollection();
        $lineItem = $this->createLineItem();
        $lineItems->add($lineItem);

        return $lineItems;
    }

    private function createLineItem(): LineItem
    {
        $lineItem = new LineItem('testid', LineItem::PRODUCT_LINE_ITEM_TYPE);

        $deliveryInformation = $this->createDeliveryInformation();
        $lineItem->setDeliveryInformation($deliveryInformation);

        $price = new CalculatedPrice(100, 200, new CalculatedTaxCollection(), new TaxRuleCollection());
        $lineItem->setPrice($price);

        return $lineItem;
    }

    private function createDeliveryInformation(): DeliveryInformation
    {
        $deliveryTime = $this->createDeliveryTime();

        return new DeliveryInformation(100, 10.0, false, null, $deliveryTime);
    }

    private function createDeliveryTime(): DeliveryTime
    {
        $deliveryTime = new DeliveryTime();
        $deliveryTime->setMin(2);
        $deliveryTime->setMax(2);
        $deliveryTime->setUnit(DeliveryTimeEntity::DELIVERY_TIME_MONTH);

        return $deliveryTime;
    }
}
