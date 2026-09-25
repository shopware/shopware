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
 * is registered. The `instanceof` rule in `DependencyInjection/mcp.php` only reaches services defined
 * in that file, so plugin and bundle tools never got the cache and returned oversized results inline.
 *
 * Runs before Monolog's LoggerChannelPass (default priority), so the added `monolog.logger` tag still
 * switches the injected logger to the `mcp` channel.
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

                $definition->addMethodCall(self::SETTER, [
                    new Reference(ToolResultCacheStorage::class),
                    new Reference('request_stack'),
                    new Reference('logger'),
                ]);

                if (!$definition->hasTag('monolog.logger')) {
                    $definition->addTag('monolog.logger', ['channel' => 'mcp']);
                }
            }
        }
    }
}
