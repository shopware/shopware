<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Traits\RedisClusterProxy;
use Symfony\Component\Cache\Traits\RedisProxy;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class RedisCartPersister extends AbstractCartPersister
{
    final public const PREFIX = 'cart-persister-';

    /**
     * @internal
     */
    public function __construct(
        private readonly \Redis|\RedisArray|\RedisCluster|RedisClusterProxy|RedisProxy $redis,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CartSerializationCleaner $cartSerializationCleaner,
        private readonly bool $compress,
        private readonly int $expireDays
    ) {
    }

    public function getDecorated(): AbstractCartPersister
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $token, SalesChannelContext $context): Cart
    {
        /** @var string|bool|array<mixed> $value */
        $value = $this->redis->get(self::PREFIX . $token);

        if ($value === false || !\is_string($value)) {
            throw CartException::tokenNotFound($token);
        }

        try {
            $value = \unserialize($value);
        } catch (\Exception) {
            throw CartException::tokenNotFound($token);
        }

        if (!isset($value['compressed'])) {
            throw CartException::tokenNotFound($token);
        }

        $content = $value['compressed'] ? CacheValueCompressor::uncompress($value['content']) : \unserialize((string) $value['content']);

        if (!\is_array($content)) {
            throw CartException::tokenNotFound($token);
        }

        $cart = $content['cart'];

        if (!$cart instanceof Cart) {
            throw CartException::deserializeFailed();
        }

        $cart->setToken($token);
        $cart->setRuleIds($content['rule_ids']);

        return $cart;
    }

    public function save(Cart $cart, SalesChannelContext $context): void
    {
        $shouldPersist = $this->shouldPersist($cart);

        $this->eventDispatcher->dispatch(new CartSavedEvent($context, $cart));

        $event = new CartVerifyPersistEvent($context, $cart, $shouldPersist);

        $this->eventDispatcher->dispatch($event);
        if (!$event->shouldBePersisted()) {
            $this->delete($cart->getToken(), $context);

            return;
        }

        $content = $this->serializeCart($cart, $context);

        $this->redis->set(self::PREFIX . $cart->getToken(), $content, ['EX' => $this->expireDays * 86400]);
    }

    public function delete(string $token, SalesChannelContext $context): void
    {
        $this->redis->del(self::PREFIX . $token);
    }

    public function replace(string $oldToken, string $newToken, SalesChannelContext $context): void
    {
        try {
            $cart = $this->load($oldToken, $context);
        } catch (CartTokenNotFoundException) {
            return;
        }

        $cart->setToken($newToken);
        $this->save($cart, $context);
        $cart->setToken($oldToken);

        $this->delete($oldToken, $context);
    }

    private function serializeCart(Cart $cart, SalesChannelContext $context): string
    {
        $errors = $cart->getErrors();
        $data = $cart->getData();

        $cart->setErrors(new ErrorCollection());
        $cart->setData(null);

        $this->cartSerializationCleaner->cleanupCart($cart);

        $content = ['cart' => $cart, 'rule_ids' => $context->getRuleIds()];

        $content = $this->compress ? CacheValueCompressor::compress($content) : \serialize($content);

        $cart->setErrors($errors);
        $cart->setData($data);

        return \serialize([
            'compressed' => $this->compress,
            'content' => $content,
            // used for migration
            'token' => $cart->getToken(),
            'customer_id' => $context->getCustomerId(),
            'rule_ids' => $context->getRuleIds(),
            'currency_id' => $context->getCurrency()->getId(),
            'shipping_method_id' => $context->getShippingMethod()->getId(),
            'payment_method_id' => $context->getPaymentMethod()->getId(),
            'country_id' => $context->getShippingLocation()->getCountry()->getId(),
            'sales_channel_id' => $context->getSalesChannel()->getId(),
            'price' => $cart->getPrice()->getTotalPrice(),
            'line_item_count' => $cart->getLineItems()->count(),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
