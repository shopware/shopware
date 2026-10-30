<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Gives every MCP tool that extends {@see McpToolResponse} the tool-result cache, wherever the tool
 * is registered. An `instanceof` rule in a services file only reaches the services of that file, so
 * this pass is the one place that wires it, for core, plugin and bundle tools alike.
 */
#[Package('framework')]
class McpToolResultCacheCompilerPass implements CompilerPassInterface
{
    private const TOOL_TAGS = ['mcp.tool', 'shopware.mcp.tool', 'shopware.store_api_mcp.tool'];

    private const SETTER = 'setToolResultCache';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ToolResultCacheStorage::class)) {
            return;
        }

        foreach (self::TOOL_TAGS as $tag) {
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $serviceId) {
                $definition = $container->getDefinition($serviceId);
                $class = $container->getParameterBag()->resolveValue($definition->getClass() ?? $serviceId);

                if (!\is_string($class) || !is_subclass_of($class, McpToolResponse::class) || $definition->hasMethodCall(self::SETTER)) {
                    continue;
                }

                // The channel logger directly, not a `monolog.logger` tag: the tag would also move every
                // other logger the tool injects to the `mcp` channel.
                $definition->addMethodCall(self::SETTER, [
                    new Reference(ToolResultCacheStorage::class),
                    new Reference('request_stack'),
                    new Reference('monolog.logger.mcp'),
                ]);
            }
        }
    }
}
