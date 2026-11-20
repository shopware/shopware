<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Schema\ServerCapabilities;
use Mcp\Server\Subscription\PublishingEventDispatcher;
use Shopware\Core\Framework\Deprecation\BCChange\BecomesInternal;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpListVersions;
use Shopware\Core\Framework\Mcp\Notification\McpListVersionsNotificationBus;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

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
 * It also applies the protocol era configured per server in `shopware.mcp.protocol_era`, see
 * {@see self::applyProtocolEra()}, and states the capabilities each server announces, see
 * {@see self::applyCapabilities()}.
 *
 * Must run after McpToolDiscoveryCompilerPass and McpToolAnalysisCompilerPass.
 */
#[Package('framework')]
#[BecomesInternal(version: 'v6.8.0')]
class McpServerBuilderCompilerPass implements CompilerPassInterface
{
    final public const PROTOCOL_ERA_PARAMETER = 'shopware.mcp.protocol_era';

    /**
     * Serves the handshake revisions only, up to 2025-11-25.
     */
    final public const ERA_LEGACY = 'legacy';

    /**
     * Serves the handshake revisions and the stateless 2026-07-28 revision; the client picks.
     */
    final public const ERA_AUTO = 'auto';

    /**
     * Carries list changes to the `subscriptions/listen` streams of the stateless era.
     */
    final public const ADMIN_NOTIFICATION_BUS = 'shopware.mcp.admin.notification_bus';
    /**
     * The server names configured in packages/mcp.php. Only the Admin API server takes the
     * capability loaders.
     */
    private const SERVERS = [
        'admin' => true,
        'store_api' => false,
    ];

    /**
     * Whether a server registers prompts: the Admin API server has core and app prompts, the Store API
     * server has none.
     */
    private const SERVES_PROMPTS = [
        'admin' => true,
        'store_api' => false,
    ];

    /**
     * Seconds a `subscriptions/listen` stream is held before the server closes it and the client reconnects.
     * Each open stream occupies a PHP worker for that time, so it stays below the common `max_execution_time`
     * of 30 seconds, which would otherwise kill the worker instead of closing the stream.
     */
    private const SUBSCRIPTION_LIFETIME = 25.0;

    public function process(ContainerBuilder $container): void
    {
        foreach (self::SERVERS as $server => $takesLoaders) {
            $builderId = \sprintf('mcp.server.%s.builder', $server);

            if (!$container->hasDefinition($builderId)) {
                continue;
            }

            $definition = $container->getDefinition($builderId);

            $this->scopeBuilderCalls($definition, $server, $takesLoaders);
            $this->applyProtocolEra($container, $definition, $server);
            $this->applyCapabilities($container, $definition, $server);
        }
    }

    /**
     * @return array<string, self::ERA_*>
     */
    public static function protocolEras(ContainerBuilder $container): array
    {
        $configured = $container->hasParameter(self::PROTOCOL_ERA_PARAMETER)
            ? $container->getParameter(self::PROTOCOL_ERA_PARAMETER)
            : [];
        $configured = \is_array($configured) ? $configured : [];

        $eras = [];
        foreach (array_keys(self::SERVERS) as $server) {
            $eras[$server] = ($configured[$server] ?? null) === self::ERA_AUTO ? self::ERA_AUTO : self::ERA_LEGACY;
        }

        return $eras;
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
     * The SDK serves the 2026-07-28 revision next to the handshake revisions from the same endpoint unless
     * it is told not to. `legacy` keeps an endpoint on the handshake revisions, `auto` lets the client pick.
     * The stateless era has no session, see adr/2026-09-24-mcp-stateless-era-degradation-policy.md for what
     * that means per feature.
     *
     * On `auto` the Admin API server also gets a notification bus, so a list change such as an app install
     * reaches the `subscriptions/listen` streams of stateless clients. The bus reads the list versions in the
     * database ({@see McpListVersionsNotificationBus}), so it reaches every worker and host, including changes
     * made on the CLI. The Store API lists only change per session, so that server has nothing to publish and
     * gets no bus; its `server/discover` therefore does not announce `listChanged`.
     *
     * No request state key is configured: no Shopware handler asks the client for input more than once
     * or mints its own request state.
     */
    private function applyProtocolEra(ContainerBuilder $container, Definition $definition, string $server): void
    {
        if (self::protocolEras($container)[$server] === self::ERA_LEGACY) {
            $definition->addMethodCall('withoutModernEra');

            return;
        }

        $definition->addMethodCall('setSubscriptionLifetime', [self::SUBSCRIPTION_LIFETIME]);

        if ($server !== 'admin') {
            return;
        }

        $container->register(self::ADMIN_NOTIFICATION_BUS, McpListVersionsNotificationBus::class)
            ->setArguments([
                new Reference(McpListVersions::class),
                new Reference('cache.app'),
                new Reference('logger'),
            ])
            ->addTag('monolog.logger', ['channel' => 'mcp']);

        // The SDK announces `listChanged` on the stateless era only for a registry that publishes its own
        // changes, which the bundle wires only when it configures the bus itself.
        $registryId = \sprintf('mcp.server.%s.registry', $server);
        if ($container->hasDefinition($registryId)) {
            $container->getDefinition($registryId)->replaceArgument(0, new Definition(PublishingEventDispatcher::class, [
                new Reference(self::ADMIN_NOTIFICATION_BUS),
                new Reference('event_dispatcher'),
            ]));
        }

        $definition->addMethodCall('setNotificationBus', [new Reference(self::ADMIN_NOTIFICATION_BUS)]);
    }

    /**
     * The SDK detects the capabilities from the registry, and announces `resources.subscribe` for every server
     * with resources. Nothing in Shopware publishes `notifications/resources/updated`, so a client that
     * subscribes would wait for updates that never come. The capabilities are therefore stated here, with
     * `subscribe` off; `initialize` and `server/discover` both read them.
     *
     * `listChanged` is announced only where the stateless era delivers list changes, which is the Admin API
     * server with its notification bus. The handshake era declares it in the `initialize` answer itself, see
     * {@see \Shopware\Core\Framework\Mcp\Notification\McpHandshakeListChangedSubscriber}.
     */
    private function applyCapabilities(ContainerBuilder $container, Definition $definition, string $server): void
    {
        $listChanged = $server === 'admin' && self::protocolEras($container)[$server] === self::ERA_AUTO;

        $definition->addMethodCall('setCapabilities', [new Definition(ServerCapabilities::class, [
            '$tools' => true,
            '$toolsListChanged' => $listChanged,
            '$resources' => true,
            '$resourcesSubscribe' => false,
            '$resourcesListChanged' => $listChanged,
            '$prompts' => self::SERVES_PROMPTS[$server],
            '$promptsListChanged' => $listChanged && self::SERVES_PROMPTS[$server],
            '$logging' => true,
            '$completions' => true,
        ])]);
    }
}
