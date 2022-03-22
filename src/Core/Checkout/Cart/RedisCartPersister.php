<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Event\CartSavedEvent;
use Shopware\Core\Checkout\Cart\Event\CartVerifyPersistEvent;
use Shopware\Core\Checkout\Cart\Exception\CartDeserializeFailedException;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class RedisCartPersister extends AbstractCartPersister
{
    /**
     * @var \Redis|\RedisCluster
     */
    private $redis;

    private EventDispatcherInterface $eventDispatcher;

    private bool $compress;

    /**
     * @param \Redis|\RedisCluster $redis
     */
    public function __construct($redis, EventDispatcherInterface $eventDispatcher, bool $compress)
    {
        $this->redis = $redis;
        $this->eventDispatcher = $eventDispatcher;
        $this->compress = $compress;
    }

    public function getDecorated(): AbstractCartPersister
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $token, SalesChannelContext $context): Cart
    {
        /** @var string|bool|array $value */
        $value = $this->redis->get('cart-' . $token);

        if ($value === false || !\is_array($value)) {
            throw new CartTokenNotFoundException($token);
        }

        $content = $value['compressed'] ? CacheValueCompressor::uncompress($value['content']) : \unserialize((string) $value['content']);

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
        $shouldPersist = $this->shouldPersist($cart);

        $this->eventDispatcher->dispatch(new CartSavedEvent($context, $cart));

        $event = new CartVerifyPersistEvent($context, $cart, $shouldPersist);

        $this->eventDispatcher->dispatch($event);
        if (!$event->shouldBePersisted()) {
            $this->delete('cart-' . $cart->getToken(), $context);

            return;
        }

        $content = $this->serializeCart($cart, $context);

        $this->redis->set('cart-' . $cart->getToken(), $content);
    }

    public function delete(string $token, SalesChannelContext $context): void
    {
        $this->redis->del('cart-' . $token);
    }

    public function replace(string $oldToken, string $newToken, SalesChannelContext $context): void
    {
        try {
            $cart = $this->load($oldToken, $context);
        } catch (CartTokenNotFoundException $e) {
            return;
        }

        $cart->setToken($newToken);
        $this->save($cart, $context);
        $cart->setToken($oldToken);

        $this->delete($oldToken, $context);
    }

    private function serializeCart(Cart $cart, SalesChannelContext $context): array
    {
        $errors = $cart->getErrors();
        $data = $cart->getData();

        $cart->setErrors(new ErrorCollection());
        $cart->setData(null);

        $content = ['cart' => $cart, 'rule_ids' => $context->getRuleIds()];

        $content = $this->compress ? CacheValueCompressor::compress($content) : \serialize($content);

        $cart->setErrors($errors);
        $cart->setData($data);

        return ['compressed' => $this->compress, 'content' => $content];
    }
}
