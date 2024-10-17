<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Redis;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Adapter\Redis\RedisConnectionProvider;
use Shopware\Core\Framework\Adapter\Redis\RedisConnectionsCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(RedisConnectionsCompilerPass::class)]
class RedisConnectionsCompilerPassTest extends TestCase
{
    private ContainerBuilder $containerBuilder;

    protected function setUp(): void
    {
        $this->containerBuilder = new ContainerBuilder();
        $connectionProviderDefinition = new Definition(RedisConnectionProvider::class, [
            null,
            new Reference('FakeFactoryClassName'),
        ]);
        $this->containerBuilder->setDefinition(RedisConnectionProvider::class, $connectionProviderDefinition);
    }

    public function testProcessCreatesConnections(): void
    {
        $this->containerBuilder->setParameter('shopware.redis.connections', [
            'db1' => ['dsn' => 'redis://localhost:6379/1'],
            'db2' => ['dsn' => 'redis://localhost:6379/2'],
        ]);

        $compilerPass = new RedisConnectionsCompilerPass();
        $compilerPass->process($this->containerBuilder);

        static::assertTrue($this->containerBuilder->hasDefinition('shopware.redis.connection.db1'));
        static::assertTrue($this->containerBuilder->hasDefinition('shopware.redis.connection.db2'));
        static::assertFalse($this->containerBuilder->hasDefinition('shopware.redis.connection.default'));

        $db1Definition = $this->containerBuilder->getDefinition('shopware.redis.connection.db1');
        static::assertSame('Redis', $db1Definition->getClass());

        $factory = $db1Definition->getFactory();
        static::assertIsArray($factory);
        static::assertCount(2, $factory);
        static::assertInstanceOf(Reference::class, $factory[0]);
        static::assertSame(RedisConnectionFactory::class, (string) $factory[0]);
        static::assertSame('create', $factory[1]);

        static::assertSame('redis://localhost:6379/1', $db1Definition->getArgument(0));
        static::assertFalse($db1Definition->isPublic());

        $db2Definition = $this->containerBuilder->getDefinition('shopware.redis.connection.db2');
        static::assertSame('redis://localhost:6379/2', $db2Definition->getArgument(0));
    }

    public function testProcessConfiguresProvider(): void
    {
        $compilerPass = new RedisConnectionsCompilerPass();
        $compilerPass->process($this->containerBuilder);

        // checking if locator is passed to the provider
        $locatorArgument = $this->containerBuilder->getDefinition(RedisConnectionProvider::class)->getArgument(0);
        static::assertInstanceOf(Reference::class, $locatorArgument);

        // and is created properly
        static::assertTrue($this->containerBuilder->hasDefinition((string) $locatorArgument));
        $locatorDefinition = $this->containerBuilder->getDefinition((string) $locatorArgument);
        $className = $locatorDefinition->getClass();
        static::assertIsString($className);
        static::assertArrayHasKey(ContainerInterface::class, class_implements($className));
    }
}
