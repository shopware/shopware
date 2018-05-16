<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Checkout\Test\Cart\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\System\Country\Struct\CountryBasicStruct;
use Shopware\System\Country\Aggregate\CountryState\Struct\CountryStateBasicStruct;
use Shopware\Checkout\Customer\Aggregate\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\Content\Product\Struct\ProductBasicStruct;
use Shopware\Checkout\Cart\Delivery\StockDeliverySeparator;
use Shopware\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Checkout\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItem;
use Shopware\Checkout\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Checkout\Cart\LineItem\LineItem;
use Shopware\Checkout\Cart\Price\GrossPriceCalculator;
use Shopware\Checkout\Cart\Price\NetPriceCalculator;
use Shopware\Checkout\Cart\Price\PriceCalculator;
use Shopware\Checkout\Cart\Price\PriceRounding;
use Shopware\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Checkout\Cart\Tax\TaxCalculator;
use Shopware\Checkout\Cart\Tax\TaxRuleCalculator;
use Shopware\Checkout\Test\Cart\Common\Generator;
use Shopware\Content\Product\Cart\ProductProcessor;
use Shopware\Content\Product\Cart\Struct\CalculatedProduct;
use Shopware\Checkout\Rule\Specification\Container\AndRule;

class StockDeliverySeparatorTest extends TestCase
{
    /**
     * @var StockDeliverySeparator
     */
    private $separator;

    protected function setUp(): void
    {
        parent::setUp();

        $taxCalculator = new TaxCalculator(
            new PriceRounding(2),
            [new TaxRuleCalculator(new PriceRounding(2))]
        );

        $this->separator = new StockDeliverySeparator(
            new PriceCalculator(
                new GrossPriceCalculator($taxCalculator, new PriceRounding(2)),
                new NetPriceCalculator($taxCalculator, new PriceRounding(2)),
                Generator::createGrossPriceDetector()
            )
        );
    }

    public function testAnEmptyCartHasNoDeliveries(): void
    {
        $deliveries = new DeliveryCollection();
        $this->separator->addItemsToDeliveries(
            $deliveries,
            new CalculatedLineItemCollection(),
            Generator::createContext()
        );

        static::assertEquals(new DeliveryCollection(), $deliveries);
    }

    public function testDeliverableItemCanBeAddedToDelivery(): void
    {
        $location = self::createShippingLocation();

        $context = Generator::createContext(null, null, null, null, null, null, $location->getAreaId(), $location->getCountry(), $location->getState());

        $item = new CalculatedProduct(
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 100),
            new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'A',
            1,
            new DeliveryDate(
                new \DateTime('2012-01-01'),
                new \DateTime('2012-01-02')
            ),
            new DeliveryDate(
                new \DateTime('2012-01-04'),
                new \DateTime('2012-01-05')
            ),
            self::createProduct(),
            null,
            new AndRule()
        );

        $deliveries = new DeliveryCollection();

        $this->separator->addItemsToDeliveries(
            $deliveries,
            new CalculatedLineItemCollection([$item]),
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

        $itemA = new CalculatedProduct(
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 5),
            new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'A',
            5,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
            self::createProduct(),
            null,
            new AndRule()
        );

        $itemB = new CalculatedProduct(
            new LineItem('B', ProductProcessor::TYPE_PRODUCT, 5),
            new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'B',
            5,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
            self::createProduct(),
            null,
            new AndRule()
        );

        $deliveries = new DeliveryCollection();

        $this->separator->addItemsToDeliveries(
            $deliveries,
            new CalculatedLineItemCollection([$itemA, $itemB]),
            Generator::createContext(null, null, null, null, null, null, $location->getAreaId(), $location->getCountry(), $location->getState())
        );

        static::assertCount(1, $deliveries);

        /** @var Delivery $delivery */
        $delivery = $deliveries->first();
        $this->assertCount(2, $delivery->getPositions());

