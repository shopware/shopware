<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Exception\CartTokenNotFoundException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;

/**
 * @internal
 */
#[Group('redis')]
class RedisCartPersisterTest extends TestCase
{
    private RedisCartPersister $persister;

    /**
     * @var \Redis
     */
    private $redis;

    protected function setUp(): void
    {
        parent::setUp();

        $redisUrl = (string) EnvironmentHelper::getVariable('REDIS_URL');

        if ($redisUrl === '') {
            static::markTestSkipped('Redis is not available');
        }

        $factory = new RedisConnectionFactory();

        $client = $factory->create($redisUrl);
        static::assertInstanceOf(\Redis::class, $client);
        $this->redis = $client;
        $this->persister = new RedisCartPersister($this->redis, new CollectingEventDispatcher(), $this->createMock(CartSerializationCleaner::class), false, 30);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis->flushAll();
    }

    public function testPersisting(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $context = $this->createMock(SalesChannelContext::class);

        $this->persister->save($cart, $context);

        $loaded = $this->persister->load($token, $context);

        static::assertEquals($cart->getToken(), $loaded->getToken());
        static::assertEquals($cart->getLineItems(), $loaded->getLineItems());

        $cart->getLineItems()->clear();

        $this->persister->save($cart, $context);

        static::expectException(CartTokenNotFoundException::class);
        $this->persister->load($token, $context);
    }

    public function testDelete(): void
    {
        $token = Uuid::randomHex();
        $cart = new Cart($token);
        $cart->add(new LineItem('test', 'test'));

        $context = $this->createMock(SalesChannelContext::class);

        $this->persister->save($cart, $context);

        $this->persister->load($token, $context);

        $this->persister->delete($token, $context);

        static::expectException(CartTokenNotFoundException::class);
        $this->persister->load($token, $context);
    }
}
