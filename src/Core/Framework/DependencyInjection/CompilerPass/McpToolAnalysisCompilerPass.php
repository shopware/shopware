<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Second MCP compiler pass: builds the tool dependency graph and privilege map from
 * #[McpToolDependsOn] and #[McpToolRequires] attributes, storing both as container
 * parameters for runtime use.
 *
 * Must run after McpToolDiscoveryCompilerPass.
 */
#[Package('framework')]
class McpToolAnalysisCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mcp.server.builder')) {
            return;
        }

        // Build one param set per MCP scope so both endpoints get identical group/dependency/
        // privilege analysis. Admin tools carry the 'mcp.tool' tag; Store API tools carry
        // 'shopware.store_api_mcp.tool'. Each scope writes under its own param prefix.
        foreach ([['mcp.tool', 'shopware.mcp.'], ['shopware.store_api_mcp.tool', 'shopware.store_api_mcp.']] as [$tag, $paramPrefix]) {
            $this->buildAndValidateToolDependencies($container, $tag, $paramPrefix);
            $this->buildToolPrivilegeMap($container, $tag, $paramPrefix);
            $this->buildToolGroupMap($container, $tag, $paramPrefix);
        }
    }

    /**
     * Reads #[McpToolDependsOn] attributes from every registered tool, validates that each
     * declared dependency name is itself a registered tool, then stores the resolved map as a
     * container parameter so the runtime provider can expand allowlists without reflection.
     *
     * @throws DependencyInjectionException when a dependency name does not match any registered tool
     */
    private function buildAndValidateToolDependencies(ContainerBuilder $container, string $tag, string $paramPrefix): void
    {
        /** @var array<string, string> $toolNames  tool-name => class */
        $toolNames = [];

        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $toolInfo = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name', 'description']);

            if ($toolInfo !== null && $toolInfo['name'] !== null) {
                $toolNames[$toolInfo['name']] = $class;
            }
        }

        /** @var array<string, list<string>> $dependencyMap  tool-name => [dep-name, ...] */
        $dependencyMap = [];

        foreach ($toolNames as $toolName => $class) {
            if (!class_exists($class)) {
                continue; // @codeCoverageIgnore
            }

            $dependencies = [];

            foreach ((new \ReflectionClass($class))->getAttributes(McpToolDependsOn::class) as $attr) {
                /** @var McpToolDependsOn $instance */
                $instance = $attr->newInstance();

                if (!isset($toolNames[$instance->toolName])) {
                    throw DependencyInjectionException::unknownMcpToolDependency($toolName, $instance->toolName);
                }

                $dependencies[] = $instance->toolName;
            }

            if ($dependencies !== []) {
                $dependencyMap[$toolName] = $dependencies;
            }
        }

        $container->setParameter($paramPrefix . 'tool_dependencies', $dependencyMap);
    }

    /**
     * Reads #[McpToolRequires] attributes from every registered tool and stores the resolved
     * privilege map as a container parameter for the API endpoint and CLI command.
     *
     * The map is purely informational — it does NOT enforce privileges at runtime.
     */
    private function buildToolPrivilegeMap(ContainerBuilder $container, string $tag, string $paramPrefix): void
    {
        /** @var array<string, array{static: list<string>, entityParam: ?string, operations: list<string>}> $privilegeMap */
        $privilegeMap = [];

        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $toolInfo = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name', 'description']);

            if ($toolInfo === null || $toolInfo['name'] === null || !class_exists($class)) {
                continue;
            }

            $static = [];
            $entityParam = null;
            $operations = [];

            foreach ((new \ReflectionClass($class))->getAttributes(McpToolRequires::class) as $attr) {
                /** @var McpToolRequires $instance */
                $instance = $attr->newInstance();

                if ($instance->privilege !== null) {
                    $static[] = $instance->privilege;
                } elseif ($instance->entityParam !== null) {
                    $entityParam = $instance->entityParam;
                    $operations = array_merge($operations, $instance->operations);
                }
            }

            if ($static !== [] || $entityParam !== null) {
                $privilegeMap[$toolInfo['name']] = [
                    'static' => $static,
                    'entityParam' => $entityParam,
                    'operations' => array_values(array_unique($operations)),
                ];
            }
        }

        $container->setParameter($paramPrefix . 'tool_privileges', $privilegeMap);
    }

    private function buildToolGroupMap(ContainerBuilder $container, string $tag, string $paramPrefix): void
    {
        /** @var array<string, string> $groupMap tool-name => group */
        $groupMap = [];

        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;

            if (!class_exists($class)) {
                $this->warnMissingToolClass($container, $serviceId, $class);

                continue;
            }

            $tool = McpToolAttributeReader::resolveAttribute($class, McpTool::class);
            if ($tool === null || $tool->name === null || $tool->name === '') {
                continue;
            }

            $group = McpToolAttributeReader::resolveAttribute($class, McpToolGroup::class)?->group;
            if ($group !== null && $group !== '') {
                $groupMap[$tool->name] = $group;
            }
        }

        $container->setParameter($paramPrefix . 'tool_groups', $groupMap);
    }

    /**
     * A service tagged "mcp.tool" whose class cannot be autoloaded is almost always a development or
     * configuration mistake. We skip it rather than fail the whole container build, but surface it as
     * a compiler log message so the misconfiguration is not lost silently.
     */
    private function warnMissingToolClass(ContainerBuilder $container, string $serviceId, string $class): void
    {
        $container->log(
            $this,
            \sprintf(
                'MCP tool service "%s" is tagged "mcp.tool" but its class "%s" cannot be loaded; skipping it during tool analysis.',
                $serviceId,
                $class,
            ),
        );
    }
}
