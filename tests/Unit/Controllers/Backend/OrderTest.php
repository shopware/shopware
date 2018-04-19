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

namespace Shopware\tests\Unit\Controllers\Backend;

use PHPUnit\Framework\TestCase;
use Shopware_Controllers_Backend_Order;

class OrderTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $controller;

    /**
     * @var \ReflectionMethod
     */
    private $method;

    protected function setUp()
    {
        $this->controller = $this->createPartialMock(Shopware_Controllers_Backend_Order::class, []);
        $class = new \ReflectionClass($this->controller);
        $this->method = $class->getMethod('resolveSortParameter');
        $this->method->setAccessible(true);
    }

    public function testSortByNonePrefixedColumn()
    {
        $sorts = [
            ['property' => 'orderTime', 'direction' => 'ASC'],
        ];

        $this->assertSame(
            [
                ['property' => 'orders.orderTime', 'direction' => 'ASC'],
            ],
            $this->method->invokeArgs($this->controller, [$sorts])
        );
    }

    public function testSortByMultipleColumnsWithoutPrefix()
    {
        $sorts = [
            ['property' => 'orderTime', 'direction' => 'ASC'],
            ['property' => 'active', 'direction' => 'ASC'],
        ];

        $this->assertSame(
            [
                ['property' => 'orders.orderTime', 'direction' => 'ASC'],
                ['property' => 'orders.active', 'direction' => 'ASC'],
            ],
            $this->method->invokeArgs($this->controller, [$sorts])
        );
    }

    public function testResolveSortParametersKeepsDirection()
    {
        $sorts = [
            ['property' => 'orderTime', 'direction' => 'DESC'],
            ['property' => 'active', 'direction' => 'DESC'],
            ['property' => 'customerId', 'direction' => 'ASC'],
        ];

        $this->assertSame(
            [
                ['property' => 'orders.orderTime', 'direction' => 'DESC'],
                ['property' => 'orders.active', 'direction' => 'DESC'],
                ['property' => 'orders.customerId', 'direction' => 'ASC'],
            ],
            $this->method->invokeArgs($this->controller, [$sorts])
        );
    }

    public function testResolveFunctionsKeepsPrefixedProperties()
    {
        $sorts = [
            ['property' => 'customer.name', 'direction' => 'DESC'],
            ['property' => 'customer.email', 'direction' => 'DESC'],
            ['property' => 'billing.countryId', 'direction' => 'ASC'],
        ];

        $this->assertSame(
            [
                ['property' => 'customer.name', 'direction' => 'DESC'],
                ['property' => 'customer.email', 'direction' => 'DESC'],
                ['property' => 'billing.countryId', 'direction' => 'ASC'],
            ],
            $this->method->invokeArgs($this->controller, [$sorts])
        );
    }

    public function testCustomerNameColumnResolvedToBillingNames()
    {
        $sorts = [
            ['property' => 'customerName', 'direction' => 'DESC'],
        ];

        $this->assertSame(
            [
                ['property' => 'billing.lastName', 'direction' => 'DESC'],
                ['property' => 'billing.firstName', 'direction' => 'DESC'],
                ['property' => 'billing.company', 'direction' => 'DESC'],
            ],
            $this->method->invokeArgs($this->controller, [$sorts])
        );
    }

    public function testCustomerEmailAliasResolvedToAssociatedColumn()
    {
        $sorts = [
            ['property' => 'customerEmail', 'direction' => 'DESC'],
        ];

        $this->assertSame(
            [
                ['property' => 'customer.email', 'direction' => 'DESC'],
            ],
            $this->method->invokeArgs($this->controller, [$sorts])
        );
    }
}
