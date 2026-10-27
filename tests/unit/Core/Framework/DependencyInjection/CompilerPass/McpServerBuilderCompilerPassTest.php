<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Server\Subscription\Psr16NotificationBus;
use Mcp\Server\Subscription\PublishingEventDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpServerBuilderCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpServerBuilderCompilerPass::class)]
class McpServerBuilderCompilerPassTest extends TestCase
{
    public function testRequestHandlersAreScopedPerServer(): void
    {
        $container = $this->createContainer();

        (new McpServerBuilderCompilerPass())->process($container);

        static::assertSame('mcp.admin.request_handler', $this->taggedIteratorTag($container, 'admin', 'addRequestHandlers'));
        static::assertSame('mcp.store_api.request_handler', $this->taggedIteratorTag($container, 'store_api', 'addRequestHandlers'));
    }

    public function testNotificationHandlersAreScopedPerServer(): void
    {
        $container = $this->createContainer();

        (new McpServerBuilderCompilerPass())->process($container);

        static::assertSame('mcp.admin.notification_handler', $this->taggedIteratorTag($container, 'admin', 'addNotificationHandlers'));
        static::assertSame('mcp.store_api.notification_handler', $this->taggedIteratorTag($container, 'store_api', 'addNotificationHandlers'));
    }

    public function testCapabilityLoadersStayOnTheAdminServer(): void
    {
        $container = $this->createContainer();

        (new McpServerBuilderCompilerPass())->process($container);

        static::assertSame('mcp.loader', $this->taggedIteratorTag($container, 'admin', 'addLoaders'));
        static::assertSame([], $this->calls($container, 'store_api', 'addLoaders'));
    }

    public function testBothServersPageWithTheShopwareParameter(): void
    {
        $container = $this->createContainer();

        (new McpServerBuilderCompilerPass())->process($container);

        foreach (['admin', 'store_api'] as $server) {
            static::assertSame(
                [['%shopware.mcp.pagination_limit%']],
                $this->calls($container, $server, 'setPaginationLimit'),
            );
        }
    }

    public function testBothServersStayOnTheHandshakeEraByDefault(): void
    {
        $container = $this->createContainer();

        (new McpServerBuilderCompilerPass())->process($container);

        foreach (['admin', 'store_api'] as $server) {
            static::assertSame([[]], $this->calls($container, $server, 'withoutModernEra'));
        }
    }

    public function testTheLegacyEraPinsTheHandshakeEra(): void
    {
        $container = $this->createContainer();
        $container->setParameter(McpServerBuilderCompilerPass::PROTOCOL_ERA_PARAMETER, ['admin' => 'legacy', 'store_api' => 'legacy']);

        (new McpServerBuilderCompilerPass())->process($container);

        foreach (['admin', 'store_api'] as $server) {
            static::assertSame([[]], $this->calls($container, $server, 'withoutModernEra'));
            static::assertSame([], $this->calls($container, $server, 'setNotificationBus'));
        }
        static::assertFalse($container->hasDefinition(McpServerBuilderCompilerPass::ADMIN_NOTIFICATION_BUS));
    }

    public function testTheAutoEraServesBothErasAndGivesTheAdminServerANotificationBus(): void
    {
        $container = $this->createContainer();
        $container->setParameter(McpServerBuilderCompilerPass::PROTOCOL_ERA_PARAMETER, ['admin' => 'auto', 'store_api' => 'auto']);

        (new McpServerBuilderCompilerPass())->process($container);

        foreach (['admin', 'store_api'] as $server) {
            static::assertSame([], $this->calls($container, $server, 'withoutModernEra'));
        }

        $busCalls = $this->calls($container, 'admin', 'setNotificationBus');
        static::assertCount(1, $busCalls);
        static::assertInstanceOf(Reference::class, $busCalls[0][0]);
        static::assertSame(McpServerBuilderCompilerPass::ADMIN_NOTIFICATION_BUS, (string) $busCalls[0][0]);
        static::assertSame([[30.0]], $this->calls($container, 'admin', 'setSubscriptionLifetime'));
        static::assertSame(Psr16NotificationBus::class, $container->getDefinition(McpServerBuilderCompilerPass::ADMIN_NOTIFICATION_BUS)->getClass());

        $dispatcher = $container->getDefinition('mcp.server.admin.registry')->getArgument(0);
        static::assertInstanceOf(Definition::class, $dispatcher);
        static::assertSame(PublishingEventDispatcher::class, $dispatcher->getClass());

        static::assertSame([], $this->calls($container, 'store_api', 'setNotificationBus'));
        static::assertInstanceOf(Reference::class, $container->getDefinition('mcp.server.store_api.registry')->getArgument(0));
    }

