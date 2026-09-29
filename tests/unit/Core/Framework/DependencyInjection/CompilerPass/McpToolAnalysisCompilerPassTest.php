<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolAnalysisCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolAnalysisCompilerPass::class)]
class McpToolAnalysisCompilerPassTest extends TestCase
{
    public function testToolDependenciesParameterIsEmptyWhenNoToolsHaveDependencies(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpAnalysisTestCoreTool::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.core', $def);

        $pass = new McpToolAnalysisCompilerPass();
        $pass->process($container);

        static::assertSame([], $container->getParameter('shopware.mcp.tool_dependencies'));
    }

    public function testToolDependencyIsResolvedAndStoredInParameter(): void
    {
        $container = $this->createContainer();

        $dep = new Definition(McpAnalysisTestCoreTool::class);
        $dep->addTag('mcp.tool');
        $container->setDefinition('tool.dep', $dep);

        $primary = new Definition(McpAnalysisTestDependentTool::class);
        $primary->addTag('mcp.tool');
        $container->setDefinition('tool.primary', $primary);

        $pass = new McpToolAnalysisCompilerPass();
        $pass->process($container);

        /** @var array<string, list<string>> $deps */
        $deps = $container->getParameter('shopware.mcp.tool_dependencies');

        static::assertArrayHasKey('my-analysis-dep-tool', $deps);
        static::assertSame(['shopware-analysis-core-tool'], $deps['my-analysis-dep-tool']);
    }

    public function testUnknownToolDependencyThrows(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpAnalysisTestDependentTool::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.primary', $def);

        $this->expectException(DependencyInjectionException::class);
        $this->expectExceptionMessageMatches('/shopware-analysis-core-tool/');

        $pass = new McpToolAnalysisCompilerPass();
        $pass->process($container);
    }

    public function testToolPrivilegesParameterIsEmptyWhenNoToolsHavePrivileges(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpAnalysisTestCoreTool::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.core', $def);

        $pass = new McpToolAnalysisCompilerPass();
        $pass->process($container);

        static::assertSame([], $container->getParameter('shopware.mcp.tool_privileges'));
    }

    public function testStaticToolPrivilegeIsResolvedAndStored(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpAnalysisTestStaticPrivilegeTool::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.static', $def);

        $pass = new McpToolAnalysisCompilerPass();
        $pass->process($container);

        /** @var array<string, array{static: list<string>, entityParam: ?string, operations: list<string>}> $privileges */
        $privileges = $container->getParameter('shopware.mcp.tool_privileges');

        static::assertArrayHasKey('my-analysis-static-priv-tool', $privileges);
        static::assertSame(['system_config:read'], $privileges['my-analysis-static-priv-tool']['static']);
        static::assertNull($privileges['my-analysis-static-priv-tool']['entityParam']);
        static::assertSame([], $privileges['my-analysis-static-priv-tool']['operations']);
    }

    public function testDynamicToolPrivilegeIsResolvedAndStored(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpAnalysisTestDynamicPrivilegeTool::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.dynamic', $def);

        $pass = new McpToolAnalysisCompilerPass();
        $pass->process($container);

        /** @var array<string, array{static: list<string>, entityParam: ?string, operations: list<string>}> $privileges */
        $privileges = $container->getParameter('shopware.mcp.tool_privileges');

        static::assertArrayHasKey('my-analysis-dynamic-priv-tool', $privileges);
        static::assertSame([], $privileges['my-analysis-dynamic-priv-tool']['static']);
        static::assertSame('entity', $privileges['my-analysis-dynamic-priv-tool']['entityParam']);
        static::assertSame(['read', 'update'], $privileges['my-analysis-dynamic-priv-tool']['operations']);
    }

