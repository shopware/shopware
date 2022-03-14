<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Shopware\Core\Checkout\Cart\Exception\CartDeserializeFailedException;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RedisCartPersister implements CartPersisterInterface
{
    /**
     * @var \Redis|\RedisCluster
     */
    private $redis;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * @param \Redis|\RedisCluster $redis
     */
    public function __construct($redis, EventDispatcherInterface $eventDispatcher)
    {
        $this->redis = $redis;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function load(string $token, SalesChannelContext $context): Cart
    {
        $content = $this->redis->get($token);

        $content = CacheValueCompressor::uncompress($content);

        if (!\is_array($content)) {
            throw new CartTokenNotFoundException($token);
        }

        $cart = $content['cart'];

        if (!$cart instanceof Cart) {
            throw new CartDeserializeFailedException();
        }

        $cart->setToken($token);
        $cart->setRuleIds($content['rule_ids']);

        return $cart;
    }

    public function save(Cart $cart, SalesChannelContext $context): void
    {
        $shouldPersist = CartPersister::shouldPersist($cart);

        $this->eventDispatcher->dispatch(new CartSavedEvent($context, $cart));

        $event = new CartVerifyPersistEvent($context, $cart, $shouldPersist);

        $this->eventDispatcher->dispatch($event);
        if (!$event->shouldBePersisted()) {
            $this->delete($cart->getToken(), $context);

            return;
        }

        $content = $this->serializeCart($cart, $context);

        $this->redis->set($cart->getToken(), $content);
    }

    public function delete(string $token, SalesChannelContext $context): void
    {
        $this->redis->del($token);
    }

    private function serializeCart(Cart $cart, SalesChannelContext $context): string
    {
        $errors = $cart->getErrors();
        $data = $cart->getData();

        $cart->setErrors(new ErrorCollection());
        $cart->setData(null);

        $content = CacheValueCompressor::compress([
            'cart' => $cart,
            'rule_ids' => $context->getRuleIds(),
        ]);

        $cart->setErrors($errors);
        $cart->setData($data);

        return $content;
    }
}
