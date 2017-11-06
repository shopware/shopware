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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Infrastructure\Validator\Collector;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\TestCase;
use Shopware\Cart\Product\Struct\ProductFetchDefinition;
use Shopware\CartBridge\Rule\Collector\ProductAttributeRuleCollector;
use Shopware\CartBridge\Rule\Data\ProductAttributeRuleData;
use Shopware\CartBridge\Rule\ProductAttributeRule;
use Shopware\Framework\Struct\StructCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Tests\Unit\Bundle\CartBundle\Common\ValidatableDefinition;

class ProductAttributeRuleCollectorTest extends TestCase
{
    public function testWithoutRule(): void
    {
        $context = $this->createMock(ShopContext::class);

        $connection = $this->createMock(Connection::class);

        $collector = new ProductAttributeRuleCollector($connection);

        $dataCollection = new StructCollection();

        $collector->fetch($dataCollection, new StructCollection(), $context);

        $this->assertSame(0, $dataCollection->count());
    }

    public function testWithAttributeData(): void
    {
        $context = $this->createMock(ShopContext::class);

        $connection = $this->createConnection([
            ['attr1' => 100, 'attr2' => 200],
            ['attr1' => 200, 'attr2' => 300],
        ]);

        $collector = new ProductAttributeRuleCollector($connection);

        $dataCollection = new StructCollection([
            new ValidatableDefinition(new ProductAttributeRule('attr1', 100)),
        ]);

        $collector->fetch($dataCollection, new StructCollection([
            new ProductFetchDefinition(['SW1']),
        ]), $context);

        $this->assertSame(2, $dataCollection->count());

        /** @var ProductAttributeRuleData $data */
        $data = $dataCollection->get(ProductAttributeRuleData::class);

        $this->assertInstanceOf(ProductAttributeRuleData::class, $data);

        $this->assertTrue($data->hasAttributeValue('attr1', 100));
        $this->assertTrue($data->hasAttributeValue('attr1', 200));
        $this->assertTrue($data->hasAttributeValue('attr2', 200));
        $this->assertTrue($data->hasAttributeValue('attr2', 300));
    }

    public function testWithoutAttributeData(): void
    {
        $context = $this->createMock(ShopContext::class);

        $connection = $this->createConnection([]);

        $collector = new ProductAttributeRuleCollector($connection);

        $dataCollection = new StructCollection([
            new ValidatableDefinition(new ProductAttributeRule('attr1', 100)),
        ]);

        $collector->fetch($dataCollection, new StructCollection(), $context);

        $this->assertSame(1, $dataCollection->count());
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
