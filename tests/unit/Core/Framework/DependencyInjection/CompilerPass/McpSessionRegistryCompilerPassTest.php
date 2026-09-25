<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Psr16SessionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpSessionRegistryCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpSessionRegistryCompilerPass::class)]
class McpSessionRegistryCompilerPassTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function serverProvider(): iterable
    {
        yield 'Admin API' => ['admin', McpSessionRegistry::class, 'shopware.mcp.session_registry_cache'];
        yield 'Store API' => ['store_api', 'mcp.store_api.session_registry', 'mcp.store_api.session_registry_cache'];
    }

    #[DataProvider('serverProvider')]
    public function testRegistryUsesThePoolOfACacheSessionStore(string $server, string $registryId, string $registryCacheId): void
    {
        $container = $this->container($server, $registryId, $registryCacheId);
        $container->setDefinition($this->sessionStoreId($server), new Definition(Psr16SessionStore::class, [new Reference('cache.mcp_sessions'), 'mcp-' . $server . '-', 3600]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference('cache.mcp_sessions'), $container->getDefinition($registryId)->getArgument(0));
    }

    #[DataProvider('serverProvider')]
    public function testRegistryStaysNextToTheDefaultFileSessionStore(string $server, string $registryId, string $registryCacheId): void
    {
        $container = $this->container($server, $registryId, $registryCacheId);
        $container->setDefinition($this->sessionStoreId($server), new Definition(FileSessionStore::class, ['%kernel.cache_dir%/mcp-sessions/' . $server, 3600]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference($registryCacheId), $container->getDefinition($registryId)->getArgument(0));
        static::assertSame('%kernel.cache_dir%/mcp-sessions/' . $server . '-registry', $this->registryDirectory($container, $registryCacheId));
    }

    public function testRegistryFollowsAFileSessionStoreInAnotherDirectory(): void
    {
        $container = $this->container('admin', McpSessionRegistry::class, 'shopware.mcp.session_registry_cache');
        $container->setDefinition($this->sessionStoreId('admin'), new Definition(FileSessionStore::class, ['/mnt/shared/mcp-sessions/', 3600]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertSame('/mnt/shared/mcp-sessions-registry', $this->registryDirectory($container, 'shopware.mcp.session_registry_cache'));
    }

    public function testOtherSessionStoresKeepTheDefaultRegistry(): void
    {
        $container = $this->container('admin', McpSessionRegistry::class, 'shopware.mcp.session_registry_cache');
        $container->setDefinition($this->sessionStoreId('admin'), new Definition(InMemorySessionStore::class, [3600]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference('shopware.mcp.session_registry_cache'), $container->getDefinition(McpSessionRegistry::class)->getArgument(0));
        static::assertSame('%kernel.cache_dir%/mcp-sessions/admin-registry', $this->registryDirectory($container, 'shopware.mcp.session_registry_cache'));
    }

    public function testLeavesAnOverriddenRegistryCacheAlone(): void
    {
        $container = $this->container('admin', McpSessionRegistry::class, 'shopware.mcp.session_registry_cache');
        // The workaround from shopware/docs#2544: the registry cache service wraps another pool.
        $container->getDefinition('shopware.mcp.session_registry_cache')->setArguments([new Reference('cache.custom')]);
        $container->setDefinition($this->sessionStoreId('admin'), new Definition(Psr16SessionStore::class, [new Reference('cache.mcp_sessions')]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference('shopware.mcp.session_registry_cache'), $container->getDefinition(McpSessionRegistry::class)->getArgument(0));
        static::assertEquals([new Reference('cache.custom')], $container->getDefinition('shopware.mcp.session_registry_cache')->getArguments());
    }

    public function testLeavesARegistryWithAnotherCacheServiceAlone(): void
    {
        $container = $this->container('admin', McpSessionRegistry::class, 'shopware.mcp.session_registry_cache');
        $container->getDefinition(McpSessionRegistry::class)->replaceArgument(0, new Reference('my.registry.cache'));
        $container->setDefinition($this->sessionStoreId('admin'), new Definition(Psr16SessionStore::class, [new Reference('cache.mcp_sessions')]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference('my.registry.cache'), $container->getDefinition(McpSessionRegistry::class)->getArgument(0));
    }

    public function testLeavesAFileAdapterInAnotherDirectoryAlone(): void
    {
        $container = $this->container('admin', McpSessionRegistry::class, 'shopware.mcp.session_registry_cache');
        $container->getDefinition('shopware.mcp.session_registry_cache')->setArguments([new Definition(FilesystemAdapter::class, ['', 0, '/custom/registry'])]);
        $container->setDefinition($this->sessionStoreId('admin'), new Definition(FileSessionStore::class, ['/mnt/shared/mcp-sessions', 3600]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertSame('/custom/registry', $this->registryDirectory($container, 'shopware.mcp.session_registry_cache'));
    }

    public function testIgnoresASessionStoreWithoutALocation(): void
    {
        $container = $this->container('admin', McpSessionRegistry::class, 'shopware.mcp.session_registry_cache');
        $container->setDefinition($this->sessionStoreId('admin'), new Definition(Psr16SessionStore::class, [new Definition(Psr16Cache::class)]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference('shopware.mcp.session_registry_cache'), $container->getDefinition(McpSessionRegistry::class)->getArgument(0));
    }

    public function testDoesNothingWithoutSessionStores(): void
    {
        $container = $this->container('admin', McpSessionRegistry::class, 'shopware.mcp.session_registry_cache');

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertSame('%kernel.cache_dir%/mcp-sessions/admin-registry', $this->registryDirectory($container, 'shopware.mcp.session_registry_cache'));
        static::assertFalse($container->hasDefinition('mcp.store_api.session_registry'));
    }

    private function container(string $server, string $registryId, string $registryCacheId): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition($registryCacheId, new Definition(Psr16Cache::class, [
            new Definition(FilesystemAdapter::class, ['', 0, '%kernel.cache_dir%/mcp-sessions/' . $server . '-registry']),
        ]));
        $container->setDefinition($registryId, new Definition(McpSessionRegistry::class, [new Reference($registryCacheId), 'key', new Reference('lock.factory')]));

        return $container;
    }

    private function sessionStoreId(string $server): string
    {
        return \sprintf('mcp.server.%s.session.store', $server);
    }

    private function registryDirectory(ContainerBuilder $container, string $registryCacheId): mixed
    {
        $adapter = $container->getDefinition($registryCacheId)->getArgument(0);
        static::assertInstanceOf(Definition::class, $adapter);

        return $adapter->getArgument(2);
    }
}
