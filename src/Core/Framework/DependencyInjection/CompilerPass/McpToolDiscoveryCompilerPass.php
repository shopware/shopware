<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @experimental stableVersion:v6.8.0
 *
 * First MCP compiler pass: remaps Shopware-specific tags to MCP SDK tags, assigns the remapped
 * capabilities to an MCP server, enforces the configured tool allowlist, and detects duplicate tool
 * name conflicts.
 *
 * Must run before McpToolAnalysisCompilerPass and McpServerBuilderCompilerPass, and before the
 * bundle's own McpPass — hence the explicit priority where it is registered.
 */
#[Package('framework')]
class McpToolDiscoveryCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (['shopware.mcp.', 'shopware.store_api_mcp.'] as $paramPrefix) {
            $container->setParameter($paramPrefix . 'tool_dependencies', []);
            $container->setParameter($paramPrefix . 'tool_privileges', []);
            $container->setParameter($paramPrefix . 'advertised_tools', []);
            $container->setParameter($paramPrefix . 'tool_groups', []);
        }

        if (!$container->hasDefinition('mcp.server.admin.builder')) {
            return;
        }

        // The bundle only collects the SDK tags, so every Shopware-scoped capability has to carry one
        // too. Store API capabilities are remapped as well: their own tag stays on as the scope
        // marker the analysis passes and assignElementsToServers() read.
        $tagMapping = [
            'shopware.mcp.tool' => 'mcp.tool',
            'shopware.mcp.prompt' => 'mcp.prompt',
            'shopware.mcp.resource' => 'mcp.resource',
            'shopware.store_api_mcp.tool' => 'mcp.tool',
            'shopware.store_api_mcp.prompt' => 'mcp.prompt',
            'shopware.store_api_mcp.resource' => 'mcp.resource',
        ];

        foreach ($tagMapping as $shopwareTag => $mcpTag) {
            foreach ($container->findTaggedServiceIds($shopwareTag) as $serviceId => $tags) {
                $definition = $container->getDefinition($serviceId);

                if (!$definition->hasTag($mcpTag)) {
                    $definition->addTag($mcpTag);
                }
            }
        }

        $this->enforceToolAllowlist($container, $this->adminToolIds($container));

        // After the allowlist, so a blocked tool's class is never handed to the bundle: it removes
        // the service, and a pattern naming a service that no longer exists is fatal there.
        $this->assignElementsToServers($container);

        // Per scope: names are unique within a scope's own registry, and the two scopes deliberately
        // share names — both endpoints expose their own shopware-tool-search, shopware-toolsets-list
        // and shopware-toolset-enable. Checking them in one pool would report those as duplicates.
        // The ids are re-read because the allowlist may have removed services.
        $storeApiToolIds = array_keys($container->findTaggedServiceIds('shopware.store_api_mcp.tool'));

        foreach ([[$this->adminToolIds($container), 'shopware.mcp.advertised_tools'], [$storeApiToolIds, 'shopware.store_api_mcp.advertised_tools']] as [$serviceIds, $advertisedParam]) {
            $this->detectToolNameConflicts($container, $serviceIds);
            $this->buildAdvertisedTools($container, $serviceIds, $advertisedParam);
        }
    }

    /**
     * Hands the bundle's McpPass the capabilities that its `registry` patterns cannot name.
     *
     * A server's element list is configured in packages/mcp.php as namespace prefixes, which covers
     * core and in-tree bundles but not plugins or third-party bundles: their namespace is arbitrary,
     * and the "*" wildcard is not usable because it would also claim the other server's elements.
     * The bundle passes those lists to its compiler pass through the `mcp.servers.elements`
     * parameter, so appending the resolved class names here assigns each capability to exactly one
     * server. An exact class name always matches, so it can never become an unused pattern (which
     * the bundle treats as a fatal typo).
     */
    private function assignElementsToServers(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('mcp.servers.elements')) {
            return;
        }

        $elements = $container->getParameter('mcp.servers.elements');

        if (!\is_array($elements)) {
            return;
        }

        $scopes = [
            'admin' => [
                'tools' => 'mcp.tool',
                'prompts' => 'mcp.prompt',
                'resources' => 'mcp.resource',
                'resource_templates' => 'mcp.resource_template',
            ],
            'store_api' => [
                'tools' => 'shopware.store_api_mcp.tool',
                'prompts' => 'shopware.store_api_mcp.prompt',
                'resources' => 'shopware.store_api_mcp.resource',
            ],
        ];

        $storeApiScoped = $this->storeApiScopedServiceIds($container);

        foreach ($scopes as $server => $kinds) {
            if (!isset($elements[$server])) {
                continue;
            }

            foreach ($kinds as $kind => $tag) {
                foreach (array_keys($container->findTaggedServiceIds($tag)) as $serviceId) {
                    // A Store API capability carries an SDK tag as well, so the bundle collects it at
                    // all. It must not additionally be claimed by the Admin API server, whose scope
                    // reads those same SDK tags.
                    if ($server === 'admin' && isset($storeApiScoped[$serviceId])) {
                        continue;
                    }

                    $class = $container->getDefinition($serviceId)->getClass() ?? $serviceId;

                    // Only capabilities no configured pattern reaches. The bundle stops at the first
                    // pattern that matches and reports every pattern that matched nothing as a fatal
                    // typo, so adding a class the namespace prefix already covers would break the
                    // container build.
                    if (!self::isCovered($elements[$server][$kind] ?? [], $serviceId, $class)) {
                        $elements[$server][$kind][] = $class;
                    }
                }
            }
        }

        $container->setParameter('mcp.servers.elements', $elements);
    }

    /**
     * Mirrors the bundle's own pattern matching: the "*" wildcard, an exact service id or class, or a
     * namespace prefix recognised by its trailing backslash.
     *
     * @param array<mixed> $patterns
     */
    private static function isCovered(array $patterns, string $serviceId, string $class): bool
    {
        foreach ($patterns as $pattern) {
            if (!\is_string($pattern)) {
                continue;
            }

            if ($pattern === '*' || $pattern === $serviceId || $pattern === $class) {
                return true;
            }

            if (str_ends_with($pattern, '\\') && (str_starts_with($class, $pattern) || str_starts_with($serviceId, $pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The Admin API tools: everything carrying the SDK tag except the Store API ones, which carry it
     * only so the bundle collects them for their own server.
     *
     * @return list<string>
     */
    private function adminToolIds(ContainerBuilder $container): array
    {
        $storeApiTools = $container->findTaggedServiceIds('shopware.store_api_mcp.tool');

        return array_values(array_filter(
            array_keys($container->findTaggedServiceIds('mcp.tool')),
            static fn (string $serviceId): bool => !isset($storeApiTools[$serviceId]),
        ));
    }

    /**
     * Every service scoped to the Store API server, of any kind, as a lookup keyed by service id.
     *
     * @return array<string, true>
     */
    private function storeApiScopedServiceIds(ContainerBuilder $container): array
    {
        $ids = [];

        foreach (['shopware.store_api_mcp.tool', 'shopware.store_api_mcp.prompt', 'shopware.store_api_mcp.resource'] as $tag) {
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $serviceId) {
                $ids[$serviceId] = true;
            }
        }

        return $ids;
    }

    /**
     * When shopware.mcp.allowed_tools is non-empty, remove any tool services
     * whose name is not in the allowlist.
     */
    /**
     * @param list<string> $serviceIds
     */
    private function enforceToolAllowlist(ContainerBuilder $container, array $serviceIds): void
    {
        if (!$container->hasParameter('shopware.mcp.allowed_tools')) {
            return;
        }

        /** @var list<string> $allowedTools */
        $allowedTools = $container->getParameter('shopware.mcp.allowed_tools');

        if ($allowedTools === []) {
            return;
        }

        foreach ($serviceIds as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $toolInfo = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name', 'description']);

            if ($toolInfo === null || !\in_array($toolInfo['name'], $allowedTools, true)) {
                $container->removeDefinition($serviceId);
            }
        }
    }

    /**
     * @param list<string> $serviceIds
     */
    private function detectToolNameConflicts(ContainerBuilder $container, array $serviceIds): void
    {
        /** @var array<string, string> $toolNames tool-name => service-id */
        $toolNames = [];

        foreach ($serviceIds as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $toolInfo = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name', 'description']);

            if ($toolInfo === null || $toolInfo['name'] === null) {
                continue;
            }

            if (isset($toolNames[$toolInfo['name']])) {
                throw DependencyInjectionException::duplicateMcpToolName($toolInfo['name'], $toolNames[$toolInfo['name']], $serviceId);
            }

            $toolNames[$toolInfo['name']] = $serviceId;
        }
    }

    /**
     * The initial tools/list surface is exactly the discovery group (tool-search + toolsets-list/
     * -enable). Every other tool is deferred and only advertised once its toolset is enabled, so a
     * domain tool cannot leak into the default surface — group membership is the single gate.
     */
    /**
     * @param list<string> $serviceIds
     */
    private function buildAdvertisedTools(ContainerBuilder $container, array $serviceIds, string $advertisedParam): void
    {
        $advertisedTools = [];

        foreach ($serviceIds as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $toolInfo = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name']);

            if ($toolInfo === null || !\is_string($toolInfo['name'] ?? null) || !class_exists($class)) {
                continue;
            }

            $groupInfo = McpToolAttributeReader::resolveInfo($class, McpToolGroup::class, ['group']);

            if ($groupInfo !== null && ($groupInfo['group'] ?? null) === McpToolsetRegistry::DISCOVERY_GROUP) {
                $advertisedTools[] = $toolInfo['name'];
            }
        }

        $container->setParameter($advertisedParam, array_values(array_unique($advertisedTools)));
    }
}
