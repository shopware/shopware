<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Redis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Adapter\Redis\RedisConnectionProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Contracts\Service\ServiceLocatorTrait;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(RedisConnectionProvider::class)]
class RedisConnectionProviderTest extends TestCase
{
    /**
     * @var \stdClass[]
     */
    private array $connections;

    private ContainerInterface $serviceLocator;

    private RedisConnectionFactory&MockObject $redisConnectionFactory;

    private RedisConnectionProvider $redisConnectionProvider;

    protected function setUp(): void
    {
        $this->connections = [
            'persistent' => new \stdClass(),
            'ephemeral' => new \stdClass(),
        ];

        $factories = [
            'shopware.redis.connection.persistent' => fn () => $this->connections['persistent'],
            'shopware.redis.connection.ephemeral' => fn () => $this->connections['ephemeral'],
        ];

        $this->serviceLocator = new class($factories) implements ContainerInterface {
            use ServiceLocatorTrait;
        };

        $this->redisConnectionFactory = $this->createMock(RedisConnectionFactory::class);
        $this->redisConnectionProvider = new RedisConnectionProvider($this->serviceLocator, $this->redisConnectionFactory);
    }

    public function testGetConnection(): void
    {
        $connection = $this->redisConnectionProvider->getConnection('persistent');
        static::assertSame($this->connections['persistent'], $connection);

        $connection = $this->redisConnectionProvider->getConnection('ephemeral');
        static::assertSame($this->connections['ephemeral'], $connection);

        $this->expectException(AdapterException::class);
        $this->redisConnectionProvider->getConnection('some-non-existing-connection');
    }

    public function testHasConnection(): void
    {
        static::assertTrue($this->redisConnectionProvider->hasConnection('persistent'));
        static::assertTrue($this->redisConnectionProvider->hasConnection('ephemeral'));
        static::assertFalse($this->redisConnectionProvider->hasConnection('some-non-existing-connection'));
    }

    /**
     * @deprecated tag:v6.7.0 - getOrCreateFromDsn is replaced by getConnection - Remove in 6.7
     */
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testGetOrCreateFromDsnWithConnectionName(): void
    {
        $this->redisConnectionFactory->expects(static::never())->method('create');
        $connection = $this->redisConnectionProvider->getOrCreateFromDsn('ephemeral', 'redis://localhost:6379');
        static::assertSame($this->connections['ephemeral'], $connection);
    }

    /**
     * @deprecated tag:v6.7.0 getOrCreateFromDsn is replaced by getConnection - Remove in 6.7
     */
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testGetOrCreateFromDsnWithDsn(): void
    {
        $dsn = 'redis://localhost:6379';
        $connection = new \stdClass();
        $this->redisConnectionFactory->expects(static::once())->method('create')->with($dsn)->willReturn($connection);
        $result = $this->redisConnectionProvider->getOrCreateFromDsn(null, $dsn);
        static::assertSame($connection, $result);
    }

    /**
     * @deprecated tag:v6.7.0 - getOrCreateFromDsn is replaced by getConnection - Remove in 6.7
     */
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testGetOrCreateFromDsnThrowsException(): void
    {
        $this->expectException(AdapterException::class);
        $this->expectExceptionMessage('Missing required $connectionName or $dsn parameters (null, null provided).');

        $this->redisConnectionProvider->getOrCreateFromDsn(null, null);
    }
}
