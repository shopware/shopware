<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Cache\CacheCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheCompilerPass::class)]
class CacheCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->addDefinitions([
            'shopware.cache.invalidator.storage.redis_adapter' => new Definition(),
            'shopware.cache.invalidator.storage.redis' => new Definition(),
            'shopware.cache.invalidator.storage.mysql' => new Definition(),
        ]);
        $this->container->setParameter('shopware.number_range.config.connection', null);
    }

    public function testProcessMySQL(): void
    {
        $container = $this->container;
        $container->setParameter('shopware.cache.invalidation.delay_options.storage', 'mysql');

        $compilerPass = new CacheCompilerPass();
        $compilerPass->process($container);

        static::assertFalse($container->hasDefinition('shopware.cache.invalidator.storage.redis'));
        static::assertFalse($container->hasDefinition('shopware.cache.invalidator.storage.redis_adapter'));
        static::assertTrue($container->hasDefinition('shopware.cache.invalidator.storage.mysql'));
    }

    public function testProcessRedis(): void
    {
        $container = $this->container;
        $container->setParameter('shopware.cache.invalidation.delay_options.storage', 'redis');
        $container->setParameter('shopware.cache.invalidation.delay_options.connection', 'connection_name');

        $compilerPass = new CacheCompilerPass();
        $compilerPass->process($container);

        static::assertTrue($container->hasDefinition('shopware.cache.invalidator.storage.redis'));
        static::assertTrue($container->hasDefinition('shopware.cache.invalidator.storage.redis_adapter'));
        static::assertFalse($container->hasDefinition('shopware.cache.invalidator.storage.mysql'));
    }

    public function testProcessRedisNoConnectionConfigured(): void
    {
        $container = $this->container;
        $container->setParameter('shopware.cache.invalidation.delay_options.storage', 'redis');
        $container->setParameter('shopware.cache.invalidation.delay_options.connection', null); // default value

        self::expectExceptionObject(AdapterException::missingRequiredParameter('shopware.cache.invalidation.delay_options.connection'));
        $compilerPass = new CacheCompilerPass();
        $compilerPass->process($container);
    }
}
