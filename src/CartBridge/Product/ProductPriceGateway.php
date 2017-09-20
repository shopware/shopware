<?php
declare(strict_types=1);
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

namespace Shopware\CartBridge\Product;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\Price\PriceDefinition;
use Shopware\Cart\Price\PriceDefinitionCollection;
use Shopware\Cart\Product\ProductPriceCollection;
use Shopware\Cart\Tax\TaxRule;
use Shopware\Cart\Tax\TaxRuleCollection;
use Shopware\Context\Struct\ShopContext;

class ProductPriceGateway implements ProductPriceGatewayInterface
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function get(array $numbers, ShopContext $context): ProductPriceCollection
    {
        $query = $this->buildQuery($numbers, $context);

        $data = $query->execute()->fetchAll(\PDO::FETCH_GROUP);

        $productPrices = new ProductPriceCollection();

        /* @var LineItemInterface $lineItem */
        foreach ($numbers as $number) {
            if (!array_key_exists($number, $data)) {
                continue;
            }

            $definitions = $this->findCustomerGroupPrice(
                $data[$number],
                $context->getCurrentCustomerGroup()->getUuid(),
                $context->getFallbackCustomerGroup()->getUuid()
            );

            if (!$definitions) {
                continue;
            }

            $prices = new PriceDefinitionCollection();

            foreach ($definitions as $index => $definition) {
                $price = new PriceDefinition(
                    (float) $definition['price_net'],
                    new TaxRuleCollection([
                        new TaxRule((float) $definition['__tax_tax']),
                    ]),
                    (int) $definition['price_from_quantity']
                );

                $prices->add($price);
            }
            $productPrices->add($number, $prices);
        }

        return $productPrices;
    }

    private function findCustomerGroupPrice(array $prices, string $currentKey, string $fallbackKey): array
    {
        $filtered = $this->filterCustomerGroupPrices($prices, $currentKey);
        if ($filtered) {
            return $filtered;
        }

        return $this->filterCustomerGroupPrices($prices, $fallbackKey);
    }

    private function filterCustomerGroupPrices(array $prices, string $key): array
    {
        return array_filter($prices, function ($price) use ($key) {
            return $price['customer_group_uuid'] === $key;
        });
    }

    private function buildQuery(array $numbers, ShopContext $context): QueryBuilder
    {
        $query = $this->connection->createQueryBuilder();

        $query->select('variant.uuid as arrayKey');

        $query->addSelect([
            'price.customer_group_uuid',
            'price.quantity_start as price_from_quantity',
            'price.quantity_end as price_to_quantity',
            'price.price as price_net',
            'tax.tax_rate as __tax_tax',
        ]);

        $query->from('product_price', 'price');
        $query->innerJoin('price', 'product_detail', 'variant', 'variant.uuid = price.product_detail_uuid');
        $query->innerJoin('variant', 'product', 'product', 'product.uuid = variant.product_uuid');
        $query->innerJoin('variant', 'tax', 'tax', 'tax.uuid = product.tax_uuid');
        $query->where('variant.uuid IN (:numbers)');
        $query->setParameter('numbers', $numbers, Connection::PARAM_STR_ARRAY);

        $customerGroups = array_unique([
            $context->getCurrentCustomerGroup()->getUuid(),
            $context->getFallbackCustomerGroup()->getUuid(),
        ]);
        $query->andWhere('price.customer_group_uuid IN (:customerGroups)');
        $query->setParameter('customerGroups', $customerGroups, Connection::PARAM_STR_ARRAY);

        return $query;
    }
}
