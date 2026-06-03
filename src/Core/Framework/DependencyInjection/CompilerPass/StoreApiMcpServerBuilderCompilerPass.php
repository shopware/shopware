<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpPrompt;
use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpTool;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[Package('framework')]
class StoreApiMcpServerBuilderCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('mcp.store_api.server.builder')) {
            return;
        }

        $this->registerCapabilitiesWithBuilder($container);
        $this->wireServiceLocator($container);
    }

    private function registerCapabilitiesWithBuilder(ContainerBuilder $container): void
    {
        $builderDef = $container->getDefinition('mcp.store_api.server.builder');

        foreach (array_keys($container->findTaggedServiceIds('shopware.store_api_mcp.tool')) as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $toolInfo = McpToolAttributeReader::resolveInfo($class, McpTool::class, ['name', 'title', 'description']);

            if ($toolInfo !== null) {
                $builderDef->addMethodCall('addTool', [$class, $toolInfo['name'], $toolInfo['title'], $toolInfo['description']]);
            }
        }

        foreach (array_keys($container->findTaggedServiceIds('shopware.store_api_mcp.prompt')) as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $promptInfo = McpToolAttributeReader::resolveInfo($class, McpPrompt::class, ['name', 'title', 'description']);

            $builderDef->addMethodCall('addPrompt', [$class, $promptInfo ? $promptInfo['name'] : null, $promptInfo ? $promptInfo['title'] : null, $promptInfo ? $promptInfo['description'] : null]);
        }

        foreach (array_keys($container->findTaggedServiceIds('shopware.store_api_mcp.resource')) as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class = $definition->getClass() ?? $serviceId;
            $resourceInfo = McpToolAttributeReader::resolveInfo($class, McpResource::class, ['uri', 'name', 'description', 'mimeType']);

            if ($resourceInfo !== null) {
                $builderDef->addMethodCall('addResource', [$class, $resourceInfo['uri'], $resourceInfo['name'], $resourceInfo['description'], $resourceInfo['mimeType']]);
            }
        }
    }

    private function wireServiceLocator(ContainerBuilder $container): void
    {
        $allMcpServices = [];

        foreach (['shopware.store_api_mcp.tool', 'shopware.store_api_mcp.prompt', 'shopware.store_api_mcp.resource'] as $tag) {
            $allMcpServices = array_merge($allMcpServices, $container->findTaggedServiceIds($tag));
        }

        if ($allMcpServices === []) {
            return;
        }

        $serviceReferences = [];
        foreach (array_keys($allMcpServices) as $serviceId) {
            $serviceReferences[$serviceId] = new Reference($serviceId);
        }

        $container->getDefinition('mcp.store_api.server.builder')->addMethodCall('setContainer', [
            ServiceLocatorTagPass::register($container, $serviceReferences),
        ]);
    }
}
