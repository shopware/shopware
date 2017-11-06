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
use Shopware\Cart\Delivery\DeliveryDate;
use Shopware\Cart\Delivery\DeliveryInformation;
use Shopware\Cart\Product\ProductData;
use Shopware\Cart\Product\ProductDataCollection;
use Shopware\Cart\Product\ProductGatewayInterface;
use Shopware\Cart\Rule\Container\OrRule;
use Shopware\Cart\Rule\Rule;
use Shopware\CartBridge\Rule\CustomerGroupRule;
use Shopware\CartBridge\Rule\ShopRule;
use Shopware\Context\Struct\ShopContext;

class ProductGateway implements ProductGatewayInterface
{
    /**
     * @var ProductPriceGatewayInterface
     */
    private $priceGateway;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(ProductPriceGatewayInterface $priceGateway, Connection $connection)
    {
        $this->priceGateway = $priceGateway;
        $this->connection = $connection;
    }

    public function get(array $numbers, ShopContext $context): ProductDataCollection
    {
        $prices = $this->priceGateway->get($numbers, $context);

        $details = $this->getDetails($numbers, $context);

        $productCollection = new ProductDataCollection();

        foreach ($numbers as $number) {
            if (!$prices->has($number)) {
                continue;
            }

            if (!array_key_exists($number, $details)) {
                continue;
            }

            $deliveryInformation = $this->buildDeliveryInformation($details[$number]);

            $rule = $this->buildRule($details[$number]);

            $productCollection->add(
                new ProductData($number, $prices->get($number), $deliveryInformation, $rule)
            );
        }

        return $productCollection;
    }

    private function getDetails(array $numbers, ShopContext $context): array
    {
        /** @var QueryBuilder $query */
        $query = $this->connection->createQueryBuilder();

        $query->select([
            'product.uuid',
            'product.stock',
            'product.weight',
            'product.width',
            'product.height',
            'product.length',
            '(
                SELECT GROUP_CONCAT(DISTINCT c.customer_group_uuid SEPARATOR \'|\')
                FROM product_avoid_customer_group as c
                WHERE c.product_uuid = product.uuid
            ) as blocked_groups',
            'product.is_closeout as closeout',
        ]);
        $query->from('product', 'product');
        $query->where('product.uuid IN (:numbers)');
        $query->setParameter('numbers', $numbers, Connection::PARAM_STR_ARRAY);

        $data = $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);

        $query = $this->connection->createQueryBuilder();
        $query->select([
            'categories_ro.product_uuid',
            'GROUP_CONCAT(DISTINCT shop.uuid SEPARATOR \'|\')', ]);
        $query->from('shop');
        $query->innerJoin('shop', 'product_category_ro', 'categories_ro', 'shop.category_uuid = categories_ro.category_uuid');
        $query->andWhere('categories_ro.product_uuid IN (:uuids)');
        $query->setParameter('uuids', $numbers, Connection::PARAM_STR_ARRAY);
        $query->groupBy('categories_ro.product_uuid');

        $shopIds = $query->execute()->fetchAll(\PDO::FETCH_KEY_PAIR);

        foreach ($data as $uuid => &$row) {
            $row['allowed_shops'] = array_key_exists($uuid, $shopIds) ? $shopIds[$uuid] : '';
        }

        return $data;
    }

    private function buildDeliveryInformation(array $row): DeliveryInformation
    {
        $earliestInterval = new \DateInterval('P1D');
        $deliveryTimeInterval = new \DateInterval('P3D');
        $delayInterval = new \DateInterval('P10D');

        return new DeliveryInformation(
            (int) $row['stock'],
            (float) $row['height'],
            (float) $row['width'],
            (float) $row['length'],
            (float) $row['weight'],
            new DeliveryDate(
                (new \DateTime())
                    ->add($earliestInterval),
                (new \DateTime())
                    ->add($earliestInterval)
                    ->add($deliveryTimeInterval)
            ),
            new DeliveryDate(
                (new \DateTime())
                    ->add($delayInterval)
                    ->add($earliestInterval),
                (new \DateTime())
                    ->add($delayInterval)
                    ->add($earliestInterval)
                    ->add($deliveryTimeInterval)
            )
        );
    }

    /**
     * @param array $row
     *
     * @return Rule
     */
    private function buildRule(array $row): Rule
    {
        $rule = new OrRule();

        if (!empty($row['blocked_groups'])) {
            $uuids = array_filter(explode('|', $row['blocked_groups']));
            $rule->addRule(new CustomerGroupRule($uuids));
        }

        if ($row['allowed_shops']) {
            $uuids = array_filter(explode('|', $row['allowed_shops']));
            $rule->addRule(new ShopRule($uuids, Rule::OPERATOR_NEQ));
        }

        return $rule;
    }
}
