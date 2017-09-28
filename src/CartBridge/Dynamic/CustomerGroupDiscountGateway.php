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

namespace Shopware\CartBridge\Dynamic;

use Doctrine\DBAL\Connection;
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\LineItem\CalculatedLineItemInterface;
use Shopware\Cart\LineItem\Discount;
use Shopware\Cart\Price\PercentagePriceCalculator;
use Shopware\Context\Struct\ShopContext;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;

class CustomerGroupDiscountGateway
{
    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(PercentagePriceCalculator $percentagePriceCalculator, Connection $connection)
    {
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->connection = $connection;
    }

    public function get(CalculatedCart $cart, ShopContext $context): ? CalculatedLineItemInterface
    {
        if (!$context->getCustomer()) {
            return null;
        }

        $goods = $cart->getCalculatedLineItems()->filterGoods();

        if ($goods->count() === 0) {
            return null;
        }

        $prices = $goods->getPrices();

        $discount = $this->getDiscount(
            $context->getCurrentCustomerGroup(),
            $prices->sum()->getTotalPrice()
        );

        if ($discount === null) {
            return null;
        }

        $discount = $this->percentagePriceCalculator->calculate($discount, $prices, $context);

        return new Discount('customer-group-discount', $discount, 'Customer group discount');
    }

    private function getDiscount(CustomerGroupBasicStruct $customerGroup, float $price): ? float
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['discounts.percentage_discount']);
        $query->from('customer_group_discount', 'discounts');
        $query->andWhere('discounts.customer_group_uuid = :customer_group_uuid');
        $query->andWhere('discounts.minimum_cart_amount <= :price');
        $query->orderBy('minimum_cart_amount', 'DESC');
        $query->setParameter('price', $price);
        $query->setParameter('customer_group_uuid', $customerGroup->getUuid());
        $query->setMaxResults(1);

        $discount = $query->execute()->fetch(\PDO::FETCH_COLUMN);
        if ($discount !== false) {
            return (float) $discount * -1;
        }

        return null;
    }
}
