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

namespace Shopware\Application\Test\Context\Rule\Context;

use PHPUnit\Framework\TestCase;
use Shopware\Checkout\Customer\Aggregate\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\Checkout\Customer\Struct\CustomerBasicStruct;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Application\Context\Rule\MatchContext\CartRuleMatchContext;
use Shopware\Application\Context\Rule\Context\BillingStreetRule;
use Shopware\Application\Context\Struct\StorefrontContext;

class BillingStreetRuleTest extends TestCase
{
    public function testWithExactMatch(): void
    {
        $rule = new BillingStreetRule('example street');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $billing = new CustomerAddressBasicStruct();
        $billing->setStreet('example street');

        $customer = new CustomerBasicStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }

    public function testCaseInsensitive(): void
    {
        $rule = new BillingStreetRule('ExaMple StreEt');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $billing = new CustomerAddressBasicStruct();
        $billing->setStreet('example street');

        $customer = new CustomerBasicStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }

    public function testNotMatch(): void
    {
        $rule = new BillingStreetRule('example street');

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $billing = new CustomerAddressBasicStruct();
        $billing->setStreet('test street');

        $customer = new CustomerBasicStruct();
        $customer->setDefaultBillingAddress($billing);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertFalse(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }

    public function testWithoutAddress(): void
    {
        $rule = new BillingStreetRule('ExaMple StreEt');

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
