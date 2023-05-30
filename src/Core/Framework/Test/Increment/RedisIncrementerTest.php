<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Increment;

use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Increment\RedisIncrementer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
class RedisIncrementerTest extends TestCase
{
    private ?\Redis $redis = null;

    private RedisIncrementer $incrementer;

    protected function setUp(): void
    {
        parent::setUp();

        $redisUrl = (string) EnvironmentHelper::getVariable('REDIS_URL');

        if ($redisUrl === '') {
            static::markTestSkipped('Redis is not available');
        }

        $factory = new RedisConnectionFactory();

        $redisClient = $factory->create($redisUrl);
        static::assertInstanceOf(\Redis::class, $redisClient);

        $this->redis = $redisClient;
        $this->incrementer = new RedisIncrementer($this->redis);
        $this->incrementer->setPool('test');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->redis?->flushAll();
    }

    public function testIncrement(): void
    {
        $this->incrementer->increment('test', 't1');
        $this->incrementer->increment('test', 't1');
        $this->incrementer->increment('test', 't1');

        $keys = $this->incrementer->list('test');
        static::assertArrayHasKey('t1', $keys);
        static::assertSame(3, $keys['t1']['count']);
    }

    public function testDecrement(): void
    {
        $this->incrementer->increment('test', 't1');
        $this->incrementer->increment('test', 't1');
        $this->incrementer->decrement('test', 't1');

        $keys = $this->incrementer->list('test');
        static::assertArrayHasKey('t1', $keys);
        static::assertSame(1, $keys['t1']['count']);

        $this->incrementer->decrement('test', 't1');
        $this->incrementer->decrement('test', 't1');
        $this->incrementer->decrement('test', 't1');
        $keys = $this->incrementer->list('test');
        static::assertSame(0, $keys['t1']['count']);
    }

    public function testReset(): void
    {
        $this->incrementer->increment('test', 't1');
        $this->incrementer->increment('test', 't2');

        $this->incrementer->reset('test', 't1');

        static::assertCount(1, $this->incrementer->list('test'));
    }

    public function testResetAll(): void
    {
        $this->incrementer->increment('test', 't1');

        $this->incrementer->reset('test');

        static::assertEmpty($this->incrementer->list('test'));
    }

    public function testDecorated(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->incrementer->getDecorated();
    }
}
