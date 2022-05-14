<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
class RedisCartPersisterTest extends TestCase
{
    public function testSave(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart('test', $token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = $this->createMock(EventDispatcher::class);

        $content = CacheValueCompressor::compress(['cart' => $cart, 'rule_ids' => []]);

        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $redis = $this->createMock(\Redis::class);
        $redis->expects(static::once())
            ->method('set')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $token));

        $context = $this->createMock(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, true);

        $persister->save($cart, $context);
    }

    public function testLoad(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart('test', $token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = $this->createMock(EventDispatcher::class);

        $content = CacheValueCompressor::compress(['cart' => $cart, 'rule_ids' => []]);

        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(static::once())
            ->method('get')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $token))
            ->willReturn(\serialize(['compressed' => true, 'content' => $content]));

        $context = $this->createMock(SalesChannelContext::class);

        $loadedCart = (new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, true))->load($token, $context);

        static::assertEquals($cart, $loadedCart);
    }

    public function testDelete(): void
    {
        $token = Uuid::randomHex();

        $dispatcher = $this->createMock(EventDispatcher::class);
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(static::once())
            ->method('del')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $token));

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, true);

        $context = $this->createMock(SalesChannelContext::class);

        $persister->delete($token, $context);
    }

    public function testLoadWithDifferentCompression(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart('test', $token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = $this->createMock(EventDispatcher::class);

        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);

        $compressedRedis = $this->createMock(\Redis::class);
        $compressedRedis->expects(static::once())
            ->method('set')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $token));

        $context = $this->createMock(SalesChannelContext::class);

        $persister = new RedisCartPersister($compressedRedis, $dispatcher, $cartSerializationCleaner, true);

        $persister->save($cart, $context);

        $dispatcher = $this->createMock(EventDispatcher::class);

        $content = CacheValueCompressor::compress(['cart' => $cart, 'rule_ids' => []]);

        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);

        $uncompressedRedis = $this->createMock(\Redis::class);
        $uncompressedRedis->expects(static::once())
            ->method('get')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $token))
            ->willReturn(\serialize(['compressed' => true, 'content' => $content]));

        $context = $this->createMock(SalesChannelContext::class);

        $loadedCart = (new RedisCartPersister($uncompressedRedis, $dispatcher, $cartSerializationCleaner, false))->load($token, $context);

        static::assertEquals($cart, $loadedCart);
    }
}
