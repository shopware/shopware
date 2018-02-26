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

namespace Shopware\Context\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Customer\Struct\CustomerBasicStruct;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Context\Rule\IsNewCustomerRule;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class IsNewCustomerRuleTest extends TestCase
{
    public function testIsNewCustomer(): void
    {
        $rule = new IsNewCustomerRule();

        $cart = $this->createMock(CalculatedCart::class);

        $customer = new CustomerBasicStruct();
        $customer->setFirstLogin(new \DateTime());

        $context = $this->createMock(StorefrontContext::class);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertTrue(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testIsNotNewCustomer(): void
    {
        $rule = new IsNewCustomerRule();

        $cart = $this->createMock(CalculatedCart::class);

        $customer = new CustomerBasicStruct();
        $customer->setFirstLogin(
            (new \DateTime())->sub(
                new \DateInterval('P' . 10 . 'D')
            )
        );

        $context = $this->createMock(StorefrontContext::class);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testWithFutureDate(): void
    {
        $rule = new IsNewCustomerRule();

        $cart = $this->createMock(CalculatedCart::class);

        $customer = new CustomerBasicStruct();
        $customer->setFirstLogin(
            (new \DateTime())->add(
                new \DateInterval('P' . 10 . 'D')
            )
        );

        $context = $this->createMock(StorefrontContext::class);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }

    public function testWithoutCustomer(): void
    {
        $rule = new IsNewCustomerRule();

        $cart = $this->createMock(CalculatedCart::class);

        $context = $this->createMock(StorefrontContext::class);

        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue(null));

        $this->assertFalse(
            $rule->match($cart, $context, new StructCollection())->matches()
        );
    }
}
