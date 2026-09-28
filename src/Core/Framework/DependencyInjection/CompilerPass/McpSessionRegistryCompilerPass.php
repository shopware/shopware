<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Session\Psr16SessionStore;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Keeps each MCP session registry exactly as shared as the sessions it lists. The registry holds the
 * ids that `tools/list_changed` broadcasts go to, and the notifier drops ids whose session it cannot
 * find. A registry that is more widely shared than the sessions would therefore drop sessions of other
 * servers, and one that is less widely shared would miss them.
 *
 * - `session: {store: cache, cache_pool: ...}`: the registry uses the same cache pool.
 * - The default file store: the registry is a file cache next to the session directory, so it is
 *   shared by every process on the server that shares the session files.
 *
 * Any other session store keeps the default registry, and a registry cache that was overridden in the
 * service configuration is left alone.
 */
#[Package('framework')]
class McpSessionRegistryCompilerPass implements CompilerPassInterface
{
    /**
     * Server name => [registry service id, default registry cache service id, default registry directory].
     */
    private const REGISTRIES = [
        'admin' => [McpSessionRegistry::class, 'shopware.mcp.session_registry_cache', '%kernel.cache_dir%/mcp-sessions/admin-registry'],
        'store_api' => ['mcp.store_api.session_registry', 'mcp.store_api.session_registry_cache', '%kernel.cache_dir%/mcp-sessions/store_api-registry'],
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::REGISTRIES as $server => [$registryId, $registryCacheId, $defaultDirectory]) {
            $sessionStoreId = \sprintf('mcp.server.%s.session.store', $server);

            if (!$container->hasDefinition($registryId) || !$container->hasDefinition($sessionStoreId)) {
                continue;
            }

            $adapter = $this->defaultRegistryAdapter($container, $registryId, $registryCacheId, $defaultDirectory);
            if ($adapter === null) {
                continue;
            }

            $sessionStore = $container->getDefinition($sessionStoreId);
            $location = $sessionStore->getArguments()[0] ?? null;

            if ($sessionStore->getClass() === Psr16SessionStore::class && $location instanceof Reference) {
                $container->getDefinition($registryId)->replaceArgument(0, $location);

                continue;
            }

            if ($sessionStore->getClass() === FileSessionStore::class && \is_string($location) && $location !== '') {
                $adapter->replaceArgument(2, rtrim($location, '/') . '-registry');
            }
        }
    }

    /**
     * Returns the file adapter of the registry cache when the registry still uses its default from
     * `DependencyInjection/mcp.php`, or null when it was overridden.
     */
    private function defaultRegistryAdapter(ContainerBuilder $container, string $registryId, string $registryCacheId, string $defaultDirectory): ?Definition
    {
        $registryCache = $container->getDefinition($registryId)->getArguments()[0] ?? null;
        if (!$registryCache instanceof Reference || (string) $registryCache !== $registryCacheId || !$container->hasDefinition($registryCacheId)) {
            return null;
        }

        $adapter = $container->getDefinition($registryCacheId)->getArguments()[0] ?? null;
        if (!$adapter instanceof Definition || $adapter->getClass() !== FilesystemAdapter::class || ($adapter->getArguments()[2] ?? null) !== $defaultDirectory) {
            return null;
        }

        return $adapter;
    }
}
