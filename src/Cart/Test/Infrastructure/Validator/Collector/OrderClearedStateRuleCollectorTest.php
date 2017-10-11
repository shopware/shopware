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

namespace Shopware\Cart\Test\Infrastructure\Validator\Collector;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\TestCase;
use Shopware\Cart\Test\Common\ValidatableDefinition;
use Shopware\CartBridge\Rule\Collector\OrderClearedStateRuleCollector;
use Shopware\CartBridge\Rule\Data\OrderClearedStateRuleData;
use Shopware\CartBridge\Rule\OrderClearedStateRule;
use Shopware\Context\Struct\ShopContext;
use Shopware\Customer\Struct\Customer;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Framework\Struct\StructCollection;

class OrderClearedStateRuleCollectorTest extends TestCase
{
    public function testWithoutRule(): void
    {
        $context = $this->createMock(ShopContext::class);

        $connection = $this->createMock(Connection::class);

        $collector = new OrderClearedStateRuleCollector($connection);

        $dataCollection = new StructCollection();

        $collector->fetch($dataCollection, new StructCollection(), $context);

        $this->assertSame(0, $dataCollection->count());
    }

    public function testWithoutCustomer(): void
    {
        $context = $this->createMock(ShopContext::class);

        $connection = $this->createMock(Connection::class);

        $collector = new OrderClearedStateRuleCollector($connection);

        $dataCollection = new StructCollection([
            new ValidatableDefinition(new OrderClearedStateRule([10])),
        ]);

        $collector->fetch($dataCollection, new StructCollection(), $context);

        $this->assertSame(1, $dataCollection->count());
    }

    public function testWithStates(): void
    {
        $context = $this->createMock(ShopContext::class);
        $customer = new CustomerBasicStruct();
        $customer->setUuid('SWAG-CUSTOMER-UUID-1');
        $context->method('getCustomer')
            ->will($this->returnValue($customer));

        $connection = $this->createConnection([1]);

        $collector = new OrderClearedStateRuleCollector($connection);

        $dataCollection = new StructCollection([
            new ValidatableDefinition(new OrderClearedStateRule([10])),
        ]);

        $collector->fetch($dataCollection, new StructCollection(), $context);

        $this->assertSame(2, $dataCollection->count());

        /* @var OrderClearedStateRuleData $rule */
        $rule = $dataCollection->get(OrderClearedStateRuleData::class);

        $this->assertInstanceOf(OrderClearedStateRuleData::class, $rule);
        $this->assertSame([1], $rule->getStates());
    }

    private function createConnection(?array $result): \PHPUnit_Framework_MockObject_MockObject
    {
        $statement = $this->createMock(Statement::class);
        $statement->expects(static::any())
            ->method('fetchAll')
            ->will(static::returnValue($result));

        $query = $this->createMock(QueryBuilder::class);
        $query->expects(static::any())
            ->method('execute')
            ->will(static::returnValue($statement));

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::any())
            ->method('createQueryBuilder')
            ->will(static::returnValue($query));

        return $connection;
    }
}
