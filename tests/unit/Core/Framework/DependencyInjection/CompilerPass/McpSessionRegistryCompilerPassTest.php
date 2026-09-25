<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Session\Psr16SessionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpSessionRegistryCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
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
    public function testRegistryFollowsACacheSessionStore(string $server, string $registryId, string $registryCacheId): void
    {
        $container = $this->container($registryId, $registryCacheId);
        $container->setDefinition(
            \sprintf('mcp.server.%s.session.store', $server),
            new Definition(Psr16SessionStore::class, [new Reference('cache.mcp_sessions'), 'mcp-' . $server . '-', 3600]),
        );

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference('cache.mcp_sessions'), $container->getDefinition($registryId)->getArgument(0));
    }

    #[DataProvider('serverProvider')]
    public function testRegistryKeepsItsDefaultPoolForAFileSessionStore(string $server, string $registryId, string $registryCacheId): void
    {
        $container = $this->container($registryId, $registryCacheId);
        $container->setDefinition(\sprintf('mcp.server.%s.session.store', $server), new Definition(FileSessionStore::class, ['/tmp/mcp', 3600]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference($registryCacheId), $container->getDefinition($registryId)->getArgument(0));
    }

    public function testLeavesAnOverriddenRegistryCacheAlone(): void
    {
        $container = $this->container(McpSessionRegistry::class, 'shopware.mcp.session_registry_cache');
        // The workaround from the docs: the registry cache service wraps another pool.
        $container->getDefinition('shopware.mcp.session_registry_cache')->setArguments([new Reference('cache.custom')]);
        $container->setDefinition('mcp.server.admin.session.store', new Definition(Psr16SessionStore::class, [new Reference('cache.mcp_sessions')]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference('shopware.mcp.session_registry_cache'), $container->getDefinition(McpSessionRegistry::class)->getArgument(0));
    }

    public function testLeavesARegistryWithAnotherCacheServiceAlone(): void
    {
        $container = $this->container(McpSessionRegistry::class, 'shopware.mcp.session_registry_cache');
        $container->getDefinition(McpSessionRegistry::class)->replaceArgument(0, new Reference('my.registry.cache'));
        $container->setDefinition('mcp.server.admin.session.store', new Definition(Psr16SessionStore::class, [new Reference('cache.mcp_sessions')]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference('my.registry.cache'), $container->getDefinition(McpSessionRegistry::class)->getArgument(0));
    }

    public function testIgnoresASessionStoreWithoutAPoolReference(): void
    {
        $container = $this->container(McpSessionRegistry::class, 'shopware.mcp.session_registry_cache');
        $container->setDefinition('mcp.server.admin.session.store', new Definition(Psr16SessionStore::class, [new Definition(Psr16Cache::class)]));

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference('shopware.mcp.session_registry_cache'), $container->getDefinition(McpSessionRegistry::class)->getArgument(0));
    }

    public function testDoesNothingWithoutSessionStoresOrRegistries(): void
    {
        $container = $this->container(McpSessionRegistry::class, 'shopware.mcp.session_registry_cache');

        (new McpSessionRegistryCompilerPass())->process($container);

        static::assertEquals(new Reference('shopware.mcp.session_registry_cache'), $container->getDefinition(McpSessionRegistry::class)->getArgument(0));
        static::assertFalse($container->hasDefinition('mcp.store_api.session_registry'));
    }

    private function container(string $registryId, string $registryCacheId): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition($registryCacheId, new Definition(Psr16Cache::class, [new Reference('cache.app')]));
        $container->setDefinition($registryId, new Definition(McpSessionRegistry::class, [new Reference($registryCacheId), 'key', new Reference('lock.factory')]));

        return $container;
    }
}
