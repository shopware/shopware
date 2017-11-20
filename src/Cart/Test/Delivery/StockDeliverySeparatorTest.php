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

namespace Shopware\Cart\Test\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Delivery\StockDeliverySeparator;
use Shopware\Cart\Delivery\Struct\Delivery;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\PriceRounding;
use Shopware\Cart\Price\Struct\Price;
use Shopware\Cart\Rule\Container\AndRule;
use Shopware\Cart\Tax\Struct\CalculatedTax;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\TaxRule;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Cart\Tax\TaxCalculator;
use Shopware\Cart\Tax\TaxRuleCalculator;
use Shopware\Cart\Test\Common\Generator;
use Shopware\CartBridge\Product\ProductProcessor;
use Shopware\CartBridge\Product\Struct\CalculatedProduct;
use Shopware\CartBridge\Voucher\Struct\CalculatedVoucher;
use Shopware\Country\Struct\CountryBasicStruct;
use Shopware\Country\Struct\CountryStateBasicStruct;
use Shopware\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Shipping\Struct\ShippingMethodBasicStruct;

class StockDeliverySeparatorTest extends TestCase
{
    /**
     * @var StockDeliverySeparator
     */
    private $separator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->separator = new StockDeliverySeparator(
            new PriceCalculator(
                new TaxCalculator(
                    new PriceRounding(2),
                    [new TaxRuleCalculator(new PriceRounding(2))]
                ),
                new PriceRounding(2),
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

        $item = new CalculatedProduct(
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 100),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'A',
            1,
            1,
            0,
            new DeliveryDate(
                new \DateTime('2012-01-01'),
                new \DateTime('2012-01-02')
            ),
            new DeliveryDate(
                new \DateTime('2012-01-04'),
                new \DateTime('2012-01-05')
            ),
            new AndRule()
        );

        $deliveries = new DeliveryCollection();

        $this->separator->addItemsToDeliveries(
            $deliveries,
            new CalculatedLineItemCollection([$item]),
            Generator::createContext(null, null, null, null, null, null, $location->getAreaUuid(), $location->getCountry(), $location->getState())
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection([
                        DeliveryPosition::createByLineItemForInStockDate($item),
                    ]),
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                    (new ShippingMethodBasicStruct())->assign(['uuid' => '1']),
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

        $itemA = new CalculatedProduct(
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 5),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'A',
            5,
            5,
            0,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
            new AndRule()
        );

        $itemB = new CalculatedProduct(
            new LineItem('B', ProductProcessor::TYPE_PRODUCT, 5),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'B',
            5,
            5,
            0,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
            new AndRule()
        );

        $deliveries = new DeliveryCollection();

        $this->separator->addItemsToDeliveries(
            $deliveries,
            new CalculatedLineItemCollection([$itemA, $itemB]),
            Generator::createContext(null, null, null, null, null, null, $location->getAreaUuid(), $location->getCountry(), $location->getState())
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection([
                        DeliveryPosition::createByLineItemForInStockDate($itemA),
                        DeliveryPosition::createByLineItemForInStockDate($itemB),
                    ]),
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                    (new ShippingMethodBasicStruct())->assign(['uuid' => '1']),
                    $location,
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $deliveries
        );
    }

    public function testOutOfStockItemsCanBeDelivered(): void
    {
        $location = self::createShippingLocation();

        $itemA = new CalculatedProduct(
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 5),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'A',
            5,
            0,
            0,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
            null
        );
        $itemB = new CalculatedProduct(
            new LineItem('B', ProductProcessor::TYPE_PRODUCT, 5),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'B',
            5,
            0,
            0,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
            null
        );

        $deliveries = new DeliveryCollection();
        $this->separator->addItemsToDeliveries(
            $deliveries,
            new CalculatedLineItemCollection([$itemA, $itemB]),
            Generator::createContext(null, null, null, null, null, null, $location->getAreaUuid(), $location->getCountry(), $location->getState())
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection([
                        DeliveryPosition::createByLineItemForOutOfStockDate($itemA),
                        DeliveryPosition::createByLineItemForOutOfStockDate($itemB),
                    ]),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
                    (new ShippingMethodBasicStruct())->assign(['uuid' => '1']),
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
        $product = new CalculatedProduct(
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 5),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'A',
            5,
            10,
            0,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
            null
        );
        $voucher = new CalculatedVoucher(
            'Code1',
            new LineItem('B', 'discount', 1),
            new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new AndRule()
        );

        $deliveries = new DeliveryCollection();
        $this->separator->addItemsToDeliveries(
            $deliveries,
            new CalculatedLineItemCollection([$product, $voucher]),
            Generator::createContext(null, null, null, null, null, null, $location->getAreaUuid(), $location->getCountry(), $location->getState())
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection([
                        DeliveryPosition::createByLineItemForInStockDate($product),
                    ]),
                    $product->getInStockDeliveryDate(),
                    (new ShippingMethodBasicStruct())->assign(['uuid' => '1']),
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

        $product = new CalculatedProduct(
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 10),
            new Price(1.19, 11.90, new CalculatedTaxCollection([new CalculatedTax(1.9, 19, 11.90)]), new TaxRuleCollection([new TaxRule(19)]), 10),
            'A',
            12,
            5,
            0,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-06')),
            null
        );

        $deliveries = new DeliveryCollection();
        $this->separator->addItemsToDeliveries(
            $deliveries,
            new CalculatedLineItemCollection([$product]),
            Generator::createContext(null, null, null, null, null, null, $location->getAreaUuid(), $location->getCountry(), $location->getState())
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new DeliveryPositionCollection([
                        new DeliveryPosition('A', $product, 5,
                            new Price(1.19, 5.95, new CalculatedTaxCollection([new CalculatedTax(0.95, 19, 5.95)]), new TaxRuleCollection([new TaxRule(19)]), 5),
                            $product->getInStockDeliveryDate()
                        ),
                    ]),
                    $product->getInStockDeliveryDate(),
                    (new ShippingMethodBasicStruct())->assign(['uuid' => '1']),
                    $location,
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
                new Delivery(
                    new DeliveryPositionCollection([
                        new DeliveryPosition('A', $product, 7,
                            new Price(1.19, 8.33, new CalculatedTaxCollection([new CalculatedTax(1.33, 19, 8.33)]), new TaxRuleCollection([new TaxRule(19)]), 7),
                            $product->getOutOfStockDeliveryDate()
                        ),
                    ]),
                    $product->getOutOfStockDeliveryDate(),
                    (new ShippingMethodBasicStruct())->assign(['uuid' => '1']),
                    $location,
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
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
        $country->setAreaUuid('SWAG-AREA-UUID-1');

        $address->setCountry($country);
        $address->getCountryState()->setCountryUuid('SWAG-AREA-COUNTRY-UUID-1');

        return ShippingLocation::createFromAddress($address);
    }
}
