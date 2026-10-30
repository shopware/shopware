<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Registry\ReferenceHandler;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResultParser;
use Shopware\Core\Framework\Mcp\Result\McpToolResultReferenceHandler;
use Shopware\Core\Framework\Mcp\Result\McpToolResultRenderer;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Routes every tool result on both MCP servers through {@see McpToolResultRenderer} by wrapping the
 * reference handler the builder executes tools with.
 *
 * The MCP bundle sets that handler in its McpPass: its own McpAppReferenceHandler when a server has
 * app tool templates, otherwise none, in which case the builder falls back to a plain ReferenceHandler
 * over the service locator it passes to setContainer(). The wrapper keeps whichever it finds as the
 * inner handler, so it has to run after McpPass, hence the negative priority where it is registered.
 */
#[Package('framework')]
class McpToolResultRendererCompilerPass implements CompilerPassInterface
{
    private const SERVERS = ['admin', 'store_api'];

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(McpToolResultRenderer::class) || !$container->hasDefinition(McpToolResultParser::class)) {
            return;
        }

        foreach (self::SERVERS as $server) {
            $builderId = \sprintf('mcp.server.%s.builder', $server);
            if (!$container->hasDefinition($builderId)) {
                continue;
            }

            $builder = $container->getDefinition($builderId);
            $inner = $this->innerHandler($builder);
            if ($inner === null) {
                continue;
            }

            $builder->removeMethodCall('setReferenceHandler');
            $builder->addMethodCall('setReferenceHandler', [new Definition(McpToolResultReferenceHandler::class, [
                $inner,
                new Reference(McpToolResultRenderer::class),
                new Reference(McpToolResultParser::class),
            ])]);
        }
    }

    /**
     * The handler the builder would otherwise use, or null when it has no tools to execute.
     */
    private function innerHandler(Definition $builder): Reference|Definition|null
    {
        $locator = null;
        $handler = null;

        foreach ($builder->getMethodCalls() as [$method, $arguments]) {
            if ($method === 'setReferenceHandler') {
                $handler = $arguments[0] ?? null;
            }

            if ($method === 'setContainer') {
                $locator = $arguments[0] ?? null;
            }
        }

        if ($handler instanceof Reference || $handler instanceof Definition) {
            return $handler;
        }

        return $locator instanceof Reference ? new Definition(ReferenceHandler::class, [$locator]) : null;
    }
}
