<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\Transport\Mcp\UcpMcpToolDispatcher;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Collects every service tagged `ucp.mcp_tool` into the
 * {@see UcpMcpToolDispatcher} registry. Tag attributes:
 *   - tool_name     UCP MCP tool identifier (e.g. `create_cart`)
 *   - capability    UCP capability the tool depends on
 *
 * @internal
 */
#[Package('framework')]
class UcpMcpToolCompilerPass implements CompilerPassInterface
{
    public const TAG = 'ucp.mcp_tool';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(UcpMcpToolDispatcher::class)) {
            return;
        }

        $dispatcher = $container->getDefinition(UcpMcpToolDispatcher::class);
        $taggedServices = $container->findTaggedServiceIds(self::TAG);

        $tools = [];
        $capabilityMap = [];

        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $tag) {
                $name = $tag['tool_name'] ?? null;
                $capability = $tag['capability'] ?? null;

                if (!\is_string($name) || $name === '') {
                    throw UcpException::mcpToolTagInvalid($id, 'missing required "tool_name" attribute');
                }
                if (!\is_string($capability) || $capability === '') {
                    throw UcpException::mcpToolTagInvalid($id, 'missing required "capability" attribute');
                }

                if (isset($tools[$name])) {
                    throw UcpException::mcpToolTagInvalid(
                        $id,
                        \sprintf('duplicate tool name "%s" — already registered by "%s"', $name, $tools[$name])
                    );
                }

                $tools[$name] = $id;
                $capabilityMap[$name] = $capability;
            }
        }

        $references = [];
        foreach ($tools as $name => $serviceId) {
            $references[$name] = new Reference($serviceId);
        }

        $dispatcher->setArgument('$tools', $references);
        $dispatcher->setArgument('$capabilityMap', $capabilityMap);
    }
}
