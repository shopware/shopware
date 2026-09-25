<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Server\Session\Psr16SessionStore;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Keeps each MCP session registry in the same place as the sessions it lists. The registry holds the
 * ids that `tools/list_changed` broadcasts go to, so it has to be as widely shared as the session
 * store: when a server keeps its sessions in a cache pool (`session: {store: cache, cache_pool: ...}`,
 * for example Redis), its registry uses that pool too. Otherwise it keeps its default, `cache.app`.
 *
 * A registry cache that was overridden in the service configuration is left alone.
 */
#[Package('framework')]
class McpSessionRegistryCompilerPass implements CompilerPassInterface
{
    private const DEFAULT_REGISTRY_POOL = 'cache.app';

    /**
     * Server name => [registry service id, default registry cache service id].
     */
    private const REGISTRIES = [
        'admin' => [McpSessionRegistry::class, 'shopware.mcp.session_registry_cache'],
        'store_api' => ['mcp.store_api.session_registry', 'mcp.store_api.session_registry_cache'],
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::REGISTRIES as $server => [$registryId, $registryCacheId]) {
            $sessionStoreId = \sprintf('mcp.server.%s.session.store', $server);

            if (!$container->hasDefinition($registryId) || !$container->hasDefinition($sessionStoreId)) {
                continue;
            }

            $sessionStore = $container->getDefinition($sessionStoreId);
            if ($sessionStore->getClass() !== Psr16SessionStore::class) {
                continue;
            }

            $sessionPool = $sessionStore->getArgument(0);
            if (!$sessionPool instanceof Reference || !$this->usesDefaultRegistryCache($container, $registryId, $registryCacheId)) {
                continue;
            }

            $container->getDefinition($registryId)->replaceArgument(0, $sessionPool);
        }
    }

    private function usesDefaultRegistryCache(ContainerBuilder $container, string $registryId, string $registryCacheId): bool
    {
        $registryCache = $container->getDefinition($registryId)->getArgument(0);
        if (!$registryCache instanceof Reference || (string) $registryCache !== $registryCacheId || !$container->hasDefinition($registryCacheId)) {
            return false;
        }

        $pool = $container->getDefinition($registryCacheId)->getArguments()[0] ?? null;

        return $pool instanceof Reference && (string) $pool === self::DEFAULT_REGISTRY_POOL;
    }
}
