<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\Price;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Core\Checkout\Cart\Tax\TaxRuleCalculator;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Test\Cart\Common\Generator;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateStruct;
use Shopware\Core\System\Country\CountryStruct;

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
            new PriceRounding(2),
            [new TaxRuleCalculator(new PriceRounding(2))]
        );

        $this->separator = new DeliveryBuilder(
            new QuantityPriceCalculator(
                new GrossPriceCalculator($taxCalculator, new PriceRounding(2)),
                new NetPriceCalculator($taxCalculator, new PriceRounding(2)),
                Generator::createGrossPriceDetector()
            )
        );
    }

    public function testAnEmptyCartHasNoDeliveries(): void
    {
        $deliveries = new DeliveryCollection();
        $this->separator->build(
            $deliveries,
            new LineItemCollection(),
            Generator::createContext()
        );

        static::assertEquals(new DeliveryCollection(), $deliveries);
    }

    public function testDeliverableItemCanBeAddedToDelivery(): void
    {
        $location = self::createShippingLocation();

        $context = Generator::createContext(null, null, null, null, null, null, $location->getAreaId(), $location->getCountry(), $location->getState());

        $item = (new LineItem('A', 'product'))
            ->setPrice(new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
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
                    )
                )
            );

        $deliveries = new DeliveryCollection();

        $this->separator->build(
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
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $deliveries
        );
    }

    public function testCanDeliveryItemsWithSameDeliveryDateTogether(): void
    {
        $location = self::createShippingLocation();

        $itemA = (new LineItem('A', 'product', 5))
            ->setPrice(new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    5,
                    5.0,
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05'))
                )
            );

        $itemB = (new LineItem('B', 'product', 5))
            ->setPrice(new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    5,
                    5.0,
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05'))
                )
            );

        $deliveries = new DeliveryCollection();

        $this->separator->build(
            $deliveries,
            new LineItemCollection([$itemA, $itemB]),
            Generator::createContext(null, null, null, null, null, null, $location->getAreaId(), $location->getCountry(), $location->getState())
        );

        static::assertCount(1, $deliveries);

        /** @var Delivery $delivery */
        $delivery = $deliveries->first();
        static::assertCount(2, $delivery->getPositions());

        static::assertContains($itemA->getKey(), $delivery->getPositions()->getKeys());
        static::assertContains($itemB->getKey(), $delivery->getPositions()->getKeys());
    }

    public function testOutOfStockItemsCanBeDelivered(): void
    {
        $location = self::createShippingLocation();

        $context = Generator::createContext(null, null, null, null, null, null, $location->getAreaId(), $location->getCountry(), $location->getState());

        $itemA = (new LineItem('A', 'product', 5))
            ->setPrice(new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    0,
                    5.0,
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05'))
                )
            );

        $itemB = (new LineItem('B', 'product', 5))
            ->setPrice(new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    0,
                    5.0,
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05'))
                )
            );

        $deliveries = new DeliveryCollection();
        $this->separator->build(
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
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $deliveries
        );
    }

    public function testNoneDeliverableItemBeIgnored(): void
    {
        $location = self::createShippingLocation();
        $context = Generator::createContext(null, null, null, null, null, null, $location->getAreaId(), $location->getCountry(), $location->getState());

        $product = (new LineItem('A', 'product', 5))
            ->setPrice(new Price(1, 5, new CalculatedTaxCollection(), new TaxRuleCollection(), 5))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    5,
                    5.0,
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05'))
                )
            );

        $calculatedLineItem = (new LineItem('B', 'product', 5))
            ->setPrice(new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $deliveries = new DeliveryCollection();
        $this->separator->build(
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
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $deliveries
        );
    }

    public function testPositionWithMoreQuantityThanStockWillBeSplitted(): void
    {
        $location = self::createShippingLocation();

        $context = Generator::createContext(null, null, null, null, null, null, $location->getAreaId(), $location->getCountry(), $location->getState());

        $product = (new LineItem('A', 'product', 12))
            ->setPrice(new Price(1.19, 14.28, new CalculatedTaxCollection([new CalculatedTax(1.9, 19, 11.90)]), new TaxRuleCollection([new TaxRule(19)]), 12))
            ->setDeliveryInformation(
                new DeliveryInformation(
                    5,
                    5.0,
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-06'))
                )
            );

        $deliveries = new DeliveryCollection();
        $this->separator->build(
            $deliveries,
            new LineItemCollection([$product]),
            $context
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection([
                        new DeliveryPosition('A', $product, 5,
                            new Price(1.19, 5.95, new CalculatedTaxCollection([new CalculatedTax(0.95, 19, 5.95)]), new TaxRuleCollection([new TaxRule(19)]), 5),
                            $product->getDeliveryInformation()->getInStockDeliveryDate()
                        ),
                    ]),
                    $product->getDeliveryInformation()->getInStockDeliveryDate(),
                    $context->getShippingMethod(),
                    $location,
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
                new Delivery(
                    new DeliveryPositionCollection([
                        new DeliveryPosition('A', $product, 7,
                            new Price(1.19, 8.33, new CalculatedTaxCollection([new CalculatedTax(1.33, 19, 8.33)]), new TaxRuleCollection([new TaxRule(19)]), 7),
                            $product->getDeliveryInformation()->getOutOfStockDeliveryDate()
                        ),
                    ]),
                    $product->getDeliveryInformation()->getOutOfStockDeliveryDate(),
                    $context->getShippingMethod(),
                    $location,
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $deliveries
        );
    }

    private static function createShippingLocation(): ShippingLocation
    {
        $address = new CustomerAddressStruct();
        $address->setCountryState(new CountryStateStruct());

        $country = new CountryStruct();
        $country->setId('5cff02b1-0297-41a4-891c-430bcd9e3603');
        $country->setAreaId('5cff02b1-0297-41a4-891c-430bcd9e3603');

        $address->setCountry($country);
        $address->getCountryState()->setCountryId('5cff02b1-0297-41a4-891c-430bcd9e3603');

        return ShippingLocation::createFromAddress($address);
    }
}
