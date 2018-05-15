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
use Shopware\Checkout\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Context\MatchContext\CartRuleMatchContext;
use Shopware\Context\Rule\Context\CustomerGroupRule;
use Shopware\Context\Struct\StorefrontContext;

class CustomerGroupRuleTest extends TestCase
{
    public function testMatch(): void
    {
        $rule = new CustomerGroupRule(['SWAG-CUSTOMER-GROUP-ID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $group = new CustomerGroupBasicStruct();
        $group->setId('SWAG-CUSTOMER-GROUP-ID-1');

        $context = $this->createMock(StorefrontContext::class);

        $context->expects($this->any())
            ->method('getCurrentCustomerGroup')
            ->will($this->returnValue($group));

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }

    public function testMultipleGroups(): void
    {
        $rule = new CustomerGroupRule(['SWAG-CUSTOMER-GROUP-ID-2', 'SWAG-CUSTOMER-GROUP-ID-3', 'SWAG-CUSTOMER-GROUP-ID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $group = new CustomerGroupBasicStruct();
        $group->setId('SWAG-CUSTOMER-GROUP-ID-3');

        $context = $this->createMock(StorefrontContext::class);

        $context->expects($this->any())
            ->method('getCurrentCustomerGroup')
            ->will($this->returnValue($group));

        $this->assertTrue(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }

    public function testNotMatch(): void
    {
        $rule = new CustomerGroupRule(['SWAG-CUSTOMER-GROUP-ID-2', 'SWAG-CUSTOMER-GROUP-ID-3', 'SWAG-CUSTOMER-GROUP-ID-1']);

        $cart = $this->createMock(CalculatedCart::class);

        $group = new CustomerGroupBasicStruct();
        $group->setId('SWAG-CUSTOMER-GROUP-ID-5');

        $context = $this->createMock(StorefrontContext::class);

        $context->expects($this->any())
            ->method('getCurrentCustomerGroup')
            ->will($this->returnValue($group));

        $this->assertFalse(
            $rule->match(new CartRuleMatchContext($cart, $context))->matches()
        );
    }
}
