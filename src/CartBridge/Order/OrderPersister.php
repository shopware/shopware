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

namespace Shopware\CartBridge\Order;

use Doctrine\DBAL\Connection;
use Shopware\Cart\Cart\CalculatedCart;
use Shopware\Cart\Order\OrderPersisterInterface;
use Shopware\Context\Struct\ShopContext;

class OrderPersister implements OrderPersisterInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function persist(CalculatedCart $calculatedCart, ShopContext $context): void
    {
        $this->connection->executeUpdate(
            'INSERT INTO `s_cart_order` (`token`, `name`, `content`, `order_time`) 
             VALUES (:token, :name, :content, :order_time)',
            [
                ':token' => $calculatedCart->getToken(),
                ':name' => $calculatedCart->getName(),
                ':content' => json_encode($calculatedCart),
                ':order_time' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]
        );
    }
}
