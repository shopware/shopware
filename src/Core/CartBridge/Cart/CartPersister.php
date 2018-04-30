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
use Shopware\Cart\Cart\CartPersisterInterface;
use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\Cart;
use Shopware\Cart\Exception\CartTokenNotFoundException;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Defaults;
use Shopware\Framework\Struct\Uuid;
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

    public function load(string $token, string $name, StorefrontContext $context): Cart
    {
        $content = $this->connection->fetchColumn(
            'SELECT container FROM cart WHERE `token` = :token AND `name` = :name AND tenant_id = :tenant',
            ['token' => $token, 'name' => $name, 'tenant' => Uuid::fromHexToBytes($context->getTenantId())]
        );

        if ($content === false) {
            throw new CartTokenNotFoundException($token);
        }

        return $this->serializer->deserialize((string) $content, null, 'json');
    }

    public function loadCalculated(string $token, string $name, StorefrontContext $context): CalculatedCart
    {
        $content = $this->connection->fetchColumn(
            'SELECT calculated FROM cart WHERE `token` = :token AND `name` = :name AND tenant_id = :tenant',
            ['token' => $token, 'name' => $name, 'tenant' => Uuid::fromHexToBytes($context->getTenantId())]
        );

        if ($content === false) {
            throw new CartTokenNotFoundException($token);
        }

        return $this->serializer->deserialize((string) $content, null, 'json');
    }

    public function save(CalculatedCart $cart, StorefrontContext $context): void
    {
        //prevent empty carts
        if ($cart->getCalculatedLineItems()->count() <= 0) {
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
            'application_id' => Uuid::fromStringToBytes($context->getApplication()->getId()),
            'application_tenant_id' => $tenantId,
            'customer_id' => $customerId,
            'customer_tenant_id' => $tenantId,
            'customer_version_id' => $context->getCustomer() ? $liveVersion : null,
            'price' => $cart->getPrice()->getTotalPrice(),
            'line_item_count' => $cart->getCalculatedLineItems()->count(),
            'calculated' => $this->serializer->serialize($cart, 'json'),
            'container' => $this->serializer->serialize($cart->getCart(), 'json'),
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];

        $this->connection->insert('cart', $data);
    }

    public function delete(string $token, ?string $name = null, StorefrontContext $context): void
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
