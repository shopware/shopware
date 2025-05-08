<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Increment;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Increment\RedisIncrementer;

/**
 * @internal
 */
#[Group('redis')]
class RedisIncrementerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $redisUrl = (string) EnvironmentHelper::getVariable('REDIS_URL');

        if ($redisUrl === '') {
            static::markTestSkipped('Redis is not available');
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $factory = new RedisConnectionFactory();
        $redisClient = $factory->create((string) EnvironmentHelper::getVariable('REDIS_URL'));
        static::assertInstanceOf(\Redis::class, $redisClient);

        $redisClient->flushAll();
    }

    public static function incrementerProvider(): \Generator
    {
        yield [null];

        yield ['test'];
    }

    #[DataProvider('incrementerProvider')]
    public function testIncrement(?string $prefix): void
    {
        $incrementer = $this->getIncrementer($prefix);

        $incrementer->increment('test', 't1');
        $incrementer->increment('test', 't1');
        $incrementer->increment('test', 't1');

        $keys = $incrementer->list('test');
        static::assertArrayHasKey('t1', $keys);
        static::assertSame(3, $keys['t1']['count']);
    }

    #[DataProvider('incrementerProvider')]
    public function testDecrement(?string $prefix): void
    {
        $incrementer = $this->getIncrementer($prefix);

        $incrementer->increment('test', 't1');
        $incrementer->increment('test', 't1');
        $incrementer->decrement('test', 't1');

        $keys = $incrementer->list('test');
        static::assertArrayHasKey('t1', $keys);
        static::assertSame(1, $keys['t1']['count']);

        $incrementer->decrement('test', 't1');
        $incrementer->decrement('test', 't1');
        $incrementer->decrement('test', 't1');
        $keys = $incrementer->list('test');
        static::assertSame(0, $keys['t1']['count']);
    }

    #[DataProvider('incrementerProvider')]
    public function testReset(?string $prefix): void
    {
        $incrementer = $this->getIncrementer($prefix);

        $incrementer->increment('test', 't1');
        $incrementer->increment('test', 't2');

        $incrementer->reset('test', 't1');

        static::assertCount(1, $incrementer->list('test'));
    }

    #[DataProvider('incrementerProvider')]
    public function testResetAll(?string $prefix): void
    {
        $incrementer = $this->getIncrementer($prefix);

        $incrementer->increment('test', 't1');

        $incrementer->reset('test');

        static::assertEmpty($incrementer->list('test'));
    }

    private function getIncrementer(?string $prefix = null): RedisIncrementer
    {
        $factory = new RedisConnectionFactory($prefix);

        $redisClient = $factory->create((string) EnvironmentHelper::getVariable('REDIS_URL'));
        static::assertInstanceOf(\Redis::class, $redisClient);

        $incrementer = new RedisIncrementer($redisClient);
        $incrementer->setPool('test');

        return $incrementer;
    }
}
