<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartCompressor;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Checkout\CheckoutPermissions;
use Shopware\Core\Content\Product\Cart\ProductNotFoundError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\Redis\RedisStub;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(RedisCartPersister::class)]
class RedisCartPersisterTest extends TestCase
{
    public function testDecorated(): void
    {
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);
        $persister = new RedisCartPersister(new RedisStub(), new CollectingEventDispatcher(), $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);
        $this->expectException(DecorationPatternException::class);
        $persister->getDecorated();
    }

    public function testSave(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = static::createStub(EventDispatcher::class);

        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);

        $redis = new RedisStub();

        $context = static::createStub(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);

        $persister->save($cart, $context);

        static::assertTrue($redis->exists(RedisCartPersister::PREFIX . $token));
    }

    public function testSaveWithSkipCartPersistencePermissionLeavesStorageUntouched(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = static::createStub(EventDispatcher::class);
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);
        $redis = new RedisStub();
        $context = static::createStub(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);
        $persister->save($cart, $context);

        static::assertTrue($redis->exists(RedisCartPersister::PREFIX . $token));

        // an emptied cart would fall into the delete branch without the permission
        $emptiedCart = new Cart($token);
        $emptiedCart->setBehavior(new CartBehavior([CheckoutPermissions::SKIP_CART_PERSISTENCE => true]));

        $persister->save($emptiedCart, $context);

        static::assertTrue($redis->exists(RedisCartPersister::PREFIX . $token));
    }

    public function testEmptyCartGetsDeleted(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);

        $dispatcher = static::createStub(EventDispatcher::class);

        $redis = new RedisStub();

        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);
        $context = static::createStub(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);
        $persister->save($cart, $context);

        static::assertFalse($redis->exists(RedisCartPersister::PREFIX . $token));
    }

    public function testSavingExistingCartDoesNotCreateMissingCart(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = static::createStub(EventDispatcher::class);
        $redis = new RedisStub();
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);
        $context = static::createStub(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);
        $persister->save($cart, $context);
        $persister->delete($token, $context);
        $persister->save($cart, $context);

        static::assertFalse($redis->exists(RedisCartPersister::PREFIX . $token));
    }

    public function testEmptiedCartCanBePersistedAgain(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add((new LineItem('test', 'test'))->setRemovable(true));

        $dispatcher = static::createStub(EventDispatcher::class);
        $redis = new RedisStub();
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);
        $context = static::createStub(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);
        $persister->save($cart, $context);

        $cart->remove('test');
        $persister->save($cart, $context);

        static::assertFalse($redis->exists(RedisCartPersister::PREFIX . $token));
        static::assertFalse($cart->isPersisted());

        $cart->add(new LineItem('test', 'test'));
        $persister->save($cart, $context);

        static::assertTrue($redis->exists(RedisCartPersister::PREFIX . $token));
    }

    public function testLoad(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = static::createStub(EventDispatcher::class);

        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);

        $redis = new RedisStub();

        $context = static::createStub(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);

        $persister->save($cart, $context);

        $loadedCart = $persister->load($token, $context);

        $cart->setData(null);
        $cart->setPersisted(true);

        static::assertEquals($cart, $loadedCart);
    }

    /**
     * @param class-string<\Throwable> $exceptionClass
     */
    #[DataProvider('dataProviderInvalidData')]
    public function testLoadingInvalidCart(mixed $data, string $exceptionClass): void
    {
        $token = Uuid::randomHex();
        $dispatcher = static::createStub(EventDispatcher::class);
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);

        $redis = new RedisStub();
        $redis->set(RedisCartPersister::PREFIX . $token, $data);

        $context = static::createStub(SalesChannelContext::class);
        $this->expectException($exceptionClass);
        (new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90))->load($token, $context);
    }

    /**
     * @return iterable<string, array{mixed, class-string<CartException>}>
     */
    public static function dataProviderInvalidData(): iterable
    {
        yield 'not existing' => [null, CartTokenNotFoundException::class];
        yield 'invalid serialize' => ['O:32:"Shopware\Core\Checkout\Cart\Cart":1:{s:5:"price";N;}', CartTokenNotFoundException::class];
        yield 'not cart serialize' => [\serialize(new \ArrayObject()), CartTokenNotFoundException::class];
        yield 'valid outer object, but invalid content' => [\serialize(['compressed' => false, 'content' => \serialize(new \ArrayObject())]), CartTokenNotFoundException::class];
        yield 'valid outer object, but content with type error' => [\serialize(['compressed' => false, 'content' => []]), CartTokenNotFoundException::class];
        yield 'valid outer object, but not cart' => [serialize(['compressed' => false, 'content' => serialize(['cart' => ''])]), CartException::class];
    }

    public function testDelete(): void
    {
        $token = Uuid::randomHex();

        $dispatcher = static::createStub(EventDispatcher::class);
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);

        $redis = new RedisStub();
        $redis->set(RedisCartPersister::PREFIX . $token, 'test');

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);

        $context = static::createStub(SalesChannelContext::class);

        $persister->delete($token, $context);

        static::assertFalse($redis->exists(RedisCartPersister::PREFIX . $token));
    }

    public function testExistsReflectsStoredCart(): void
    {
        $token = Uuid::randomHex();

        $dispatcher = static::createStub(EventDispatcher::class);
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);

        $redis = new RedisStub();

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);

        $context = static::createStub(SalesChannelContext::class);

        static::assertFalse($persister->exists($token, $context));

        $redis->set(RedisCartPersister::PREFIX . $token, 'test');

        static::assertTrue($persister->exists($token, $context));

        $persister->delete($token, $context);

        static::assertFalse($persister->exists($token, $context));
    }

    public function testLoadWithDifferentCompression(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = static::createStub(EventDispatcher::class);

        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);

        $compressedRedis = new RedisStub();

        $context = static::createStub(SalesChannelContext::class);

        $compressor = new CartCompressor(false, 'gzip');
        $persister = new RedisCartPersister($compressedRedis, $dispatcher, $cartSerializationCleaner, $compressor, 90);

        $persister->save($cart, $context);

        $dispatcher = static::createStub(EventDispatcher::class);

        [$compression, $content] = $compressor->serialize(['cart' => $cart, 'rule_ids' => []]);

        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);

        $uncompressedRedis = new RedisStub();
        $uncompressedRedis->set(RedisCartPersister::PREFIX . $token, \serialize(['compressed' => $compression, 'content' => $content]));

        $context = static::createStub(SalesChannelContext::class);

        $loadedCart = (new RedisCartPersister($uncompressedRedis, $dispatcher, $cartSerializationCleaner, $compressor, 90))->load($token, $context);

        $cart->setPersisted(true);

        static::assertEquals($cart, $loadedCart);
    }

    public function testReplace(): void
    {
        $oldToken = Uuid::randomHex();
        $newToken = Uuid::randomHex();
        $cart = new Cart($oldToken);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = static::createStub(EventDispatcher::class);

        $compressor = new CartCompressor(false, 'gzip');

        [$compression, $cart] = $compressor->serialize(['cart' => $cart, 'rule_ids' => []]);

        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);

        $redis = new RedisStub();
        $redis->set(RedisCartPersister::PREFIX . $oldToken, \serialize(['compressed' => $compression, 'content' => $cart]));

        $context = static::createStub(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, $compressor, 90);

        $persister->replace($oldToken, $newToken, $context);

        static::assertFalse($redis->exists(RedisCartPersister::PREFIX . $oldToken));
        static::assertTrue($redis->exists(RedisCartPersister::PREFIX . $newToken));
    }

    public function testReplaceCopyRuleIds(): void
    {
        $oldToken = Uuid::randomHex();
        $newToken = Uuid::randomHex();
        $cart = new Cart($oldToken);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = new CollectingEventDispatcher();
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);

        $redis = new RedisStub();

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getRuleIds')->willReturn(['test']);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);

        $persister->save($cart, $context);

        $persister->replace($oldToken, $newToken, $context);

        $movedCart = $persister->load($newToken, $context);

        static::assertSame(['test'], $movedCart->getRuleIds());
    }

    public function testInvalidCartReplace(): void
    {
        $token = Uuid::randomHex();

        $dispatcher = static::createStub(EventDispatcher::class);
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);

        $redis = new RedisStub();

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);

        $context = static::createStub(SalesChannelContext::class);

        $newToken = Uuid::randomHex();
        $persister->replace($token, $newToken, $context);

        static::assertFalse($redis->exists(RedisCartPersister::PREFIX . $token));
        static::assertFalse($redis->exists(RedisCartPersister::PREFIX . $newToken));
    }

    public function testExpiration(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $dispatcher = static::createStub(EventDispatcher::class);

        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);
        $redis = new RedisStub();

        $context = static::createStub(SalesChannelContext::class);

        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);

        $persister->save($cart, $context);

        static::assertSame(90 * 86400, $redis->ttl(RedisCartPersister::PREFIX . $token));
    }

    public function testSaveCartWithoutErrorCleanup(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));
        $cart->addErrors(new ProductNotFoundError(Uuid::randomHex()));

        $context = static::createStub(SalesChannelContext::class);
        $dispatcher = static::createStub(EventDispatcher::class);
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);
        $redis = new RedisStub();
        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);

        $persister->save($cart, $context);

        $cart = $persister->load($cart->getToken(), $context);

        static::assertNotEmpty($cart->getLineItems());
        static::assertEmpty($cart->getErrors());
    }

    public function testSaveCartWithPersistCartErrorPermission(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $productId = Uuid::randomHex();
        $cart->addErrors(new ProductNotFoundError($productId));

        $cart->setBehavior(new CartBehavior([
            AbstractCartPersister::PERSIST_CART_ERROR_PERMISSION => true,
        ]));

        $context = static::createStub(SalesChannelContext::class);
        $dispatcher = static::createStub(EventDispatcher::class);
        $cartSerializationCleaner = static::createStub(CartSerializationCleaner::class);
        $redis = new RedisStub();
        $persister = new RedisCartPersister($redis, $dispatcher, $cartSerializationCleaner, new CartCompressor(false, 'gzip'), 90);

        $persister->save($cart, $context);
        $cart = $persister->load($cart->getToken(), $context);

        static::assertNotEmpty($cart->getLineItems());
        static::assertNotEmpty($cart->getErrors());

        $error = $cart->getErrors()->first();
        static::assertInstanceOf(ProductNotFoundError::class, $error);
        static::assertEquals(['id' => $productId], $error->getParameters());
    }
}
