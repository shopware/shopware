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

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart\Cart;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressStruct;
use Shopware\Core\Checkout\Customer\CustomerStruct;
use Shopware\Core\Checkout\Customer\Rule\BillingAreaRule;
use Shopware\Core\System\Country\CountryStruct;

class BillingAreaRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-1']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');
        $country->setAreaId('SWAG-AREA-ID-1');

        $billing = new CustomerAddressStruct();
        $billing->setCountry($country);

        $customer = new CustomerStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithNotMatch(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-2']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');
        $country->setAreaId('SWAG-AREA-ID-1');

        $billing = new CustomerAddressStruct();
        $billing->setCountry($country);

        $customer = new CustomerStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testMultipleCountries(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-1', 'SWAG-AREA-ID-3', 'SWAG-AREA-ID-2']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $country = new CountryStruct();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');
        $country->setAreaId('SWAG-AREA-ID-3');

        $billing = new CustomerAddressStruct();
        $billing->setCountry($country);

        $customer = new CustomerStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue($customer));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = new BillingAreaRule(['SWAG-AREA-ID-1', 'SWAG-AREA-ID-3', 'SWAG-AREA-ID-2']);

        $cart = $this->createMock(Cart::class);

        $context = $this->createMock(CheckoutContext::class);

        $context->expects(static::any())
            ->method('getCustomer')
            ->will(static::returnValue(null));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }
}
