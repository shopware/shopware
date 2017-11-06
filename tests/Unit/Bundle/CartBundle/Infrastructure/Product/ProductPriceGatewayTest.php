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

namespace Shopware\Tests\Unit\Bundle\CartBundle\Infrastructure\Product;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Cart\Price\Struct\PriceDefinition;
use Shopware\Cart\Price\PriceDefinitionCollection;
use Shopware\Cart\Product\ProductPriceCollection;
use Shopware\Cart\Tax\Struct\TaxRule;
use Shopware\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\CartBridge\Product\ProductPriceGateway;
use Shopware\Framework\Struct\FieldHelper;
use Shopware\CustomerGroup\Struct\CustomerGroup;
use Shopware\Tax\Struct\TaxHydrator;
use Shopware\Tests\Unit\Bundle\CartBundle\Common\Generator;

class ProductPriceGatewayTest extends TestCase
{
    const QUERY_TO_UNLIMITED = 'beliebig';

    public function testNoProductPricesDefined(): void
    {
        $gateway = new ProductPriceGateway(
            $this->createDatabaseMock([]),
            $this->createMock(FieldHelper::class),
            new TaxHydrator()
        );

        $context = Generator::createContext(
            $this->createCustomerGroup('EK1'), //current customer group
            $this->createCustomerGroup('EK2')  //fallback customer group
        );

        $prices = $gateway->get(['SW1'], $context);
        static::assertEquals(new ProductPriceCollection(), $prices);
    }

    public function testReturnsPriceDefinitionIndexedByNumber(): void
    {
        $gateway = new ProductPriceGateway(
            $this->createDatabaseMock([
                'SW1' => [
                    PriceQueryRow::create('EK1', 1, self::QUERY_TO_UNLIMITED, 20.10, 19),
                ],
            ]),
            $this->createMock(FieldHelper::class),
            new TaxHydrator()
        );

        $context = Generator::createContext(
            $this->createCustomerGroup('EK1'), //current customer group
            $this->createCustomerGroup('EK2')  //fallback customer group
        );

        $prices = $gateway->get(['SW1'], $context);

        static::assertEquals(
            new ProductPriceCollection([
                'SW1' => new PriceDefinitionCollection([
                    new PriceDefinition(20.10, new TaxRuleCollection([new TaxRule(19)])),
                ]),
            ]),
            $prices
        );
    }

    public function testCurrentCustomerGroupPricesHasHigherPriority(): void
    {
        $gateway = new ProductPriceGateway(
            $this->createDatabaseMock([
                'SW1' => [
                    PriceQueryRow::create('EK2', 1, self::QUERY_TO_UNLIMITED, 5.10, 19),
                    PriceQueryRow::create('EK1', 1, self::QUERY_TO_UNLIMITED, 20.10, 19),
                ],
            ]),
            $this->createMock(FieldHelper::class),
            new TaxHydrator()
        );

        $context = Generator::createContext(
            $this->createCustomerGroup('EK1'), //current customer group
            $this->createCustomerGroup('EK2')  //fallback customer group
        );

        $prices = $gateway->get(['SW1'], $context);

        static::assertEquals(
            new ProductPriceCollection([
                'SW1' => new PriceDefinitionCollection([
                    new PriceDefinition(20.10, new TaxRuleCollection([new TaxRule(19)])),
                ]),
            ]),
            $prices
        );
    }

    public function testProductsWithFallbackCustomerGroupPrice(): void
    {
        $gateway = new ProductPriceGateway(
            $this->createDatabaseMock([
                'SW1' => [
                    PriceQueryRow::create('EK2', 1, self::QUERY_TO_UNLIMITED, 5.10, 19),
                    PriceQueryRow::create('EK1', 1, self::QUERY_TO_UNLIMITED, 20.10, 19),
                ],
                'SW2' => [
                    PriceQueryRow::create('EK2', 1, self::QUERY_TO_UNLIMITED, 5.10, 19),
                ],
            ]),
            $this->createMock(FieldHelper::class),
            new TaxHydrator()
        );

        $context = Generator::createContext(
            $this->createCustomerGroup('EK1'), //current customer group
            $this->createCustomerGroup('EK2')  //fallback customer group
        );

        $prices = $gateway->get(['SW1', 'SW2'], $context);

        static::assertEquals(
            new ProductPriceCollection([
                'SW1' => new PriceDefinitionCollection([
                    new PriceDefinition(20.10, new TaxRuleCollection([new TaxRule(19)])),
                ]),
                'SW2' => new PriceDefinitionCollection([
                    new PriceDefinition(5.10, new TaxRuleCollection([new TaxRule(19)])),
                ]),
            ]),
            $prices
        );
    }

