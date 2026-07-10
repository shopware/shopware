<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
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

        $this->buildAndValidateToolDependencies($container);
        $this->buildToolPrivilegeMap($container);
    }

    /**
     * Reads #[McpToolDependsOn] attributes from every registered tool, validates that each
     * declared dependency name is itself a registered tool, then stores the resolved map as a
     * container parameter so the runtime provider can expand allowlists without reflection.
     *
     * @throws DependencyInjectionException when a dependency name does not match any registered tool
     */
    private function buildAndValidateToolDependencies(ContainerBuilder $container): void
    {
        /** @var array<string, string> $toolNames  tool-name => class */
        $toolNames = [];

        foreach ($container->findTaggedServiceIds('mcp.tool') as $serviceId => $tags) {
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

        $container->setParameter('shopware.mcp.tool_dependencies', $dependencyMap);
    }

    /**
     * Reads #[McpToolRequires] attributes from every registered tool and stores the resolved
     * privilege map as a container parameter for the API endpoint and CLI command.
     *
     * The map is purely informational — it does NOT enforce privileges at runtime.
     */
    private function buildToolPrivilegeMap(ContainerBuilder $container): void
    {
        /** @var array<string, array{static: list<string>, entityParam: ?string, operations: list<string>}> $privilegeMap */
        $privilegeMap = [];

        foreach ($container->findTaggedServiceIds('mcp.tool') as $serviceId => $tags) {
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

        $container->setParameter('shopware.mcp.tool_privileges', $privilegeMap);
    }
}
