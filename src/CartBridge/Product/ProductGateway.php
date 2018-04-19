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
            'variant.order_number',
            'variant.stock',
            'variant.weight',
            'variant.width',
            'variant.height',
            'variant.length',
            'variant.shipping_time',
            "GROUP_CONCAT(DISTINCT customerGroups.customer_group_id SEPARATOR '|') as blocked_groups",
            "GROUP_CONCAT(DISTINCT shop.id SEPARATOR '|') AS allowed_shops",
            'product.last_stock as closeout',
        ]);
        $query->from('product_detail', 'variant');
        $query->innerJoin('variant', 'product', 'product', 'product.id = variant.product_id');
        $query->leftJoin('variant', 'product_avoid_customer_group', 'customerGroups', 'customerGroups.product_id = variant.product_id');
        $query->leftJoin('variant', 'product_category_ro', 'categories_ro', 'categories_ro.product_id = variant.product_id');
        $query->leftJoin('categories_ro', 's_core_shops', 'shop', 'shop.category_id = categories_ro.category_id');
        $query->groupBy('variant.id');

        $query->where('variant.order_number IN (:numbers)');
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
            $ids = array_filter(explode('|', $row['blocked_groups']));
            $ids = array_map(function ($id) {
                return (int) $id;
            }, $ids);

            $rule->addRule(new CustomerGroupRule($ids));
        }

        if ($row['allowed_shops']) {
            $ids = array_filter(explode('|', $row['allowed_shops']));
            $ids = array_map(function ($id) {
                return (int) $id;
            }, $ids);

            $rule->addRule(new ShopRule($ids, Rule::OPERATOR_NEQ));
        }

        return $rule;
    }
}
