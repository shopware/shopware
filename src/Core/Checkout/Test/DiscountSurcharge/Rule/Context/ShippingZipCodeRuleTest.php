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
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressBasicStruct;
use Shopware\Core\Checkout\Customer\Rule\ShippingZipCodeRule;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\System\Country\CountryBasicStruct;

class ShippingZipCodeRuleTest extends TestCase
{
    public function testEqualsWithSingleCode(): void
    {
        $rule = new ShippingZipCodeRule(['ABC123']);
        $address = $this->createAddress('ABC123');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(CheckoutContext::class);

        $location = ShippingLocation::createFromAddress($address);

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will($this->returnValue($location));

        $this->assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testEqualsWithMultipleCodes(): void
    {
        $rule = new ShippingZipCodeRule(['ABC1', 'ABC2', 'ABC3']);
        $address = $this->createAddress('ABC2');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(CheckoutContext::class);

        $location = ShippingLocation::createFromAddress($address);

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will($this->returnValue($location));

        $this->assertTrue(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testNotMatchWithSingleCode(): void
    {
        $rule = new ShippingZipCodeRule(['ABC1', 'ABC2', 'ABC3']);
        $address = $this->createAddress('ABC4');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(CheckoutContext::class);

        $location = ShippingLocation::createFromAddress($address);

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will($this->returnValue($location));

        $this->assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    public function testWithoutShippingAddress(): void
    {
        $rule = new ShippingZipCodeRule(['ABC1', 'ABC2', 'ABC3']);

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(CheckoutContext::class);

        $location = ShippingLocation::createFromCountry(new CountryBasicStruct());

        $context->expects($this->any())
            ->method('getShippingLocation')
            ->will($this->returnValue($location));

        $this->assertFalse(
            $rule->match(new CartRuleScope($cart, $context))->matches()
        );
    }

    private function createAddress(string $code): CustomerAddressBasicStruct
    {
        $address = new CustomerAddressBasicStruct();
        $address->setZipcode($code);
        $address->setCountry(new CountryBasicStruct());

        return $address;
    }
}