        static::assertContains($itemA->getIdentifier(), $delivery->getPositions()->getKeys());
        static::assertContains($itemB->getIdentifier(), $delivery->getPositions()->getKeys());
    }

    public function testOutOfStockItemsCanBeDelivered(): void
    {
        $location = self::createShippingLocation();

        $context = Generator::createContext(null, null, null, null, null, null, $location->getAreaId(), $location->getCountry(), $location->getState());

        $itemA = new CalculatedProduct(
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 5),
            new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'A',
            5,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
            self::createProduct(0)
        );
        $itemB = new CalculatedProduct(
            new LineItem('B', ProductProcessor::TYPE_PRODUCT, 5),
            new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'B',
            5,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
            self::createProduct(0)
        );

        $deliveries = new DeliveryCollection();
        $this->separator->addItemsToDeliveries(
            $deliveries,
            new CalculatedLineItemCollection([$itemA, $itemB]),
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
        $context = Generator::createContext(null, null, null, null, null, null, $location->getAreaId(), $location->getCountry(), $location->getState());

        $product = new CalculatedProduct(
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 5),
            new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'A',
            5,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
            self::createProduct()
        );

        $calculatedLineItem = new CalculatedLineItem(
            'SW123456',
            new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            1,
            'no_deliverable_item',
            'Test',
            new LineItem('SW123456', 'lineItem', 1)
        );

        $deliveries = new DeliveryCollection();
        $this->separator->addItemsToDeliveries(
            $deliveries,
            new CalculatedLineItemCollection([$product, $calculatedLineItem]),
            $context
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection([
                        DeliveryPosition::createByLineItemForInStockDate($product),
                    ]),
                    $product->getInStockDeliveryDate(),
                    $context->getShippingMethod(),
                    $location,
                    new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $deliveries
        );
    }

    public function testPositionWithMoreQuantityThanStockWillBeSplitted(): void
    {
        $location = self::createShippingLocation();

        $context = Generator::createContext(null, null, null, null, null, null, $location->getAreaId(), $location->getCountry(), $location->getState());

        $product = new CalculatedProduct(
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 10),
            new CalculatedPrice(1.19, 11.90, new CalculatedTaxCollection([new CalculatedTax(1.9, 19, 11.90)]), new TaxRuleCollection([new TaxRule(19)]), 10),
            'A',
            12,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-06')),
            self::createProduct()
        );

        $deliveries = new DeliveryCollection();
        $this->separator->addItemsToDeliveries(
            $deliveries,
            new CalculatedLineItemCollection([$product]),
            $context
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection([
                        new DeliveryPosition('A', $product, 5,
                            new CalculatedPrice(1.19, 5.95, new CalculatedTaxCollection([new CalculatedTax(0.95, 19, 5.95)]), new TaxRuleCollection([new TaxRule(19)]), 5),
                            $product->getInStockDeliveryDate()
                        ),
                    ]),
                    $product->getInStockDeliveryDate(),
                    $context->getShippingMethod(),
                    $location,
                    new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
                new Delivery(
                    new DeliveryPositionCollection([
                        new DeliveryPosition('A', $product, 7,
                            new CalculatedPrice(1.19, 8.33, new CalculatedTaxCollection([new CalculatedTax(1.33, 19, 8.33)]), new TaxRuleCollection([new TaxRule(19)]), 7),
                            $product->getOutOfStockDeliveryDate()
                        ),
                    ]),
                    $product->getOutOfStockDeliveryDate(),
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
        $address = new CustomerAddressBasicStruct();
        $address->setCountryState(new CountryStateBasicStruct());

        $country = new CountryBasicStruct();
        $country->setId('5cff02b1-0297-41a4-891c-430bcd9e3603');
        $country->setAreaId('5cff02b1-0297-41a4-891c-430bcd9e3603');

        $address->setCountry($country);
        $address->getCountryState()->setCountryId('5cff02b1-0297-41a4-891c-430bcd9e3603');

        return ShippingLocation::createFromAddress($address);
    }

    private static function createProduct(int $stock = 5, string $name = 'test', float $weight = 5.0): ProductBasicStruct
    {
        $product = new ProductBasicStruct();
        $product->setStock($stock);
        $product->setName($name);
        $product->setWeight($weight);
        $product->setMinDeliveryTime(1);
        $product->setMaxDeliveryTime(2);

        return $product;
    }
}
