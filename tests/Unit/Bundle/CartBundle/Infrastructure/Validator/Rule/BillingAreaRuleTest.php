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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Infrastructure\Validator\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\CartBridge\Rule\BillingAreaRule;
use Shopware\Address\Struct\Address;
use Shopware\Framework\Struct\StructCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\CountryArea\Struct\CountryArea;
use Shopware\Country\Struct\Country;
use Shopware\Customer\Struct\Customer;

class BillingAreaRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = new BillingAreaRule([1]);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $country = new Country();

        $area = new CountryArea();
        $area->setId(1);
        $country->setArea($area);

        $billing = new Address();
        $billing->setCountry($country);

        $customer = new Customer();
        $customer->setActiveBillingAddress($billing);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testWithNotMatch(): void
    {
        $rule = new BillingAreaRule([2]);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $country = new Country();

        $area = new CountryArea();
        $area->setId(1);
        $country->setArea($area);

        $billing = new Address();
        $billing->setCountry($country);

        $customer = new Customer();
        $customer->setActiveBillingAddress($billing);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testMultipleCountries(): void
    {
        $rule = new BillingAreaRule([1, 3, 2]);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $country = new Country();

        $area = new CountryArea();
        $area->setId(3);
        $country->setArea($area);

        $billing = new Address();
        $billing->setCountry($country);

        $customer = new Customer();
        $customer->setActiveBillingAddress($billing);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = new BillingAreaRule([1, 3, 2]);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(ShopContext::class);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue(null));

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }
}