    public function testTheEraIsConfiguredPerServer(): void
    {
        $container = $this->createContainer();
        $container->setParameter(McpServerBuilderCompilerPass::PROTOCOL_ERA_PARAMETER, ['admin' => 'legacy', 'store_api' => 'auto']);

        (new McpServerBuilderCompilerPass())->process($container);

        static::assertSame([[]], $this->calls($container, 'admin', 'withoutModernEra'));
        static::assertSame([], $this->calls($container, 'store_api', 'withoutModernEra'));
        static::assertSame(['admin' => 'legacy', 'store_api' => 'auto'], McpServerBuilderCompilerPass::protocolEras($container));
    }

    public function testUnrelatedCallsKeepTheirArgumentsAndOrder(): void
    {
        $container = $this->createContainer();

        (new McpServerBuilderCompilerPass())->process($container);

        $methods = array_column($container->getDefinition('mcp.server.admin.builder')->getMethodCalls(), 0);

        static::assertSame([
            'setServerInfo',
            'setPaginationLimit',
            'addRequestHandlers',
            'addNotificationHandlers',
            'addLoaders',
            'setLogger',
            'withoutModernEra',
        ], $methods);
        static::assertSame([['Shopware', '1.0.0']], $this->calls($container, 'admin', 'setServerInfo'));
    }

    public function testSkipsAServerThatIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        (new McpServerBuilderCompilerPass())->process($container);

        static::assertFalse($container->hasDefinition('mcp.server.admin.builder'));
        static::assertFalse($container->hasDefinition('mcp.server.store_api.builder'));
    }

    /**
     * Mirrors the calls the MCP bundle puts on a server builder it registers from the `servers`
     * configuration.
     */
    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach (['admin' => 'Shopware', 'store_api' => 'Shopware Store API'] as $server => $name) {
            $definition = new Definition();
            $definition->addMethodCall('setServerInfo', [$name, '1.0.0']);
            $definition->addMethodCall('setPaginationLimit', [50]);
            $definition->addMethodCall('addRequestHandlers', [new TaggedIteratorArgument('mcp.request_handler')]);
            $definition->addMethodCall('addNotificationHandlers', [new TaggedIteratorArgument('mcp.notification_handler')]);
            $definition->addMethodCall('addLoaders', [new TaggedIteratorArgument('mcp.loader')]);
            $definition->addMethodCall('setLogger', []);

            $container->setDefinition(\sprintf('mcp.server.%s.builder', $server), $definition);
            $container->setDefinition(\sprintf('mcp.server.%s.registry', $server), new Definition(null, [new Reference('event_dispatcher'), new Reference('logger')]));
        }

        return $container;
    }

    /**
     * @return list<array<mixed>>
     */
    private function calls(ContainerBuilder $container, string $server, string $method): array
    {
        $arguments = [];

        foreach ($container->getDefinition(\sprintf('mcp.server.%s.builder', $server))->getMethodCalls() as [$name, $callArguments]) {
            if ($name === $method) {
                $arguments[] = $callArguments;
            }
        }

        return $arguments;
    }

    private function taggedIteratorTag(ContainerBuilder $container, string $server, string $method): string
    {
        $calls = $this->calls($container, $server, $method);

        static::assertCount(1, $calls);
        static::assertInstanceOf(TaggedIteratorArgument::class, $calls[0][0]);

        return $calls[0][0]->getTag();
    }
}
