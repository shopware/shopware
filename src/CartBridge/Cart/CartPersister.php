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

namespace Shopware\CartBridge\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Cart\Cart\CartContainer;
use Shopware\Cart\Cart\CartPersisterInterface;
use Shopware\Cart\Exception\CartTokenNotFoundException;
use Shopware\Serializer\JsonSerializer;

class CartPersister implements CartPersisterInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var JsonSerializer
     */
    private $serializer;

    public function __construct(Connection $connection, JsonSerializer $serializer)
    {
        $this->connection = $connection;
        $this->serializer = $serializer;
    }

    public function load(string $token): CartContainer
    {
        $content = $this->connection->fetchColumn(
            'SELECT content FROM s_cart WHERE `token` = :token',
            [':token' => $token]
        );

        if (false === $content) {
            throw new CartTokenNotFoundException($token);
        }

        return $this->serializer->deserialize($content);
    }

    public function save(CartContainer $cartContainer): void
    {
        $this->connection->executeUpdate(
            'INSERT INTO `s_cart` (`token`, `name`, `content`) 
             VALUES (:token, :name, :content)
             ON DUPLICATE KEY UPDATE `name` = :name, `content` = :content',
            [
                ':token' => $cartContainer->getToken(),
                ':name' => $cartContainer->getName(),
                ':content' => $this->serializer->serialize($cartContainer),
            ]
        );
    }
}
