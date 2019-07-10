<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\ReferencePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;

class StockDeliverySeparatorTest extends TestCase
{
    /**
     * @var DeliveryBuilder
     */
    private $separator;

    protected function setUp(): void
    {
        parent::setUp();

        $taxCalculator = new TaxCalculator(
            new PriceRounding(),
            new TaxRuleCalculator(new PriceRounding())
        );

        $referencePriceCalculator = new ReferencePriceCalculator(new PriceRounding());

        $this->separator = new DeliveryBuilder(
            new QuantityPriceCalculator(
                new GrossPriceCalculator($taxCalculator, new PriceRounding(), $referencePriceCalculator),
                new NetPriceCalculator($taxCalculator, new PriceRounding(), $referencePriceCalculator),
                Generator::createGrossPriceDetector(),
                $referencePriceCalculator
            )
        );
    }

    public function testAnEmptyCartHasNoDeliveries(): void
    {
        $deliveries = new DeliveryCollection();
        $context = Generator::createSalesChannelContext();

        $data = new CartDataCollection();
        $data->set(
            DeliveryProcessor::buildKey($context->getShippingMethod()->getId()),
            $context->getShippingMethod()
        );

        $this->separator->build(
            $data,
            $deliveries,
            new LineItemCollection(),
            $context
        );

        static::assertEquals(new DeliveryCollection(), $deliveries);
    }

    public function testDeliverableItemCanBeAddedToDelivery(): void
    {
        $location = self::createShippingLocation();

        $context = Generator::createSalesChannelContext(null, null, null, null, null, null, $location->getCountry(), $location->getState());

        $item = (new LineItem('A', 'product'))
            ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    5,
                    5.0,
                    new DeliveryDate(
                        new \DateTime('2012-01-01'),
                        new \DateTime('2012-01-02')
                    ),
                    new DeliveryDate(
                        new \DateTime('2012-01-04'),
                        new \DateTime('2012-01-05')
                    ),
                    false
                )
            );

        $deliveries = new DeliveryCollection();

        $data = new CartDataCollection();
        $data->set(
            DeliveryProcessor::buildKey($context->getShippingMethod()->getId()),
            $context->getShippingMethod()
        );

        $this->separator->build(
            $data,
            $deliveries,
            new LineItemCollection([$item]),
            $context
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection([
                        DeliveryPosition::createByLineItemForInStockDate($item),
                    ]),
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                    $context->getShippingMethod(),
                    $location,
                    new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $deliveries
        );
    }

    public function testCanDeliveryItemsWithSameDeliveryDateTogether(): void
    {
        $location = self::createShippingLocation();

        $itemA = (new LineItem('A', 'product', null, 5))
            ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    5,
                    5.0,
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
                    false
                )
            );

        $itemB = (new LineItem('B', 'product', null, 5))
            ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    5,
                    5.0,
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
                    false
                )
            );

        $deliveries = new DeliveryCollection();

        $context = Generator::createSalesChannelContext(
            null,
            null,
            null,
            null,
            null,
            null,
            $location->getCountry(),
            $location->getState()
        );

        $data = new CartDataCollection();
        $data->set(
            DeliveryProcessor::buildKey($context->getShippingMethod()->getId()),
            $context->getShippingMethod()
        );

        $this->separator->build(
            $data,
            $deliveries,
            new LineItemCollection([$itemA, $itemB]),
            $context
        );

        static::assertCount(1, $deliveries);

        /** @var Delivery $delivery */
        $delivery = $deliveries->first();
        static::assertCount(2, $delivery->getPositions());

        static::assertContains($itemA->getId(), $delivery->getPositions()->getKeys());
        static::assertContains($itemB->getId(), $delivery->getPositions()->getKeys());
    }

    public function testOutOfStockItemsCanBeDelivered(): void
    {
        $location = self::createShippingLocation();

        $context = Generator::createSalesChannelContext(null, null, null, null, null, null, $location->getCountry(), $location->getState());

        $itemA = (new LineItem('A', 'product', null, 5))
            ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    0,
                    5.0,
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
                    false
                )
            );

        $itemB = (new LineItem('B', 'product', null, 5))
            ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    0,
                    5.0,
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
                    false
                )
            );

        $deliveries = new DeliveryCollection();
        $data = new CartDataCollection();
        $data->set(
            DeliveryProcessor::buildKey($context->getShippingMethod()->getId()),
            $context->getShippingMethod()
        );

        $this->separator->build(
            $data,
            $deliveries,
            new LineItemCollection([$itemA, $itemB]),
            $context
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection([
                        DeliveryPosition::createByLineItemForOutOfStockDate($itemA),
                        DeliveryPosition::createByLineItemForOutOfStockDate($itemB),
                    ]),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
                    $context->getShippingMethod(),
                    $location,
                    new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $deliveries
        );
    }

    public function testNoneDeliverableItemBeIgnored(): void
    {
        $location = self::createShippingLocation();
        $context = Generator::createSalesChannelContext(null, null, null, null, null, null, $location->getCountry(), $location->getState());

        $product = (new LineItem('A', 'product', null, 5))
            ->setPrice(new CalculatedPrice(1, 5, new CalculatedTaxCollection(), new TaxRuleCollection(), 5))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    5,
                    5.0,
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
                    false
                )
            );

        $calculatedLineItem = (new LineItem('B', 'product', null, 5))
            ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = new DeliveryCollection();

        $data = new CartDataCollection();
        $data->set(
            DeliveryProcessor::buildKey($context->getShippingMethod()->getId()),
            $context->getShippingMethod()
        );

        $this->separator->build(
            $data,
            $deliveries,
            new LineItemCollection([$product, $calculatedLineItem]),
            $context
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection([
                        DeliveryPosition::createByLineItemForInStockDate($product),
                    ]),
                    $product->getDeliveryInformation()->getInStockDeliveryDate(),
                    $context->getShippingMethod(),
                    $location,
                    new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $deliveries
        );
    }

    private static function createShippingLocation(): ShippingLocation
    {
        $address = new CustomerAddressEntity();
        $address->setCountryState(new CountryStateEntity());

        $country = new CountryEntity();
        $country->setId('5cff02b1029741a4891c430bcd9e3603');

        $address->setCountry($country);
        $address->getCountryState()->setCountryId('5cff02b1029741a4891c430bcd9e3603');

        return ShippingLocation::createFromAddress($address);
    }
}
