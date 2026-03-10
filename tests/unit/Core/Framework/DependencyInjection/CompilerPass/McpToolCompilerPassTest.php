<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolCompilerPass::class)]
class McpToolCompilerPassTest extends TestCase
{
    public function testPluginTagsAreRemappedToMcpTags(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpToolCompilerPassTestNamespacedTool::class);
        $def->addTag('shopware.mcp.tool');
        $container->setDefinition(McpToolCompilerPassTestNamespacedTool::class, $def);

        $pass = new McpToolCompilerPass();
        $pass->process($container);

        static::assertTrue($container->getDefinition(McpToolCompilerPassTestNamespacedTool::class)->hasTag('mcp.tool'));
    }

    public function testDuplicateToolNamesThrow(): void
    {
        $container = $this->createContainer();

        $def1 = new Definition(McpToolCompilerPassTestCoreTool::class);
        $def1->addTag('mcp.tool');
        $container->setDefinition('tool.first', $def1);

        $def2 = new Definition(McpToolCompilerPassTestCoreTool::class);
        $def2->addTag('mcp.tool');
        $container->setDefinition('tool.second', $def2);

        $this->expectException(DependencyInjectionException::class);
        $this->expectExceptionMessageMatches('/duplicate/i');

        $pass = new McpToolCompilerPass();
        $pass->process($container);
    }

    public function testPluginToolWithNamespacePasses(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpToolCompilerPassTestNamespacedTool::class);
        $def->addTag('shopware.mcp.tool');
        $container->setDefinition(McpToolCompilerPassTestNamespacedTool::class, $def);

        $pass = new McpToolCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasDefinition(McpToolCompilerPassTestNamespacedTool::class));
    }

    public function testAllowlistRemovesNonAllowedTools(): void
    {
        $container = $this->createContainer();
        $container->setParameter('shopware.mcp.allowed_tools', ['shopware-core-tool']);

        $allowed = new Definition(McpToolCompilerPassTestCoreTool::class);
        $allowed->addTag('mcp.tool');
        $container->setDefinition('tool.allowed', $allowed);

        $blocked = new Definition(McpToolCompilerPassTestNamespacedTool::class);
        $blocked->addTag('mcp.tool');
        $container->setDefinition('tool.blocked', $blocked);

        $pass = new McpToolCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasDefinition('tool.allowed'));
        static::assertFalse($container->hasDefinition('tool.blocked'));
    }

    public function testEmptyAllowlistKeepsAllTools(): void
    {
        $container = $this->createContainer();
        $container->setParameter('shopware.mcp.allowed_tools', []);

        $def = new Definition(McpToolCompilerPassTestCoreTool::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.core', $def);

        $pass = new McpToolCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasDefinition('tool.core'));
    }

    public function testSkipsWhenNoMcpServerBuilder(): void
    {
        $container = new ContainerBuilder();

        $def = new Definition(McpToolCompilerPassTestCoreTool::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.core', $def);

        $pass = new McpToolCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasDefinition('tool.core'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('mcp.server.builder');

        return $container;
    }
}

/**
 * @internal
 */
#[McpTool(name: 'shopware-core-tool', description: 'test core tool')]
class McpToolCompilerPassTestCoreTool
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'my-plugin-namespaced-tool', description: 'test namespaced tool')]
class McpToolCompilerPassTestNamespacedTool
{
    public function __invoke(): string
    {
        return '';
    }
}
