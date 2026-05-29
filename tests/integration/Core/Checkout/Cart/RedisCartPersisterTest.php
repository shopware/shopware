<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCompressor;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[Group('redis')]
#[Package('checkout')]
class RedisCartPersisterTest extends TestCase
{
    private RedisCartPersister $persister;

    private \Redis $redis;

    protected function setUp(): void
    {
        parent::setUp();

        $redisUrl = (string) EnvironmentHelper::getVariable('REDIS_URL');

        if ($redisUrl === '') {
            static::markTestSkipped('Redis is not available');
        }

        $client = (new RedisConnectionFactory())->create($redisUrl);
        static::assertInstanceOf(\Redis::class, $client);
        $this->redis = $client;
        $this->persister = new RedisCartPersister($this->redis, new CollectingEventDispatcher(), $this->createMock(CartSerializationCleaner::class), new CartCompressor(false, 'gzip'), 30);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clear the Redis storage only if it was set up and not skipped
        if (isset($this->redis)) {
            $this->redis->flushAll();
        }
    }

    public function testPersisting(): void
    {
        $cartToken = CartService::getNewToken();
        $cart = new Cart($cartToken);
        $cart->add(new LineItem('test', 'test'));

        $context = $this->createMock(SalesChannelContext::class);

        $this->persister->save($cart, $context);

        $loaded = $this->persister->load($cartToken, $context);

        static::assertSame($cart->getToken(), $loaded->getToken());
        static::assertEquals($cart->getLineItems(), $loaded->getLineItems());

        $cart->getLineItems()->clear();

        $this->persister->save($cart, $context);

        $this->expectException(CartTokenNotFoundException::class);
        $this->persister->load($cartToken, $context);
    }

    public function testDelete(): void
    {
        $cartToken = CartService::getNewToken();
        $cart = new Cart($cartToken);
        $cart->add(new LineItem('test', 'test'));

        $context = $this->createMock(SalesChannelContext::class);

        $this->persister->save($cart, $context);

        $this->persister->load($cartToken, $context);

        $this->persister->delete($cartToken, $context);

        $this->expectException(CartTokenNotFoundException::class);
        $this->persister->load($cartToken, $context);
    }

    public function testSavingExistingCartDoesNotRecreateDeletedCart(): void
    {
        $cartToken = CartService::getNewToken();
        $cart = new Cart($cartToken);
        $cart->add(new LineItem('test', 'test'));

        $context = $this->createMock(SalesChannelContext::class);

        $this->persister->save($cart, $context);
        $this->persister->delete($cartToken, $context);
        $this->persister->save($cart, $context);

        static::assertSame(0, $this->redis->exists(RedisCartPersister::PREFIX . $cartToken));
    }

    public function testLoadGzipCompressedCart(): void
    {
        $cartToken = CartService::getNewToken();

        $cart = new Cart($cartToken);
        $compressed = ['content' => gzcompress(serialize(['cart' => $cart, 'rule_ids' => []]), 9), 'compressed' => 1];

        $this->redis->set(RedisCartPersister::PREFIX . $cartToken, serialize($compressed));

        $loaded = $this->persister->load($cartToken, $this->createMock(SalesChannelContext::class));

        $cart->setPersisted(true);

        static::assertEquals($cart, $loaded);
    }

    public function testLoadZstdCompressedCart(): void
    {
        if (!\function_exists('zstd_compress')) {
            static::markTestSkipped('zstd extension is not installed');
        }

        $cartToken = CartService::getNewToken();

        $cart = new Cart($cartToken);
        $compressed = ['content' => \zstd_compress(serialize(['cart' => $cart, 'rule_ids' => []]), 9), 'compressed' => 2];

        $this->redis->set(RedisCartPersister::PREFIX . $cartToken, serialize($compressed));

        $loaded = $this->persister->load($cartToken, $this->createMock(SalesChannelContext::class));

        $cart->setPersisted(true);

        static::assertEquals($cart, $loaded);
    }
}
