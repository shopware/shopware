<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache\InvalidatorStorage;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
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

    private \Redis $redis;

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
        $this->storage = new RedisInvalidatorStorage($this->redis, new NullLogger());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clear the Redis storage only if it was set up and not skipped
        if (isset($this->redis)) {
            $this->redis->del('invalidation');
        }
    }

    public function testLoadWhenEmpty(): void
    {
        static::assertSame([], $this->storage->loadAndDelete());

        $this->storage->store(['test']);

        static::assertSame(['test'], $this->storage->loadAndDelete());
        static::assertSame([], $this->storage->loadAndDelete());
    }

    public function testStoreAndLoadLargeBatch(): void
    {
        $tags = $this->generateTags(50000);

        $this->storage->store($tags);

        static::assertSame(50000, $this->redis->scard('invalidation'));

        $loadedTags = $this->storage->loadAndDelete();

        static::assertCount(50000, $loadedTags);
        static::assertSame(0, $this->redis->scard('invalidation'));
    }

    public function testMultipleStoreAndLoadCycles(): void
    {
        // First cycle
        $tags1 = $this->generateTags(1000);
        $this->storage->store($tags1);
        $loaded1 = $this->storage->loadAndDelete();
        static::assertCount(1000, $loaded1);

        // Second cycle
        $tags2 = $this->generateTags(2000);
        $this->storage->store($tags2);
        $loaded2 = $this->storage->loadAndDelete();
        static::assertCount(2000, $loaded2);

        // Verify empty
        static::assertSame([], $this->storage->loadAndDelete());
    }

    /**
     * @return list<string>
     */
    private function generateTags(int $count): array
    {
        $tags = [];
        for ($i = 0; $i < $count; ++$i) {
            $tags[] = 'test-tag-' . bin2hex(random_bytes(8)) . '-' . $i;
        }

        return $tags;
    }
}