    public function testLastGraduatedPriceOfCurrentCustomerGroup(): void
    {
        $gateway = new ProductPriceGateway(
            $this->createDatabaseMock([
                'SW1' => [
                    PriceQueryRow::create('EK2', 1, self::QUERY_TO_UNLIMITED, 5.10, 19),
                    PriceQueryRow::create('EK1', 1, 3, 20.10, 19),
                    PriceQueryRow::create('EK1', 4, self::QUERY_TO_UNLIMITED, 15.10, 19),
                ],
                'SW2' => [
                    PriceQueryRow::create('EK2', 1, self::QUERY_TO_UNLIMITED, 5.10, 19),
                ],
            ]),
            $this->createMock(FieldHelper::class),
            new TaxHydrator()
        );

        $context = Generator::createContext(
            $this->createCustomerGroup('EK1'), //current customer group
            $this->createCustomerGroup('EK2')  //fallback customer group
        );

        $prices = $gateway->get(['SW1', 'SW2'], $context);

        static::assertEquals(
            new ProductPriceCollection([
                'SW1' => new PriceDefinitionCollection([
                    new PriceDefinition(20.10, new TaxRuleCollection([new TaxRule(19)]), 1),
                    new PriceDefinition(15.10, new TaxRuleCollection([new TaxRule(19)]), 4),
                ]),
                'SW2' => new PriceDefinitionCollection([
                    new PriceDefinition(5.10, new TaxRuleCollection([new TaxRule(19)]), 1),
                ]),
            ]),
            $prices
        );
    }

    public function testUseFallbackPriceWithGraduation(): void
    {
        $gateway = new ProductPriceGateway(
            $this->createDatabaseMock([
                'SW1' => [
                    PriceQueryRow::create('EK2', 1, 2, 1.10, 19),
                    PriceQueryRow::create('EK2', 3, 4, 2.10, 19),
                    PriceQueryRow::create('EK2', 5, self::QUERY_TO_UNLIMITED, 3.10, 19),
                    PriceQueryRow::create('EK3', 1, self::QUERY_TO_UNLIMITED, 20.10, 19),
                ],
                'SW2' => [
                    PriceQueryRow::create('EK2', 1, self::QUERY_TO_UNLIMITED, 5.10, 19),
                ],
            ]),
            $this->createMock(FieldHelper::class),
            new TaxHydrator()
        );

        $context = Generator::createContext(
            $this->createCustomerGroup('EK1'), //current customer group
            $this->createCustomerGroup('EK2')  //fallback customer group
        );

        $prices = $gateway->get(['SW1', 'SW2'], $context);

        static::assertEquals(
            new ProductPriceCollection([
                'SW1' => new PriceDefinitionCollection([
                    new PriceDefinition(1.10, new TaxRuleCollection([new TaxRule(19)]), 1),
                    new PriceDefinition(2.10, new TaxRuleCollection([new TaxRule(19)]), 3),
                    new PriceDefinition(3.10, new TaxRuleCollection([new TaxRule(19)]), 5),
                ]),
                'SW2' => new PriceDefinitionCollection([
                    new PriceDefinition(5.10, new TaxRuleCollection([new TaxRule(19)]), 1),
                ]),
            ]),
            $prices
        );
    }

    /**
     * @param array[] $queryResult
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|Connection
     */
    private function createDatabaseMock($queryResult)
    {
        $statement = $this->createMock(Statement::class);
        $statement->expects(static::any())
            ->method('fetchAll')
            ->will(static::returnValue($queryResult));

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

    /**
     * @param string $key
     *
     * @return \Shopware\CustomerGroup\Struct\CustomerGroup
     */
    private function createCustomerGroup($key): CustomerGroup
    {
        $group = new CustomerGroup();
        $group->setKey($key);

        return $group;
    }
}

class PriceQueryRow
{
    public static function create(
        $customerGroupKey,
        $from,
        $to,
        $price,
        $taxRate,
        $taxId = null,
        $taxName = null
    ): array {
        return [
            'price_customer_group_key' => $customerGroupKey,
            'price_from_quantity' => $from,
            'price_to_quantity' => $to,
            'price_net' => $price,
            '__tax_id' => $taxId,
            '__tax_tax' => $taxRate,
            '__tax_description' => $taxName,
        ];
    }
}
