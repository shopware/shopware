<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Event\CartLoadedEvent;
use Shopware\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
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
        private readonly CartCompressor $compressor
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

        try {
            $cart = $this->compressor->unserialize($content['payload'], (int) $content['compressed']);
        } catch (\Exception) {
            // When we can't decode it, we have to delete it
            throw CartException::tokenNotFound($token);
        }

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
            INSERT INTO `cart` (`token`, `payload`, `rule_ids`, `compressed`, `created_at`)
            VALUES (:token, :payload, :rule_ids, :compressed, :now)
            ON DUPLICATE KEY UPDATE `payload` = :payload, `compressed` = :compressed, `rule_ids` = :rule_ids, `created_at` = :now;
        SQL;

        [$compressed, $serializeCart] = $this->serializeCart($cart);

        $data = [
            'token' => $cart->getToken(),
            'payload' => $serializeCart,
            'rule_ids' => json_encode($context->getRuleIds(), \JSON_THROW_ON_ERROR),
            'now' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'compressed' => $compressed,
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

    public function prune(int $days): void
    {
        $time = new \DateTime();
        $time->modify(\sprintf('-%d day', $days));

        $stmt = $this->connection->prepare(<<<'SQL'
            DELETE FROM cart
                WHERE created_at <= :timestamp
                LIMIT 1000;
        SQL);

        $timestamp = $time->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        do {
            $result = $stmt->executeStatement(['timestamp' => $timestamp]);
        } while ($result > 0);
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function serializeCart(Cart $cart): array
    {
        $errors = $cart->getErrors();
        $data = $cart->getData();

        $cart->setErrors(new ErrorCollection());
        $cart->setData(null);

        $this->cartSerializationCleaner->cleanupCart($cart);

        $serialized = $this->compressor->serialize($cart);

        $cart->setErrors($errors);
        $cart->setData($data);

        return $serialized;
    }
}
