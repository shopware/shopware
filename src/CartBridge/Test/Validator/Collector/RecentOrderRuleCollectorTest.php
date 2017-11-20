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

namespace Shopware\CartBridge\Test\Validator\Collector;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Cart\Test\Common\ValidatableDefinition;
use Shopware\CartBridge\Rule\Collector\RecentOrderRuleCollector;
use Shopware\CartBridge\Rule\Data\RecentOrderRuleData;
use Shopware\CartBridge\Rule\RecentOrderRule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Framework\Struct\StructCollection;

class RecentOrderRuleCollectorTest extends TestCase
{
    public function testWithoutRule(): void
    {
        $context = $this->createMock(ShopContext::class);

        $connection = $this->createMock(Connection::class);

        $collector = new RecentOrderRuleCollector($connection);

        $dataCollection = new StructCollection();

        $collector->fetch($dataCollection, new StructCollection(), $context);

        $this->assertSame(0, $dataCollection->count());
    }

    public function testWithoutCustomer(): void
    {
        $context = $this->createMock(ShopContext::class);

        $connection = $this->createMock(Connection::class);

        $collector = new RecentOrderRuleCollector($connection);

        $dataCollection = new StructCollection([
            new ValidatableDefinition(new RecentOrderRule(10)),
        ]);

        $collector->fetch($dataCollection, new StructCollection(), $context);

        $this->assertSame(1, $dataCollection->count());
    }

    public function testWithLastOrder(): void
    {
        $context = $this->createMock(ShopContext::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchColumn')
            ->will($this->returnValue('2012-01-01'));

        $customer = new CustomerBasicStruct();
        $customer->setUuid('SWAG-CUSTOMER-UUID-1');
        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $collector = new RecentOrderRuleCollector($connection);

        $dataCollection = new StructCollection([
            new ValidatableDefinition(new RecentOrderRule(10)),
        ]);

        $collector->fetch($dataCollection, new StructCollection(), $context);

        $this->assertSame(2, $dataCollection->count());

        /** @var RecentOrderRuleData $rule */
        $rule = $dataCollection->get(RecentOrderRuleData::class);

        $this->assertInstanceOf(RecentOrderRuleData::class, $rule);
        $this->assertEquals(
            new \DateTime('2012-01-01'),
            $rule->getRecentOrderTime()
        );
    }

    public function testWithoutLastOrder(): void
    {
        $context = $this->createMock(ShopContext::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchColumn')
            ->will($this->returnValue(null));

        $customer = new CustomerBasicStruct();
        $customer->setUuid('SWAG-CUSTOMER-UUID-1');
        $context->expects($this->any())
            ->method('getCustomer')
            ->will($this->returnValue($customer));

        $collector = new RecentOrderRuleCollector($connection);

        $dataCollection = new StructCollection([
            new ValidatableDefinition(new RecentOrderRule(10)),
        ]);

        $collector->fetch($dataCollection, new StructCollection(), $context);

        $this->assertSame(2, $dataCollection->count());

        /** @var RecentOrderRuleData $rule */
        $rule = $dataCollection->get(RecentOrderRuleData::class);

        $this->assertInstanceOf(RecentOrderRuleData::class, $rule);
        $this->assertNull($rule->getRecentOrderTime());
    }
}