    public function testToolWithNoMcpToolAttributeIsSkippedInPrivilegeMap(): void
    {
        $container = $this->createContainer();

        $def = new Definition(McpAnalysisTestNoAttributeTool::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.no-attr', $def);

        $pass = new McpToolAnalysisCompilerPass();
        $pass->process($container);

        static::assertSame([], $container->getParameter('shopware.mcp.tool_privileges'));
    }

    public function testToolGroupsAreResolvedAndStored(): void
    {
        $container = $this->createContainer();

        $explicit = new Definition(McpAnalysisTestExplicitGroupTool::class);
        $explicit->addTag('mcp.tool');
        $container->setDefinition('tool.explicit', $explicit);

        $derived = new Definition(McpAnalysisTestCoreTool::class);
        $derived->addTag('mcp.tool');
        $container->setDefinition('tool.derived', $derived);

        $methodLevel = new Definition(McpAnalysisTestMethodLevelGroupTool::class);
        $methodLevel->addTag('mcp.tool');
        $container->setDefinition('tool.method-level', $methodLevel);

        $pass = new McpToolAnalysisCompilerPass();
        $pass->process($container);

        static::assertSame([
            'shopware-analysis-explicit-group-tool' => 'catalogue',
            'shopware-analysis-method-level-group-tool' => 'orders',
        ], $container->getParameter('shopware.mcp.tool_groups'));
    }

    public function testToolWithMissingClassIsSkippedAndWarns(): void
    {
        $container = $this->createContainer();

        $def = new Definition('App\\Does\\Not\\Exist\\Tool');
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.missing', $def);

        (new McpToolAnalysisCompilerPass())->process($container);

        static::assertSame([], $container->getParameter('shopware.mcp.tool_groups'));

        $warnings = array_values(array_filter(
            $container->getCompiler()->getLog(),
            static fn (string $message): bool => str_contains($message, 'tool.missing'),
        ));
        static::assertCount(1, $warnings);
        static::assertStringContainsString('cannot be loaded', $warnings[0]);
    }

    public function testSkipsWhenNoMcpServerBuilder(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('shopware.mcp.tool_dependencies', []);
        $container->setParameter('shopware.mcp.tool_privileges', []);

        $def = new Definition(McpAnalysisTestCoreTool::class);
        $def->addTag('mcp.tool');
        $container->setDefinition('tool.core', $def);

        $pass = new McpToolAnalysisCompilerPass();
        $pass->process($container);

        static::assertSame([], $container->getParameter('shopware.mcp.tool_dependencies'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('mcp.server.admin.builder');
        $container->setParameter('shopware.mcp.tool_dependencies', []);
        $container->setParameter('shopware.mcp.tool_privileges', []);
        $container->setParameter('shopware.mcp.tool_groups', []);

        return $container;
    }
}

/**
 * @internal
 */
#[McpTool(name: 'shopware-analysis-core-tool', description: 'analysis test core tool')]
class McpAnalysisTestCoreTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'my-analysis-dep-tool', description: 'tool that depends on shopware-analysis-core-tool')]
#[McpToolDependsOn('shopware-analysis-core-tool')]
class McpAnalysisTestDependentTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'my-analysis-static-priv-tool', description: 'tool with a static privilege')]
#[McpToolRequires('system_config:read')]
class McpAnalysisTestStaticPrivilegeTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'my-analysis-dynamic-priv-tool', description: 'tool with dynamic privileges per entity')]
#[McpToolRequires(entityParam: 'entity', operations: ['read'])]
#[McpToolRequires(entityParam: 'entity', operations: ['update'])]
class McpAnalysisTestDynamicPrivilegeTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'shopware-analysis-explicit-group-tool', description: 'tool with explicit group')]
#[McpToolGroup('catalogue')]
class McpAnalysisTestExplicitGroupTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
class McpAnalysisTestMethodLevelGroupTool extends McpToolResponse
{
    #[McpTool(name: 'shopware-analysis-method-level-group-tool', description: 'method-level group tool')]
    #[McpToolGroup('orders')]
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 *
 * Intentionally has no #[McpTool] attribute — exercises the toolInfo === null continue path.
 */
class McpAnalysisTestNoAttributeTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}
