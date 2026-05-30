<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolDiscoveryCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolDiscoveryCompilerPass::class)]
class McpToolDiscoveryCompilerPassTest extends TestCase
{
    public function testPluginTagsAreRemappedToMcpTags(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpDiscoveryTestNamespacedTool::class);
        $def->addTag('shopware.mcp.tool');
        $container->setDefinition(McpDiscoveryTestNamespacedTool::class, $def);

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertTrue($container->getDefinition(McpDiscoveryTestNamespacedTool::class)->hasTag('mcp.tool'));
    }

    public function testDuplicateToolNamesThrow(): void
    {
        $container = $this->createContainer();

        $def1 = new Definition(McpDiscoveryTestCoreTool::class);
        $def1->addTag('mcp.tool');
        $container->setDefinition('tool.first', $def1);

        $def2 = new Definition(McpDiscoveryTestCoreTool::class);
        $def2->addTag('mcp.tool');
        $container->setDefinition('tool.second', $def2);

        $this->expectException(DependencyInjectionException::class);
        $this->expectExceptionMessageMatches('/duplicate/i');

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);
    }

    public function testPluginToolWithNamespacePasses(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpDiscoveryTestNamespacedTool::class);
        $def->addTag('shopware.mcp.tool');
        $container->setDefinition(McpDiscoveryTestNamespacedTool::class, $def);

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasDefinition(McpDiscoveryTestNamespacedTool::class));
    }

    public function testAllowlistRemovesNonAllowedTools(): void
    {
        $container = $this->createContainer();
        $container->setParameter('shopware.mcp.allowed_tools', ['shopware-discovery-core-tool']);

        $allowed = new Definition(McpDiscoveryTestCoreTool::class);
        $allowed->addTag('mcp.tool');
        $container->setDefinition('tool.allowed', $allowed);

        $blocked = new Definition(McpDiscoveryTestNamespacedTool::class);
        $blocked->addTag('mcp.tool');
        $container->setDefinition('tool.blocked', $blocked);

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasDefinition('tool.allowed'));
        static::assertFalse($container->hasDefinition('tool.blocked'));
    }

    public function testEmptyAllowlistKeepsAllTools(): void
    {
        $container = $this->createContainer();
        $container->setParameter('shopware.mcp.allowed_tools', []);

        $def = new Definition(McpDiscoveryTestCoreTool::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.core', $def);

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasDefinition('tool.core'));
    }

    public function testToolWithoutMcpAttributeIsSkippedInConflictDetection(): void
    {
        $container = $this->createContainer();

        $def1 = new Definition(McpDiscoveryTestNoAttribute::class);
        $def1->addTag('mcp.tool');
        $container->setDefinition('tool.no-attr', $def1);

        $def2 = new Definition(McpDiscoveryTestCoreTool::class);
        $def2->addTag('mcp.tool');
        $container->setDefinition('tool.core', $def2);

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasDefinition('tool.no-attr'));
        static::assertTrue($container->hasDefinition('tool.core'));
    }

    public function testAllowlistRemovesToolWithoutMcpAttribute(): void
    {
        $container = $this->createContainer();
        $container->setParameter('shopware.mcp.allowed_tools', ['shopware-discovery-core-tool']);

        $def = new Definition(McpDiscoveryTestNoAttribute::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.no-attr', $def);

        $allowed = new Definition(McpDiscoveryTestCoreTool::class);
        $allowed->addTag('mcp.tool');
        $container->setDefinition('tool.allowed', $allowed);

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertFalse($container->hasDefinition('tool.no-attr'));
        static::assertTrue($container->hasDefinition('tool.allowed'));
    }

    public function testNonExistentClassIsSkippedInConflictDetection(): void
    {
        $container = $this->createContainer();

        $def = new Definition('App\\NonExistent\\ToolClass');
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.ghost', $def);

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasDefinition('tool.ghost'));
    }

    public function testSkipsWhenNoMcpServerBuilder(): void
    {
        $container = new ContainerBuilder();

        $def = new Definition(McpDiscoveryTestCoreTool::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.core', $def);

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasDefinition('tool.core'));
    }

    public function testToolDependenciesParameterIsAlwaysInitialized(): void
    {
        $container = new ContainerBuilder();

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasParameter('shopware.mcp.tool_dependencies'));
        static::assertSame([], $container->getParameter('shopware.mcp.tool_dependencies'));
    }

    public function testToolPrivilegesParameterIsAlwaysInitialized(): void
    {
        $container = new ContainerBuilder();

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasParameter('shopware.mcp.tool_privileges'));
        static::assertSame([], $container->getParameter('shopware.mcp.tool_privileges'));
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
#[McpTool(name: 'shopware-discovery-core-tool', description: 'test core tool')]
class McpDiscoveryTestCoreTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'my-discovery-namespaced-tool', description: 'test namespaced tool')]
class McpDiscoveryTestNamespacedTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
class McpDiscoveryTestNoAttribute
{
    public function __invoke(): string
    {
        return '';
    }
}
