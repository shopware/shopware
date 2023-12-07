<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Event\CartLoadedEvent;
use Shopware\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class CartPersister extends AbstractCartPersister
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartSerializationCleaner $cartSerializationCleaner,
        private readonly bool $compress
    ) {
    }

    public function getDecorated(): AbstractCartPersister
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $token, SalesChannelContext $context): Cart
    {
        $content = $this->connection->fetchAssociative(
            '#cart-persister::load
            SELECT `cart`.`payload`, `cart`.`rule_ids`, `cart`.`compressed` FROM cart WHERE `token` = :token',
            ['token' => $token]
        );

        if (!\is_array($content)) {
            throw CartException::tokenNotFound($token);
        }

        $cart = $content['compressed'] ? CacheValueCompressor::uncompress($content['payload']) : unserialize((string) $content['payload']);

        if (!$cart instanceof Cart) {
            throw CartException::deserializeFailed();
        }

        $cart->setToken($token);
        $cart->setRuleIds(json_decode((string) $content['rule_ids'], true, 512, \JSON_THROW_ON_ERROR) ?? []);

        $this->eventDispatcher->dispatch(new CartLoadedEvent($cart, $context));

        return $cart;
    }

    /**
     * @throws InvalidUuidException
     */
    public function save(Cart $cart, SalesChannelContext $context): void
    {
        if ($cart->getBehavior()?->isRecalculation()) {
            return;
        }

        $shouldPersist = $this->shouldPersist($cart);

        $event = new CartVerifyPersistEvent($context, $cart, $shouldPersist);
        $this->eventDispatcher->dispatch($event);

        if (!$event->shouldBePersisted()) {
            $this->delete($cart->getToken(), $context);

            return;
        }

        $sql = <<<'SQL'
            INSERT INTO `cart` (`token`, `currency_id`, `shipping_method_id`, `payment_method_id`, `country_id`, `sales_channel_id`, `customer_id`, `price`, `line_item_count`, `payload`, `rule_ids`, `compressed`, `created_at`)
            VALUES (:token, :currency_id, :shipping_method_id, :payment_method_id, :country_id, :sales_channel_id, :customer_id, :price, :line_item_count, :payload, :rule_ids, :compressed, :now)
            ON DUPLICATE KEY UPDATE `currency_id` = :currency_id, `shipping_method_id` = :shipping_method_id, `payment_method_id` = :payment_method_id, `country_id` = :country_id, `sales_channel_id` = :sales_channel_id, `customer_id` = :customer_id,`price` = :price, `line_item_count` = :line_item_count, `payload` = :payload, `compressed` = :compressed, `rule_ids` = :rule_ids, `updated_at` = :now;
        SQL;

        $customerId = $context->getCustomer() ? Uuid::fromHexToBytes($context->getCustomer()->getId()) : null;

        $data = [
            'token' => $cart->getToken(),
            'currency_id' => Uuid::fromHexToBytes($context->getCurrency()->getId()),
            'shipping_method_id' => Uuid::fromHexToBytes($context->getShippingMethod()->getId()),
            'payment_method_id' => Uuid::fromHexToBytes($context->getPaymentMethod()->getId()),
            'country_id' => Uuid::fromHexToBytes($context->getShippingLocation()->getCountry()->getId()),
            'sales_channel_id' => Uuid::fromHexToBytes($context->getSalesChannel()->getId()),
            'customer_id' => $customerId,
            'price' => $cart->getPrice()->getTotalPrice(),
            'line_item_count' => $cart->getLineItems()->count(),
            'payload' => $this->serializeCart($cart),
            'rule_ids' => json_encode($context->getRuleIds(), \JSON_THROW_ON_ERROR),
            'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'compressed' => (int) $this->compress,
        ];

        $query = new RetryableQuery($this->connection, $this->connection->prepare($sql));
        $query->execute($data);

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

    public function replace(string $oldToken, string $newToken, SalesChannelContext $context): void
    {
        $this->connection->executeStatement(
            'UPDATE `cart` SET `token` = :newToken WHERE `token` = :oldToken',
            ['newToken' => $newToken, 'oldToken' => $oldToken]
        );
    }

    private function serializeCart(Cart $cart): string
    {
        $errors = $cart->getErrors();
        $data = $cart->getData();

        $cart->setErrors(new ErrorCollection());
        $cart->setData(null);

        $this->cartSerializationCleaner->cleanupCart($cart);

        $serialized = $this->compress ? CacheValueCompressor::compress($cart) : serialize($cart);

        $cart->setErrors($errors);
        $cart->setData($data);

        return $serialized;
    }
}
