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
            'variant.uuid',
            'variant.product_uuid',
            'variant.stock',
            'variant.weight',
            'variant.width',
            'variant.height',
            'variant.length',
            '(
                SELECT GROUP_CONCAT(DISTINCT c.customer_group_uuid SEPARATOR \'|\') 
                FROM product_avoid_customer_group as c 
                WHERE c.product_uuid = variant.product_uuid
            ) as blocked_groups',

            '(
                SELECT GROUP_CONCAT(DISTINCT shop.uuid SEPARATOR \'|\')
                FROM shop
                    INNER JOIN product_category_ro categories_ro
                WHERE shop.category_uuid = categories_ro.category_uuid
                AND categories_ro.product_uuid = variant.product_uuid

             ) AS allowed_shops',
            'product.is_closeout as closeout',
        ]);
        $query->from('product_detail', 'variant');
        $query->innerJoin('variant', 'product', 'product', 'product.uuid = variant.product_uuid');

        // group by uuid is not possible at the moment since the column is not unique
        $query->where('variant.uuid IN (:numbers)');
        $query->setParameter('numbers', $numbers, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP | \PDO::FETCH_UNIQUE);
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
