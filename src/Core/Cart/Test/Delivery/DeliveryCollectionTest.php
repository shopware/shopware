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
use Shopware\System\Country\Struct\CountryBasicStruct;
use Shopware\Api\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Cart\Delivery\Struct\Delivery;
use Shopware\Cart\Delivery\Struct\DeliveryCollection;
use Shopware\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Cart\Delivery\Struct\DeliveryPositionCollection;
use Shopware\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Cart\Price\Struct\CalculatedPrice;
use Shopware\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;

class DeliveryCollectionTest extends TestCase
{
    public function testCollectionIsCountable(): void
    {
        $collection = new DeliveryCollection();
        static::assertCount(0, $collection);
        static::assertSame(0, $collection->count());
    }

    public function testAddFunctionAddsANewDelivery(): void
    {
        $collection = new DeliveryCollection();
        $collection->add(
            new Delivery(
                new DeliveryPositionCollection(),
                new DeliveryDate(
                    new \DateTime(),
                    new \DateTime()
                ),
                new DefaultShippingMethod(),
                self::createShippingLocation(),
                new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
            )
        );
        static::assertCount(1, $collection);
    }

    public function testCollectionCanBeFilledByConstructor(): void
    {
        $collection = new DeliveryCollection([
            new Delivery(
                new DeliveryPositionCollection(),
                new DeliveryDate(
                    new \DateTime(),
                    new \DateTime()
                ),
                new DefaultShippingMethod(),
                self::createShippingLocation(),
                new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
            ),
            new Delivery(
                new DeliveryPositionCollection(),
                new DeliveryDate(
                    new \DateTime(),
                    new \DateTime()
                ),
                new DefaultShippingMethod(),
                self::createShippingLocation(),
                new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
            ),
        ]);
        static::assertCount(2, $collection);
    }

    public function testCollectionCanBeCleared(): void
    {
        $collection = new DeliveryCollection([
            new Delivery(
                new DeliveryPositionCollection(),
                new DeliveryDate(
                    new \DateTime(),
                    new \DateTime()
                ),
                new DefaultShippingMethod(),
                self::createShippingLocation(),
                new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
            ),
            new Delivery(
                new DeliveryPositionCollection(),
                new DeliveryDate(
                    new \DateTime(),
                    new \DateTime()
                ),
                new DefaultShippingMethod(),
                self::createShippingLocation(),
                new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection())
            ),
        ]);
        $collection->clear();
        static::assertCount(0, $collection);
    }

    private static function createShippingLocation(): ShippingLocation
    {
        $country = new CountryBasicStruct();
        $country->setAreaId('SWAG-AREA-ID-1');

        return ShippingLocation::createFromCountry($country);
    }
}

class DefaultShippingMethod extends ShippingMethodBasicStruct
{
    protected $minDeliveryTime = 1;
    protected $maxDeliveryTime = 1;
}
