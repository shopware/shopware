<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Adjusts the server builders the MCP bundle registers from the `servers` configuration in
 * packages/mcp.php, for the three things that configuration cannot express.
 *
 * 1. Protocol handlers are scoped per server. The bundle wires `addRequestHandlers()` and
 *    `addNotificationHandlers()` from one global tag for every server, but Shopware's handlers are
 *    bound to one registry: both McpAllowlistListRequestHandler instances answer a ListToolsRequest,
 *    so on a shared tag whichever came first would answer on both endpoints.
 * 2. Capability loaders stay on the Admin API server. The app loaders (tagged `mcp.loader`) publish
 *    app capabilities, which have always been an Admin API concern; leaving the bundle's global
 *    `addLoaders()` in place would newly advertise every app tool on /store-api/_mcp too.
 * 3. Both servers page with Shopware's own pagination parameter, so the number the allowlist request
 *    handlers slice with cannot drift from the one the SDK advertises.
 *
 * It also pins both servers to the handshake protocol era — see {@see self::pinHandshakeEra()}.
 *
 * Must run after McpToolDiscoveryCompilerPass and McpToolAnalysisCompilerPass.
 */
#[Package('framework')]
class McpServerBuilderCompilerPass implements CompilerPassInterface
{
    /**
     * The server names configured in packages/mcp.php. Only the Admin API server takes the
     * capability loaders.
     */
    private const SERVERS = [
        'admin' => true,
        'store_api' => false,
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::SERVERS as $server => $takesLoaders) {
            $builderId = \sprintf('mcp.server.%s.builder', $server);

            if (!$container->hasDefinition($builderId)) {
                continue;
            }

            $definition = $container->getDefinition($builderId);

            $this->scopeBuilderCalls($definition, $server, $takesLoaders);
            $this->pinHandshakeEra($definition);
        }
    }

    /**
     * Rewrites the method calls the bundle put on the builder. The list is rebuilt in place rather
     * than patched with removeMethodCall(), so the remaining calls keep their original order.
     */
    private function scopeBuilderCalls(Definition $definition, string $server, bool $takesLoaders): void
    {
        $calls = [];

        foreach ($definition->getMethodCalls() as [$method, $arguments]) {
            switch ($method) {
                case 'addRequestHandlers':
                    $arguments = [new TaggedIteratorArgument(\sprintf('mcp.%s.request_handler', $server))];

                    break;
                case 'addNotificationHandlers':
                    $arguments = [new TaggedIteratorArgument(\sprintf('mcp.%s.notification_handler', $server))];

                    break;
                case 'addLoaders':
                    if (!$takesLoaders) {
                        continue 2;
                    }

                    break;
                case 'setPaginationLimit':
                    $arguments = ['%shopware.mcp.pagination_limit%'];

                    break;
            }

            $calls[] = [$method, $arguments];
        }

        $definition->setMethodCalls($calls);
    }

    /**
     * mcp/sdk 0.8 serves the 2026-07-28 revision from the same endpoint as the handshake era, and
     * Shopware builds the transport itself, so both endpoints would start answering it the moment the
     * SDK is upgraded. That era is stateless: it mints no Mcp-Session-Id, which the toolset session
     * storage and the list-changed notifications are built on, and a handler returning an
     * InputRequiredResult additionally needs a signed request state key shared across every worker
     * that might serve the retry.
     *
     * Keep the endpoints on the era they were written for until that is designed and tested.
     */
    private function pinHandshakeEra(Definition $definition): void
    {
        $definition->addMethodCall('withoutModernEra');
    }
}
