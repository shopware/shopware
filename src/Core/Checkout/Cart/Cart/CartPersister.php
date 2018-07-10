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

namespace Shopware\Core\Checkout\Cart\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Serializer\SerializerInterface;

class CartPersister implements CartPersisterInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(Connection $connection, SerializerInterface $serializer)
    {
        $this->connection = $connection;
        $this->serializer = $serializer;
    }

    public function load(string $token, string $name, CheckoutContext $context): Cart
    {
        $content = $this->connection->fetchColumn(
            'SELECT `cart`.`cart` FROM cart WHERE `token` = :token AND `name` = :name AND tenant_id = :tenant',
            ['token' => $token, 'name' => $name, 'tenant' => Uuid::fromHexToBytes($context->getTenantId())]
        );

        if ($content === false) {
            throw new CartTokenNotFoundException($token);
        }

        return $this->serializer->deserialize((string) $content, '', 'json');
    }

    public function save(Cart $cart, CheckoutContext $context): void
    {
        //prevent empty carts
        if ($cart->getLineItems()->count() <= 0) {
            $this->delete($context->getToken(), $cart->getName(), $context);

            return;
        }

        $this->delete($context->getToken(), $cart->getName(), $context);

        $liveVersion = Uuid::fromStringToBytes(Defaults::LIVE_VERSION);

        $customerId = $context->getCustomer() ? Uuid::fromStringToBytes($context->getCustomer()->getId()) : null;

        $tenantId = Uuid::fromHexToBytes($context->getTenantId());

        $data = [
            'version_id' => $liveVersion,
            'tenant_id' => $tenantId,
            'token' => $context->getToken(),
            'name' => $cart->getName(),
            'currency_id' => Uuid::fromStringToBytes($context->getCurrency()->getId()),
            'currency_tenant_id' => $tenantId,
            'currency_version_id' => $liveVersion,
            'shipping_method_id' => Uuid::fromStringToBytes($context->getShippingMethod()->getId()),
            'shipping_method_tenant_id' => $tenantId,
            'shipping_method_version_id' => $liveVersion,
            'payment_method_id' => Uuid::fromStringToBytes($context->getPaymentMethod()->getId()),
            'payment_method_tenant_id' => $tenantId,
            'payment_method_version_id' => $liveVersion,
            'country_id' => Uuid::fromStringToBytes($context->getShippingLocation()->getCountry()->getId()),
            'country_tenant_id' => $tenantId,
            'country_version_id' => $liveVersion,
            'touchpoint_id' => Uuid::fromStringToBytes($context->getTouchpoint()->getId()),
            'touchpoint_tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'customer_tenant_id' => $tenantId,
            'customer_version_id' => $context->getCustomer() ? $liveVersion : null,
            'price' => $cart->getPrice()->getTotalPrice(),
            'line_item_count' => $cart->getLineItems()->count(),
            'cart' => $this->serializer->serialize($cart, 'json'),
            'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
        ];

        $this->connection->insert('cart', $data);
    }

    public function delete(string $token, ?string $name = null, CheckoutContext $context): void
    {
        if ($name === null) {
            $this->connection->executeUpdate(
                'DELETE FROM cart WHERE `token` = :token AND tenant_id = :tenant',
                ['token' => $token, 'tenant' => Uuid::fromHexToBytes($context->getTenantId())]
            );

            return;
        }

        $this->connection->executeUpdate(
            'DELETE FROM cart WHERE `token` = :token AND `name` = :name AND tenant_id = :tenant',
            ['token' => $token, 'name' => $name, 'tenant' => Uuid::fromHexToBytes($context->getTenantId())]
        );
    }
}
