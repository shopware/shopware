<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolResultCacheCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultCacheCompilerPass::class)]
class McpToolResultCacheCompilerPassTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function toolTagProvider(): iterable
    {
        yield 'plugin tool' => ['shopware.mcp.tool'];
        yield 'Store API tool' => ['shopware.store_api_mcp.tool'];
        yield 'bundle tool' => ['mcp.tool'];
    }

    #[DataProvider('toolTagProvider')]
    public function testWiresTheCacheIntoToolsExtendingMcpToolResponse(string $tag): void
    {
        $container = $this->container();
        $container->setDefinition('plugin.tool', (new Definition(EntitySchemaTool::class))->addTag($tag));

        (new McpToolResultCacheCompilerPass())->process($container);

        $definition = $container->getDefinition('plugin.tool');
        static::assertEquals(
            [['setToolResultCache', [new Reference(ToolResultCacheStorage::class), new Reference('request_stack'), new Reference('logger')]]],
            $definition->getMethodCalls(),
        );
        static::assertSame([['channel' => 'mcp']], $definition->getTag('monolog.logger'));
    }

    public function testResolvesParameterisedClassNames(): void
    {
        $container = $this->container();
        $container->setParameter('plugin.tool.class', EntitySchemaTool::class);
        $container->setDefinition('plugin.tool', (new Definition('%plugin.tool.class%'))->addTag('shopware.mcp.tool'));

        (new McpToolResultCacheCompilerPass())->process($container);

        static::assertTrue($container->getDefinition('plugin.tool')->hasMethodCall('setToolResultCache'));
    }

    public function testLeavesToolsAlone(): void
    {
        $container = $this->container();
        // Already wired, for example by the instanceof rule in mcp.php, which also sets the channel.
        $container->setDefinition('core.tool', (new Definition(EntitySchemaTool::class))
            ->addTag('mcp.tool')
            ->addTag('monolog.logger', ['channel' => 'mcp'])
            ->addMethodCall('setToolResultCache', ['existing']));
        // A tool that does not extend McpToolResponse has no cache setter.
        $container->setDefinition('other.tool', (new Definition(\stdClass::class))->addTag('shopware.mcp.tool'));

        (new McpToolResultCacheCompilerPass())->process($container);

        static::assertSame([['setToolResultCache', ['existing']]], $container->getDefinition('core.tool')->getMethodCalls());
        static::assertSame([['channel' => 'mcp']], $container->getDefinition('core.tool')->getTag('monolog.logger'));
        static::assertSame([], $container->getDefinition('other.tool')->getMethodCalls());
        static::assertFalse($container->getDefinition('other.tool')->hasTag('monolog.logger'));
    }

    public function testDoesNothingWithoutTheCacheStorage(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('plugin.tool', (new Definition(EntitySchemaTool::class))->addTag('shopware.mcp.tool'));

        (new McpToolResultCacheCompilerPass())->process($container);

        static::assertSame([], $container->getDefinition('plugin.tool')->getMethodCalls());
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ToolResultCacheStorage::class, new Definition(ToolResultCacheStorage::class));

        return $container;
    }
}
