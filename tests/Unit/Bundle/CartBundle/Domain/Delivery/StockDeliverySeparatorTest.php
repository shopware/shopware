<?php
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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Domain\Delivery;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Delivery\Struct\Delivery;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Cart\Delivery\DeliveryInformation;
use Shopware\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Cart\Delivery\StockDeliverySeparator;
use Shopware\Cart\LineItem\CalculatedLineItemCollection;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Cart\Price\Struct\Price;
use Shopware\Cart\Price\PriceCalculator;
use Shopware\Cart\Price\PriceRounding;
use Shopware\CartBridge\Product\Struct\CalculatedProduct;
use Shopware\CartBridge\Product\ProductProcessor;
use Shopware\Cart\Rule\Container\AndRule;
use Shopware\Cart\Tax\Struct\CalculatedTax;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\TaxCalculator;
use Shopware\Cart\Tax\Struct\TaxRule;
use Shopware\Cart\Tax\TaxRuleCalculator;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\CartBridge\Voucher\Struct\CalculatedVoucher;
use Shopware\Address\Struct\Address;
use Shopware\CountryArea\Struct\CountryArea;
use Shopware\Country\Struct\Country;
use Shopware\CountryState\Struct\CountryState;
use Shopware\ShippingMethod\Struct\ShippingMethod;
use Shopware\Tests\Unit\Bundle\CartBundle\Common\Generator;

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
        static::assertEquals(
            new DeliveryCollection(),
            $this->separator->addItemsToDeliveries(
                new DeliveryCollection(),
                new CalculatedLineItemCollection(),
                Generator::createContext()
            )
        );
    }

    public function testDeliverableItemCanBeAddedToDelivery(): void
    {
        $location = self::createShippingLocation();

        $item = new CalculatedProduct(
            'A',
            1,
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 100),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new DeliveryInformation(
                1, 0, 0, 0, 0,
                new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05'))
            ),
            new AndRule()
        );

        static::assertEquals(
            new \Shopware\Cart\Delivery\Struct\DeliveryCollection([
                new Delivery(
                    new \Shopware\Cart\Delivery\Struct\DeliveryPositionCollection([
                        DeliveryPosition::createByLineItemForInStockDate($item),
                    ]),
                    new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                    new ShippingMethod(1, '', ShippingMethod::CALCULATION_BY_WEIGHT, true, 1),
                    $location,
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $this->separator->addItemsToDeliveries(
                new \Shopware\Cart\Delivery\Struct\DeliveryCollection(),
                new CalculatedLineItemCollection([$item]),
                Generator::createContext(null, null, null, null, null, null, $location->getArea(), $location->getCountry(), $location->getState())
            )
        );
    }

    public function testCanDeliveryItemsWithSameDeliveryDateTogether(): void
    {
        $location = self::createShippingLocation();

        $deliveryInformation = new DeliveryInformation(
            5, 0, 0, 0, 0,
            new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
            new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05'))
        );

        $itemA = new CalculatedProduct('A', 5,
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 5),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $deliveryInformation,
            new AndRule()
        );

        $itemB = new CalculatedProduct('B', 5,
            new LineItem('B', ProductProcessor::TYPE_PRODUCT, 5),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            $deliveryInformation,
            new AndRule()
        );

        $result = $this->separator->addItemsToDeliveries(
            new \Shopware\Cart\Delivery\Struct\DeliveryCollection(),
            new CalculatedLineItemCollection([$itemA, $itemB]),
            Generator::createContext(null, null, null, null, null, null, $location->getArea(), $location->getCountry(), $location->getState())
        );

        static::assertEquals(
            new \Shopware\Cart\Delivery\Struct\DeliveryCollection([
                new \Shopware\Cart\Delivery\Struct\Delivery(
                    new \Shopware\Cart\Delivery\Struct\DeliveryPositionCollection([
                        DeliveryPosition::createByLineItemForInStockDate($itemA),
                        \Shopware\Cart\Delivery\Struct\DeliveryPosition::createByLineItemForInStockDate($itemB),
                    ]),
                    $deliveryInformation->getInStockDeliveryDate(),
                    new ShippingMethod(1, '', ShippingMethod::CALCULATION_BY_WEIGHT, true, 1),
                    $location,
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $result
        );
    }

    public function testOutOfStockItemsCanBeDelivered(): void
    {
        $location = self::createShippingLocation();

        $itemA = new CalculatedProduct('A', 5,
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 5),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new DeliveryInformation(
                0, 0, 0, 0, 0,
                new \Shopware\Cart\Delivery\Struct\DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
                new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05'))
            ),
            new AndRule()
        );
        $itemB = new CalculatedProduct('B', 5,
            new LineItem('B', ProductProcessor::TYPE_PRODUCT, 5),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new DeliveryInformation(
                0, 0, 0, 0, 0,
                new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-02')),
                new \Shopware\Cart\Delivery\Struct\DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05'))
            ),
            new AndRule()
        );

        static::assertEquals(
            new \Shopware\Cart\Delivery\Struct\DeliveryCollection([
                new \Shopware\Cart\Delivery\Struct\Delivery(
                    new \Shopware\Cart\Delivery\Struct\DeliveryPositionCollection([
                        \Shopware\Cart\Delivery\Struct\DeliveryPosition::createByLineItemForOutOfStockDate($itemA),
                        \Shopware\Cart\Delivery\Struct\DeliveryPosition::createByLineItemForOutOfStockDate($itemB),
                    ]),
                    new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05')),
                    new ShippingMethod(1, '', ShippingMethod::CALCULATION_BY_WEIGHT, true, 1),
                    $location,
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $this->separator->addItemsToDeliveries(
                new \Shopware\Cart\Delivery\Struct\DeliveryCollection(),
                new CalculatedLineItemCollection([$itemA, $itemB]),
                Generator::createContext(null, null, null, null, null, null, $location->getArea(), $location->getCountry(), $location->getState())
            )
        );
    }

    public function testNoneDeliverableItemBeIgnored(): void
    {
        $location = self::createShippingLocation();
        $product = new CalculatedProduct('A', 5,
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 5),
            new Price(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new DeliveryInformation(
                10, 0, 0, 0, 0,
                new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
                new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-05'))
            ),
            new AndRule()
        );
        $voucher = new CalculatedVoucher(
            'Code1',
            new LineItem('B', 'discount', 1),
            new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            new AndRule()
        );

        static::assertEquals(
            new DeliveryCollection([
                new Delivery(
                    new \Shopware\Cart\Delivery\Struct\DeliveryPositionCollection([
                        DeliveryPosition::createByLineItemForInStockDate($product),
                    ]),
                    $product->getInStockDeliveryDate(),
                    new ShippingMethod(1, '', ShippingMethod::CALCULATION_BY_WEIGHT, true, 1),
                    $location,
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $this->separator->addItemsToDeliveries(
                new DeliveryCollection(),
                new CalculatedLineItemCollection([$product, $voucher]),
                Generator::createContext(null, null, null, null, null, null, $location->getArea(), $location->getCountry(), $location->getState())
            )
        );
    }

    public function testPositionWithMoreQuantityThanStockWillBeSplitted(): void
    {
        $location = self::createShippingLocation();

        $product = new CalculatedProduct('A', 12,
            new LineItem('A', ProductProcessor::TYPE_PRODUCT, 10),
            new Price(1.19, 11.90, new CalculatedTaxCollection([new CalculatedTax(1.9, 19, 11.90)]), new TaxRuleCollection([new TaxRule(19)]), 10),
            new DeliveryInformation(5, 0, 0, 0, 0,
                new DeliveryDate(new \DateTime('2012-01-01'), new \DateTime('2012-01-03')),
                new DeliveryDate(new \DateTime('2012-01-04'), new \DateTime('2012-01-06'))
            ),
            new AndRule()
        );

        static::assertEquals(
            new \Shopware\Cart\Delivery\Struct\DeliveryCollection([
                new \Shopware\Cart\Delivery\Struct\Delivery(
                    new \Shopware\Cart\Delivery\Struct\DeliveryPositionCollection([
                        new DeliveryPosition('A', $product, 5,
                            new Price(1.19, 5.95, new CalculatedTaxCollection([new CalculatedTax(0.95, 19, 5.95)]), new TaxRuleCollection([new TaxRule(19)]), 5),
                            $product->getInStockDeliveryDate()
                        ),
                    ]),
                    $product->getInStockDeliveryDate(),
                    new ShippingMethod(1, '', ShippingMethod::CALCULATION_BY_WEIGHT, true, 1),
                    $location,
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
                new \Shopware\Cart\Delivery\Struct\Delivery(
                    new DeliveryPositionCollection([
                        new DeliveryPosition('A', $product, 7,
                            new Price(1.19, 8.33, new CalculatedTaxCollection([new CalculatedTax(1.33, 19, 8.33)]), new TaxRuleCollection([new TaxRule(19)]), 7),
                            $product->getOutOfStockDeliveryDate()
                        ),
                    ]),
                    $product->getOutOfStockDeliveryDate(),
                    new ShippingMethod(1, '', ShippingMethod::CALCULATION_BY_WEIGHT, true, 1),
                    $location,
                    new Price(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
                ),
            ]),
            $this->separator->addItemsToDeliveries(
                new DeliveryCollection(),
                new CalculatedLineItemCollection([$product]),
                Generator::createContext(null, null, null, null, null, null, $location->getArea(), $location->getCountry(), $location->getState())
            )
        );
    }

    private static function createShippingLocation(): ShippingLocation
    {
        $address = new Address();
        $address->setState(new CountryState());

        $country = new Country();
        $country->setArea(new CountryArea());

        $address->setCountry($country);
        $address->getState()->setCountry($country);

        return \Shopware\Cart\Delivery\Struct\ShippingLocation::createFromAddress($address);
    }
}
