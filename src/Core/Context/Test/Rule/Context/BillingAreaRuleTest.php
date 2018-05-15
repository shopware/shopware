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

namespace Shopware\Context\Test\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\System\Country\Struct\CountryBasicStruct;
use Shopware\Checkout\Customer\Struct\CustomerAddressBasicStruct;
use Shopware\Checkout\Customer\Struct\CustomerBasicStruct;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Context\MatchContext\CartRuleMatchContext;
use Shopware\Context\Rule\Context\BillingAreaRule;
use Shopware\Context\Struct\StorefrontContext;

class BillingAreaRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $country = new CountryBasicStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');
        $country->setAreaId('SWAG-AREA-ID-1');

        $billing = new CustomerAddressBasicStruct();
        $billing->setCountry($country);

        $customer = new CustomerBasicStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }

    public function testWithNotMatch(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-2']);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $country = new CountryBasicStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');
        $country->setAreaId('SWAG-AREA-ID-1');

        $billing = new CustomerAddressBasicStruct();
        $billing->setCountry($country);

        $customer = new CustomerBasicStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertFalse(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }

    public function testMultipleCountries(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-1', 'SWAG-AREA-ID-3', 'SWAG-AREA-ID-2']);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $country = new CountryBasicStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');
        $country->setAreaId('SWAG-AREA-ID-3');

        $billing = new CustomerAddressBasicStruct();
        $billing->setCountry($country);

        $customer = new CustomerBasicStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-1', 'SWAG-AREA-ID-3', 'SWAG-AREA-ID-2']);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue(null));

        $this->assertFalse(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }
}
