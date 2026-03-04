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
 * Collects services tagged with "shopware.mcp.tool" from plugins
 * and re-tags them with "mcp.tool" so the MCP SDK discovers them.
 * Also detects duplicate tool name conflicts across all registered tools.
 */
#[Package('framework')]
class McpToolCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
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

        $this->collectAllowedConsoleCommands($container);
        $this->enforceToolAllowlist($container);
        $this->detectToolNameConflicts($container);
    }

    /**
     * Merges console commands tagged with "shopware.mcp.allowed_command" into the
     * configured allowlist so plugins can expose their commands via the console tool.
     */
    private function collectAllowedConsoleCommands(ContainerBuilder $container): void
    {
        $taggedIds = $container->findTaggedServiceIds('shopware.mcp.allowed_command');

        if ($taggedIds === []) {
            return;
        }

        $commandNames = [];

        foreach (array_keys($taggedIds) as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;

            if (!class_exists($class)) {
                continue;
            }

            $ref = new \ReflectionClass($class);

            foreach ($ref->getAttributes(\Symfony\Component\Console\Attribute\AsCommand::class) as $attr) {
                $instance = $attr->newInstance();
                $commandNames[] = $instance->name;
            }
        }

        if ($commandNames === []) {
            return;
        }

        /** @var list<string> $existing */
        $existing = $container->hasParameter('shopware.mcp.allowed_console_commands')
            ? $container->getParameter('shopware.mcp.allowed_console_commands')
            : [];

        $container->setParameter(
            'shopware.mcp.allowed_console_commands',
            array_values(array_unique([...$existing, ...$commandNames])),
        );
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
            $toolName = $this->resolveToolName($class);

            if ($toolName !== null && !\in_array($toolName, $allowedTools, true)) {
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

            $toolName = $this->resolveToolName($class);

            if ($toolName === null) {
                continue;
            }

            if (isset($toolNames[$toolName])) {
                throw DependencyInjectionException::duplicateMcpToolName($toolName, $toolNames[$toolName], $serviceId);
            }

            $toolNames[$toolName] = $serviceId;
        }
    }

    private function resolveToolName(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }

        $ref = new \ReflectionClass($class);

        foreach ($ref->getAttributes(McpTool::class) as $attr) {
            $instance = $attr->newInstance();

            return $instance->name;
        }

        return null;
    }
}
