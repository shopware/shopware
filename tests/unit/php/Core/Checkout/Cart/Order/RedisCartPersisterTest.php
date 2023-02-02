<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Cart\RedisCartPersister
 */
class RedisCartPersisterTest extends TestCase
{
    public function testDecorated(): void
    {
        $redis = $this->createMock(\Redis::class);
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $persister = new RedisCartPersister($redis, $eventDispatcher, $cartSerializationCleaner, true, 90);
        $this->expectException(DecorationPatternException::class);
        $persister->getDecorated();
    }

    public function testSave(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = $this->createMock(EventDispatcher::class);

        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $redis = $this->createMock(\Redis::class);
        $redis->expects(static::once())
            ->method('set')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $token));

        $context = $this->createMock(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, true, 90);

        $persister->save($cart, $context);
    }

    public function testEmptyCartGetsDeleted(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);

        $dispatcher = $this->createMock(EventDispatcher::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(static::once())
            ->method('del')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $token));

        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $context = $this->createMock(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, false, 90);
        $persister->save($cart, $context);
    }

    public function testLoad(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
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

        $loadedCart = (new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, true, 90))->load($token, $context);

        static::assertEquals($cart, $loadedCart);
    }

    /**
     * @dataProvider dataProviderInvalidData
     *
     * @param class-string<\Throwable> $exceptionClass
     */
    public function testLoadingInvalidCart(mixed $data, string $exceptionClass): void
    {
        $token = Uuid::randomHex();
        $dispatcher = $this->createMock(EventDispatcher::class);
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(static::once())
            ->method('get')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $token))
            ->willReturn($data);

        $context = $this->createMock(SalesChannelContext::class);
        $this->expectException($exceptionClass);
        (new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, true, 90))->load($token, $context);
    }

    /**
     * @return iterable<string, array{mixed, class-string<CartException>}>
     */
    public function dataProviderInvalidData(): iterable
    {
        yield 'not existing' => [null, CartTokenNotFoundException::class];
        yield 'invalid serialize' => ['abc', CartTokenNotFoundException::class];
        yield 'not cart serialize' => [\serialize(new \ArrayObject()), CartTokenNotFoundException::class];
        yield 'valid outer object, but invalid content' => [\serialize(['compressed' => false, 'content' => \serialize(new \ArrayObject())]), CartTokenNotFoundException::class];
        yield 'valid outer object, but not cart' => [serialize(['compressed' => false, 'content' => serialize(['cart' => ''])]), CartException::class];
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

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, true, 90);

        $context = $this->createMock(SalesChannelContext::class);

        $persister->delete($token, $context);
    }

    public function testLoadWithDifferentCompression(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = $this->createMock(EventDispatcher::class);

        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);

        $compressedRedis = $this->createMock(\Redis::class);
        $compressedRedis->expects(static::once())
            ->method('set')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $token));

        $context = $this->createMock(SalesChannelContext::class);

        $persister = new RedisCartPersister($compressedRedis, $dispatcher, $cartSerializationCleaner, true, 90);

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

        $loadedCart = (new RedisCartPersister($uncompressedRedis, $dispatcher, $cartSerializationCleaner, false, 90))->load($token, $context);

        static::assertEquals($cart, $loadedCart);
    }

    public function testReplace(): void
    {
        $oldToken = Uuid::randomHex();
        $newToken = Uuid::randomHex();
        $cart = new Cart($oldToken);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = $this->createMock(EventDispatcher::class);

        $content = CacheValueCompressor::compress(['cart' => $cart, 'rule_ids' => []]);

        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(static::once())
            ->method('get')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $oldToken))
            ->willReturn(\serialize(['compressed' => true, 'content' => $content]));

        $redis->expects(static::once())
            ->method('del')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $oldToken));

        $redis->expects(static::once())
            ->method('set')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $newToken));

        $context = $this->createMock(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, true, 90);

        $persister->replace($oldToken, $newToken, $context);
    }

    public function testInvalidCartReplace(): void
    {
        $token = Uuid::randomHex();

        $dispatcher = $this->createMock(EventDispatcher::class);
        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);

        $redis = $this->createMock(\Redis::class);
        $redis->expects(static::once())
            ->method('get')
            ->with(static::equalTo(RedisCartPersister::PREFIX . $token))
            ->willReturn(null);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, true, 90);

        $context = $this->createMock(SalesChannelContext::class);

        $persister->replace($token, Uuid::randomHex(), $context);
    }

    public function testExpiration(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = $this->createMock(EventDispatcher::class);

        $cartSerializationCleaner = $this->createMock(CartSerializationCleaner::class);
        $redis = $this->createMock(\Redis::class);
        $redis->expects(static::once())
              ->method('set')
              ->with(RedisCartPersister::PREFIX . $token, static::anything(), ['EX' => 90 * 86400]);

        $context = $this->createMock(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, true, 90);

        $persister->save($cart, $context);
    }
}
