<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
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
        $container->setParameter('shopware.mcp.tool_dependencies', []);
        $container->setParameter('shopware.mcp.tool_privileges', []);

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
        $this->detectToolNameConflicts($container);
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

    private function detectToolNameConflicts(ContainerBuilder $container): void
    {
        /** @var array<string, string> $toolNames tool-name => service-id */
        $toolNames = [];

        foreach ($container->findTaggedServiceIds('mcp.tool') as $serviceId => $tags) {
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
}
