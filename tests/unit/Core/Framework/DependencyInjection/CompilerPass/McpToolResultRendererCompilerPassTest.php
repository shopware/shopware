<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Registry\ReferenceHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolResultRendererCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResultParser;
use Shopware\Core\Framework\Mcp\Result\McpToolResultReferenceHandler;
use Shopware\Core\Framework\Mcp\Result\McpToolResultRenderer;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultRendererCompilerPass::class)]
class McpToolResultRendererCompilerPassTest extends TestCase
{
    public function testWrapsAPlainReferenceHandlerOverTheServiceLocator(): void
    {
        $container = $this->container();
        $container->setDefinition('mcp.server.admin.builder', (new Definition())->addMethodCall('setContainer', [new Reference('locator.admin')]));

        (new McpToolResultRendererCompilerPass())->process($container);

        $wrapper = $this->referenceHandler($container, 'mcp.server.admin.builder');
        static::assertSame(McpToolResultReferenceHandler::class, $wrapper->getClass());
        $inner = $wrapper->getArgument(0);
        static::assertInstanceOf(Definition::class, $inner);
        static::assertSame(ReferenceHandler::class, $inner->getClass());
        static::assertEquals([new Reference('locator.admin')], $inner->getArguments());
        static::assertEquals(new Reference(McpToolResultRenderer::class), $wrapper->getArgument(1));
        static::assertEquals(new Reference(McpToolResultParser::class), $wrapper->getArgument(2));
    }

    public function testWrapsTheReferenceHandlerTheBundleAlreadySet(): void
    {
        $container = $this->container();
        $container->setDefinition('mcp.server.store_api.builder', (new Definition())
            ->addMethodCall('setReferenceHandler', [new Reference('mcp.server.store_api.app.reference_handler')])
            ->addMethodCall('setContainer', [new Reference('locator.store')]));

        (new McpToolResultRendererCompilerPass())->process($container);

        $builder = $container->getDefinition('mcp.server.store_api.builder');
        static::assertCount(1, array_filter($builder->getMethodCalls(), static fn (array $call): bool => $call[0] === 'setReferenceHandler'));
        static::assertEquals(new Reference('mcp.server.store_api.app.reference_handler'), $this->referenceHandler($container, 'mcp.server.store_api.builder')->getArgument(0));
    }

    public function testLeavesABuilderWithoutToolsAlone(): void
    {
        $container = $this->container();
        $container->setDefinition('mcp.server.admin.builder', new Definition());

        (new McpToolResultRendererCompilerPass())->process($container);

        static::assertSame([], $container->getDefinition('mcp.server.admin.builder')->getMethodCalls());
    }

    public function testDoesNothingWithoutTheRenderer(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('mcp.server.admin.builder', (new Definition())->addMethodCall('setContainer', [new Reference('locator.admin')]));

        (new McpToolResultRendererCompilerPass())->process($container);

        static::assertCount(1, $container->getDefinition('mcp.server.admin.builder')->getMethodCalls());
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(McpToolResultRenderer::class, new Definition(McpToolResultRenderer::class));
        $container->setDefinition(McpToolResultParser::class, new Definition(McpToolResultParser::class));

        return $container;
    }

    private function referenceHandler(ContainerBuilder $container, string $builderId): Definition
    {
        foreach ($container->getDefinition($builderId)->getMethodCalls() as [$method, $arguments]) {
            if ($method === 'setReferenceHandler') {
                static::assertInstanceOf(Definition::class, $arguments[0]);

                return $arguments[0];
            }
        }

        static::fail('No setReferenceHandler call on ' . $builderId);
    }
}
