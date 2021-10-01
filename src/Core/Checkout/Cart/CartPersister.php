<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Shopware\Core\Checkout\Cart\Exception\CartDeserializeFailedException;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CartPersister implements CartPersisterInterface
{
    private Connection $connection;

    private EventDispatcherInterface $eventDispatcher;

    public function __construct(Connection $connection, EventDispatcherInterface $eventDispatcher)
    {
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(string $token, SalesChannelContext $context): Cart
    {
        $content = $this->connection->fetchAssociative(
            '#cart-persister::load
            SELECT `cart`.`cart`, `cart`.rule_ids FROM cart WHERE `token` = :token',
            ['token' => $token]
        );

        if (!\is_array($content)) {
            throw new CartTokenNotFoundException($token);
        }

        $cart = unserialize((string) $content['cart']);

        if (!$cart instanceof Cart) {
            throw new CartDeserializeFailedException();
        }

        $cart->setToken($token);
        $cart->setRuleIds(json_decode((string) $content['rule_ids'], true) ?? []);

        return $cart;
    }

    /**
     * @throws InvalidUuidException
     */
    public function save(Cart $cart, SalesChannelContext $context): void
    {
        $shouldPersist = $cart->getLineItems()->count() > 0
            || $cart->getAffiliateCode() !== null
            || $cart->getCampaignCode() !== null
            || $cart->getCustomerComment() !== null
            || $cart->getExtension(DeliveryProcessor::MANUAL_SHIPPING_COSTS) instanceof CalculatedPrice;

        $event = new CartVerifyPersistEvent($context, $cart, $shouldPersist);

        $this->eventDispatcher->dispatch($event);

        $this->connection->beginTransaction();
        if (!$event->shouldBePersisted()) {
            $this->delete($cart->getToken(), $context);
            $this->connection->commit();

            return;
        }

        $sql = <<<'SQL'
            INSERT INTO `cart` (`token`, `name`, `currency_id`, `shipping_method_id`, `payment_method_id`, `country_id`, `sales_channel_id`, `customer_id`, `price`, `line_item_count`, `cart`, `rule_ids`, `created_at`)
            VALUES (:token, :name, :currency_id, :shipping_method_id, :payment_method_id, :country_id, :sales_channel_id, :customer_id, :price, :line_item_count, :cart, :rule_ids, :now)
            ON DUPLICATE KEY UPDATE `name` = :name,`currency_id` = :currency_id, `shipping_method_id` = :shipping_method_id, `payment_method_id` = :payment_method_id, `country_id` = :country_id, `sales_channel_id` = :sales_channel_id, `customer_id` = :customer_id,`price` = :price, `line_item_count` = :line_item_count, `cart` = :cart, `rule_ids` = :rule_ids, `updated_at` = :now
            ;
        SQL;

        $customerId = $context->getCustomer() ? Uuid::fromHexToBytes($context->getCustomer()->getId()) : null;

        $data = [
            'token' => $cart->getToken(),
            'name' => $cart->getName(),
            'currency_id' => Uuid::fromHexToBytes($context->getCurrency()->getId()),
            'shipping_method_id' => Uuid::fromHexToBytes($context->getShippingMethod()->getId()),
            'payment_method_id' => Uuid::fromHexToBytes($context->getPaymentMethod()->getId()),
            'country_id' => Uuid::fromHexToBytes($context->getShippingLocation()->getCountry()->getId()),
            'sales_channel_id' => Uuid::fromHexToBytes($context->getSalesChannel()->getId()),
            'customer_id' => $customerId,
            'price' => $cart->getPrice()->getTotalPrice(),
            'line_item_count' => $cart->getLineItems()->count(),
            'cart' => $this->serializeCart($cart),
            'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'rule_ids' => json_encode($context->getRuleIds()),
        ];

        $query = new RetryableQuery($this->connection, $this->connection->prepare($sql));
        $query->execute($data);
        $this->connection->commit();

        $this->eventDispatcher->dispatch(new CartSavedEvent($context, $cart));
    }

    public function delete(string $token, SalesChannelContext $context): void
    {
        $query = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('DELETE FROM `cart` WHERE `token` = :token')
        );
        $query->execute(['token' => $token]);
    }

    private function serializeCart(Cart $cart): string
    {
        $errors = $cart->getErrors();
        $data = $cart->getData();

        $cart->setErrors(new ErrorCollection());
        $cart->setData(null);

        $serializedCart = serialize($cart);

        $cart->setErrors($errors);
        $cart->setData($data);

        return $serializedCart;
    }
}
