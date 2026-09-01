<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolDiscoveryCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
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

    public function testAdvertisedToolsParameterIsAlwaysInitialized(): void
    {
        $container = new ContainerBuilder();

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasParameter('shopware.mcp.advertised_tools'));
        static::assertSame([], $container->getParameter('shopware.mcp.advertised_tools'));
    }

    public function testDiscoveryGroupToolsAreAddedToAdvertisedToolsParameter(): void
    {
        $container = $this->createContainer();

        $visible = new Definition(McpDiscoveryTestDiscoveryGroupTool::class);
        $visible->addTag('mcp.tool');
        $container->setDefinition('tool.visible', $visible);

        $deferred = new Definition(McpDiscoveryTestCoreTool::class);
        $deferred->addTag('mcp.tool');
        $container->setDefinition('tool.deferred', $deferred);

        $methodLevel = new Definition(McpDiscoveryTestMethodLevelDiscoveryGroupTool::class);
        $methodLevel->addTag('mcp.tool');
        $container->setDefinition('tool.method-level', $methodLevel);

        $pass = new McpToolDiscoveryCompilerPass();
        $pass->process($container);

        static::assertSame(
            ['shopware-discovery-visible-tool', 'shopware-discovery-method-visible-tool'],
            $container->getParameter('shopware.mcp.advertised_tools'),
        );
    }

    public function testPluginToolIsAssignedToTheAdminServer(): void
    {
        $container = $this->createContainer();
        $container->setParameter('mcp.servers.elements', $this->emptyElements());
        $container->register(McpDiscoveryTestNamespacedTool::class, McpDiscoveryTestNamespacedTool::class)
            ->addTag('shopware.mcp.tool');

        (new McpToolDiscoveryCompilerPass())->process($container);

        $elements = $container->getParameter('mcp.servers.elements');
        static::assertIsArray($elements);
        static::assertContains(McpDiscoveryTestNamespacedTool::class, $elements['admin']['tools']);
        static::assertNotContains(McpDiscoveryTestNamespacedTool::class, $elements['store_api']['tools']);
    }

    /**
     * A Store API tool carries the SDK tag too, so the bundle collects it at all. It must reach the
     * Store API server only — the Admin API endpoint never advertises Store API tools.
     */
    public function testStoreApiToolIsAssignedToTheStoreApiServerOnly(): void
    {
        $container = $this->createContainer();
        $container->setParameter('mcp.servers.elements', $this->emptyElements());
        $container->register(McpDiscoveryTestStoreApiTool::class, McpDiscoveryTestStoreApiTool::class)
            ->addTag('mcp.tool')
            ->addTag('shopware.store_api_mcp.tool');

        (new McpToolDiscoveryCompilerPass())->process($container);

        $elements = $container->getParameter('mcp.servers.elements');
        static::assertIsArray($elements);
        static::assertContains(McpDiscoveryTestStoreApiTool::class, $elements['store_api']['tools']);
        static::assertNotContains(McpDiscoveryTestStoreApiTool::class, $elements['admin']['tools']);
    }

    /**
     * The bundle stops at the first pattern that matches and treats a pattern which matched nothing
     * as a fatal typo, so a class a configured prefix already covers must not be added again.
     */
    public function testClassCoveredByAConfiguredPrefixIsNotAddedAgain(): void
    {
        $container = $this->createContainer();
        $elements = $this->emptyElements();
        $elements['admin']['tools'] = ['Shopware\\Tests\\Unit\\Core\\Framework\\DependencyInjection\\CompilerPass\\'];
        $container->setParameter('mcp.servers.elements', $elements);
        $container->register(McpDiscoveryTestNamespacedTool::class, McpDiscoveryTestNamespacedTool::class)
            ->addTag('shopware.mcp.tool');

        (new McpToolDiscoveryCompilerPass())->process($container);

        $result = $container->getParameter('mcp.servers.elements');
        static::assertIsArray($result);
        static::assertSame(['Shopware\\Tests\\Unit\\Core\\Framework\\DependencyInjection\\CompilerPass\\'], $result['admin']['tools']);
    }

    public function testStoreApiToolIsNotCountedAsAnAdminToolNameConflict(): void
    {
        $container = $this->createContainer();
        $container->setParameter('mcp.servers.elements', $this->emptyElements());
        // Same tool name on both endpoints — that is deliberate for the discovery meta-tools.
        $container->register(McpDiscoveryTestCoreTool::class, McpDiscoveryTestCoreTool::class)
            ->addTag('mcp.tool');
        $container->register(McpDiscoveryTestSameNameStoreApiTool::class, McpDiscoveryTestSameNameStoreApiTool::class)
            ->addTag('mcp.tool')
            ->addTag('shopware.store_api_mcp.tool');

        (new McpToolDiscoveryCompilerPass())->process($container);

        static::assertTrue($container->hasDefinition(McpDiscoveryTestCoreTool::class));
        static::assertTrue($container->hasDefinition(McpDiscoveryTestSameNameStoreApiTool::class));
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    private function emptyElements(): array
    {
        $kinds = ['tools' => [], 'prompts' => [], 'resources' => [], 'resource_templates' => [], 'apps' => []];

        return ['admin' => $kinds, 'store_api' => $kinds];
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('mcp.server.admin.builder');

        return $container;
    }
}

/**
 * @internal
 */
#[McpTool(name: 'shopware-discovery-store-api-tool', description: 'test store api tool')]
class McpDiscoveryTestStoreApiTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'shopware-discovery-core-tool', description: 'same name as the admin tool')]
class McpDiscoveryTestSameNameStoreApiTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
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
#[McpTool(name: 'shopware-discovery-visible-tool', description: 'test visible tool')]
#[McpToolGroup('discovery')]
class McpDiscoveryTestDiscoveryGroupTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
class McpDiscoveryTestMethodLevelDiscoveryGroupTool extends McpToolResponse
{
    #[McpTool(name: 'shopware-discovery-method-visible-tool', description: 'test method visible tool')]
    #[McpToolGroup('discovery')]
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
