<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Exception\CartDeserializeFailedException;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Exception\InvalidUuidException;
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

    public function load(string $token, CheckoutContext $context): Cart
    {
        $content = $this->connection->fetchColumn(
            'SELECT `cart`.`cart` FROM cart WHERE `token` = :token',
            ['token' => $token]
        );

        if ($content === false) {
            throw new CartTokenNotFoundException($token);
        }

        $cart = $this->serializer->deserialize((string) $content, '', 'json');
        if (!$cart instanceof Cart) {
            throw new CartDeserializeFailedException();
        }

        return $cart;
    }

    /**
     * @throws InvalidUuidException
     */
    public function save(Cart $cart, CheckoutContext $context): void
    {
        //prevent empty carts
        if ($cart->getLineItems()->count() <= 0) {
            $this->delete($cart->getToken(), $context);

            return;
        }

        $this->delete($cart->getToken(), $context);

        $liveVersion = Uuid::fromStringToBytes(Defaults::LIVE_VERSION);

        $customerId = $context->getCustomer() ? Uuid::fromStringToBytes($context->getCustomer()->getId()) : null;

        $data = [
            'version_id' => $liveVersion,
            'token' => $cart->getToken(),
            'name' => $cart->getName(),
            'currency_id' => Uuid::fromStringToBytes($context->getCurrency()->getId()),
            'currency_version_id' => $liveVersion,
            'shipping_method_id' => Uuid::fromStringToBytes($context->getShippingMethod()->getId()),
            'shipping_method_version_id' => $liveVersion,
            'payment_method_id' => Uuid::fromStringToBytes($context->getPaymentMethod()->getId()),
            'payment_method_version_id' => $liveVersion,
            'country_id' => Uuid::fromStringToBytes($context->getShippingLocation()->getCountry()->getId()),
            'country_version_id' => $liveVersion,
            'sales_channel_id' => Uuid::fromStringToBytes($context->getSalesChannel()->getId()),
            'customer_id' => $customerId,
            'customer_version_id' => $context->getCustomer() ? $liveVersion : null,
            'price' => $cart->getPrice()->getTotalPrice(),
            'line_item_count' => $cart->getLineItems()->count(),
            'cart' => $this->serializer->serialize($cart, 'json'),
            'created_at' => (new \DateTime())->format(Defaults::DATE_FORMAT),
        ];

        $this->connection->insert('cart', $data);
    }

    /**
     * @throws InvalidUuidException
     */
    public function delete(string $token, CheckoutContext $context): void
    {
        $this->connection->executeUpdate(
            'DELETE FROM cart WHERE `token` = :token',
            ['token' => $token]
        );
    }
}
