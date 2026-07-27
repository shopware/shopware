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
 * First MCP compiler pass: remaps Shopware-specific tags to MCP SDK tags, enforces the
 * configured tool allowlist, and detects duplicate tool name conflicts.
 *
 * Must run before McpToolAnalysisCompilerPass and McpServerBuilderCompilerPass.
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

        if (!$container->hasDefinition('mcp.server.builder')) {
            return;
        }

        $tagMapping = [
            'shopware.mcp.tool' => 'mcp.tool',
            'shopware.mcp.prompt' => 'mcp.prompt',
            'shopware.mcp.resource' => 'mcp.resource',
        ];

        foreach ($tagMapping as $shopwareTag => $mcpTag) {
            foreach ($container->findTaggedServiceIds($shopwareTag) as $serviceId => $tags) {
                $definition = $container->getDefinition($serviceId);

                if (!$definition->hasTag($mcpTag)) {
                    $definition->addTag($mcpTag);
                }
            }
        }

        $this->enforceToolAllowlist($container);

        // Per scope: names are unique within a scope's own registry (admin and Store API may
        // legitimately share a name like shopware-tool-search across the two registries).
        foreach (['mcp.tool' => 'shopware.mcp.advertised_tools', 'shopware.store_api_mcp.tool' => 'shopware.store_api_mcp.advertised_tools'] as $tag => $advertisedParam) {
            $this->detectToolNameConflicts($container, $tag);
            $this->buildAdvertisedTools($container, $tag, $advertisedParam);
        }
    }

    /**
     * When shopware.mcp.allowed_tools is non-empty, remove any tool services
     * whose name is not in the allowlist.
     */
    private function enforceToolAllowlist(ContainerBuilder $container): void
    {
        if (!$container->hasParameter('shopware.mcp.allowed_tools')) {
            return;
        }

        /** @var list<string> $allowedTools */
        $allowedTools = $container->getParameter('shopware.mcp.allowed_tools');

        if ($allowedTools === []) {
            return;
        }

        foreach ($container->findTaggedServiceIds('mcp.tool') as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $toolInfo = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name', 'description']);

            if ($toolInfo === null || !\in_array($toolInfo['name'], $allowedTools, true)) {
                $container->removeDefinition($serviceId);
            }
        }
    }

    private function detectToolNameConflicts(ContainerBuilder $container, string $tag): void
    {
        /** @var array<string, string> $toolNames tool-name => service-id */
        $toolNames = [];

        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
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
    private function buildAdvertisedTools(ContainerBuilder $container, string $tag, string $advertisedParam): void
    {
        $advertisedTools = [];

        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
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
