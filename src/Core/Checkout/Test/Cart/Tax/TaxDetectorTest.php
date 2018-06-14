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

namespace Shopware\Core\Checkout\Test\Cart\Tax;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Tax\TaxDetector;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\Core\System\Country\Struct\CountryBasicStruct;

class TaxDetectorTest extends TestCase
{
    public function testUseGrossPrices(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $customerGroup = $this->createMock(CustomerGroupBasicStruct::class);
        $customerGroup->expects($this->once())->method('getDisplayGross')->will($this->returnValue(true));
        $context->expects($this->once())->method('getCurrentCustomerGroup')->will($this->returnValue($customerGroup));

        $detector = new TaxDetector();
        $this->assertTrue($detector->useGross($context));
    }

    public function testDoNotUseGrossPrices(): void
    {
        $context = $this->createMock(CheckoutContext::class);
        $customerGroup = $this->createMock(CustomerGroupBasicStruct::class);
        $customerGroup->expects($this->once())->method('getDisplayGross')->will($this->returnValue(false));
        $context->expects($this->once())->method('getCurrentCustomerGroup')->will($this->returnValue($customerGroup));

        $detector = new TaxDetector();
        $this->assertFalse($detector->useGross($context));
    }

    public function testIsNetDelivery(): void
    {
        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryBasicStruct();
        $country->setTaxFree(true);

        $context->expects($this->once())->method('getShippingLocation')->will($this->returnValue(
            ShippingLocation::createFromCountry($country)
        ));

        $detector = new TaxDetector();
        $this->assertTrue($detector->isNetDelivery($context));
    }

    public function testIsNotNetDelivery(): void
    {
        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryBasicStruct();
        $country->setTaxFree(false);

        $context->expects($this->once())->method('getShippingLocation')->will($this->returnValue(
            ShippingLocation::createFromCountry($country)
        ));

        $detector = new TaxDetector();
        $this->assertFalse($detector->isNetDelivery($context));
    }
}
