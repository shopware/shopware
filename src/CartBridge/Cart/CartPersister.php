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
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Cart\Cart\CartPersisterInterface;
use Shopware\Cart\Exception\CartTokenNotFoundException;
use Shopware\Context\Struct\ShopContext;
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

    public function load(string $token, string $name): CartContainer
    {
        $content = $this->connection->fetchColumn(
            'SELECT container FROM cart WHERE `token` = :token AND `name` = :name',
            ['token' => $token, 'name' => $name]
        );

        if ($content === false) {
            throw new CartTokenNotFoundException($token);
        }

        return $this->serializer->deserialize((string) $content);
    }

    public function save(CalculatedCart $cart, ShopContext $context): void
    {
        //prevent empty carts
        if ($cart->getCalculatedLineItems()->count() <= 0) {
            return;
        }

        $this->connection->executeUpdate(
            'DELETE FROM cart WHERE `token` = :token AND `name` = :name',
            ['token' => $cart->getToken(), 'name' => $cart->getName()]
        );
        
        $this->connection->insert('cart', [
            'token' => $cart->getToken(),
            'name' => $cart->getName(),
            'calculated' => $this->serializer->serialize($cart),
            'container' => $this->serializer->serialize($cart->getCartContainer()),
            'currency_uuid' => $context->getCurrency()->getUuid(),
            'shipping_method_uuid' => $context->getShippingMethod()->getUuid(),
            'payment_method_uuid' => $context->getPaymentMethod()->getUuid(),
            'country_uuid' => $context->getShippingLocation()->getCountry()->getUuid(),
            'customer_uuid' => $context->getCustomer() ? $context->getCustomer()->getUuid() : null,
            'shop_uuid' => $context->getShop()->getUuid(),
            'price' => $cart->getPrice()->getTotalPrice(),
            'line_item_count' => $cart->getCalculatedLineItems()->count(),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);
    }

    public function delete(string $token, ?string $name = null): void
    {
        if ($name === null) {
            $this->connection->executeUpdate(
                'DELETE FROM cart WHERE `token` = :token',
                ['token' => $token]
            );

            return;
        }

        $this->connection->executeUpdate(
            'DELETE FROM cart WHERE `token` = :token AND `name` = :name',
            ['token' => $token, 'name' => $name]
        );
    }
}
