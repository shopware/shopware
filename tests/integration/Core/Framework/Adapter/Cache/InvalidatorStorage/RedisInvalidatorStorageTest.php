<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache\InvalidatorStorage;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\InvalidatorStorage\RedisInvalidatorStorage;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;

/**
 * @internal
 */
#[Group('redis')]
class RedisInvalidatorStorageTest extends TestCase
{
    private RedisInvalidatorStorage $storage;

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
        $this->storage = new RedisInvalidatorStorage($this->redis);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis->flushAll();
    }

    public function testLoadWhenEmpty(): void
    {
        static::assertSame([], $this->storage->loadAndDelete());

        $this->storage->store(['test']);

        static::assertSame(['test'], $this->storage->loadAndDelete());
        static::assertSame([], $this->storage->loadAndDelete());
    }
}
