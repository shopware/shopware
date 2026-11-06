<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolAnalysisCompilerPass;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolAttributeReader;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolDependsOn;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Attribute\McpToolRequires;
use Shopware\Core\Framework\Mcp\McpToolsetRegistry;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\Tool\MediaUploadTool;
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

    public function testToolClaimingTheReservedDiscoveryGroupFallsBackToTheCatchAllToolset(): void
    {
        $container = $this->createContainer();

        $claiming = new Definition(McpAnalysisTestClaimingDiscoveryTool::class);
        $claiming->addTag('mcp.tool');
        $container->setDefinition('tool.claiming', $claiming);

        $meta = new Definition(McpAnalysisTestDiscoveryMetaTool::class);
        $meta->addTag('mcp.tool');
        $container->setDefinition('tool.meta', $meta);

        (new McpToolAnalysisCompilerPass())->process($container);

        static::assertSame([
            'swag-analysis-claims-discovery' => McpToolsetRegistry::FALLBACK_GROUP,
            McpToolsetRegistry::ENABLE_TOOLSET_TOOL => McpToolsetRegistry::DISCOVERY_GROUP,
        ], $container->getParameter('shopware.mcp.tool_groups'));

        // The claimed description described the discovery group, so it must not describe the fallback toolset.
        static::assertSame([], $container->getParameter('shopware.mcp.tool_group_descriptions'));
    }

    public function testToolGroupDescriptionsAreResolvedPerGroup(): void
    {
        $container = $this->createContainer();

        foreach ([
            'tool.explicit' => McpAnalysisTestExplicitGroupTool::class,
            'tool.describing' => McpAnalysisTestDescribingGroupTool::class,
            'tool.method-level' => McpAnalysisTestMethodLevelGroupTool::class,
            'tool.blank' => McpAnalysisTestBlankDescriptionTool::class,
        ] as $serviceId => $class) {
            $container->setDefinition($serviceId, (new Definition($class))->addTag('mcp.tool'));
        }

        (new McpToolAnalysisCompilerPass())->process($container);

        // One describing tool is enough for the whole group; a blank description is no description.
        static::assertSame([
            'catalogue' => 'Browse and edit the catalogue.',
            'orders' => 'Manage orders.',
        ], $container->getParameter('shopware.mcp.tool_group_descriptions'));
        static::assertSame([], $this->groupDescriptionConflicts($container));
    }

    public function testStoreApiToolsStayOutOfTheAdminToolMaps(): void
    {
        $container = $this->createContainer();

        // McpToolDiscoveryCompilerPass gives every Store API tool the SDK tag "mcp.tool" as well.
        $container->setDefinition(
            'tool.store-api',
            (new Definition(McpAnalysisTestDescribingGroupTool::class))
                ->addTag('shopware.store_api_mcp.tool')
                ->addTag('mcp.tool'),
        );

        (new McpToolAnalysisCompilerPass())->process($container);

        static::assertSame([], $container->getParameter('shopware.mcp.tool_groups'));
        static::assertSame([], $container->getParameter('shopware.mcp.tool_group_descriptions'));
        static::assertSame(
            ['catalogue' => 'Browse and edit the catalogue.'],
            $container->getParameter('shopware.store_api_mcp.tool_group_descriptions'),
        );
    }

    public function testConflictingExtensionDescriptionsUseTheFirstToolByNameAndWarn(): void
    {
        $container = $this->createContainer();

        // Registered in reverse name order: the result must not depend on registration order.
        $container->setDefinition('tool.conflicting', (new Definition(McpAnalysisTestConflictingDescriptionTool::class))->addTag('mcp.tool'));
        $container->setDefinition('tool.describing', (new Definition(McpAnalysisTestDescribingGroupTool::class))->addTag('mcp.tool'));

        (new McpToolAnalysisCompilerPass())->process($container);

        static::assertSame(
            ['catalogue' => 'Browse and edit the catalogue.'],
            $container->getParameter('shopware.mcp.tool_group_descriptions'),
        );

        $conflicts = $this->groupDescriptionConflicts($container);
        static::assertCount(1, $conflicts);
        static::assertStringContainsString('"swag-analysis-z-conflicting-tool" describes the "catalogue" toolset differently than "swag-analysis-describing-tool"', $conflicts[0]);
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

    public function testACoreDescriptionWinsOverAnExtensionDescriptionOfTheSameGroup(): void
    {
        $container = $this->createContainer();

        // The extension tool sorts before the core tool by name, so only the core rule can let core win.
        $container->setDefinition('tool.extension', (new Definition(McpAnalysisTestDescribingCoreGroupTool::class))->addTag('mcp.tool'));
        $container->setDefinition('tool.core', (new Definition(MediaUploadTool::class))->addTag('mcp.tool'));

        (new McpToolAnalysisCompilerPass())->process($container);

        $coreDescription = McpToolAttributeReader::resolveAttribute(MediaUploadTool::class, McpToolGroup::class)?->description;
        static::assertIsString($coreDescription);

        static::assertSame(['media' => $coreDescription], $container->getParameter('shopware.mcp.tool_group_descriptions'));
        static::assertSame('media', $container->getParameter('shopware.mcp.tool_groups')['acme-analysis-media-export'] ?? null, 'the extension tool still joins the core group');

        $ignored = array_values(array_filter(
            $container->getCompiler()->getLog(),
            static fn (string $message): bool => str_contains($message, 'extensions cannot change a core toolset description'),
        ));
        static::assertCount(1, $ignored);
        static::assertStringContainsString('"acme-analysis-media-export" describes the core "media" toolset', $ignored[0]);
    }

    public function testACommercialToolCannotChangeACoreGroupDescription(): void
    {
        // Commercial is a separately versioned plugin; a Shopware\Commercial class name must not count as core.
        $commercialClass = 'Shopware\\Commercial\\Mcp\\Tool\\AnalysisMediaExportTool';
        if (!class_exists($commercialClass)) {
            class_alias(McpAnalysisTestDescribingCoreGroupTool::class, $commercialClass);
        }

        $container = $this->createContainer();
        $container->setDefinition('tool.commercial', (new Definition($commercialClass))->addTag('mcp.tool'));
        $container->setDefinition('tool.core', (new Definition(MediaUploadTool::class))->addTag('mcp.tool'));

        (new McpToolAnalysisCompilerPass())->process($container);

        static::assertSame(
            ['media' => McpToolAttributeReader::resolveAttribute(MediaUploadTool::class, McpToolGroup::class)?->description],
            $container->getParameter('shopware.mcp.tool_group_descriptions'),
        );

        $ignored = array_values(array_filter(
            $container->getCompiler()->getLog(),
            static fn (string $message): bool => str_contains($message, 'extensions cannot change a core toolset description'),
        ));
        static::assertCount(1, $ignored);
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

    /**
     * @return list<string>
     */
    private function groupDescriptionConflicts(ContainerBuilder $container): array
    {
        return array_values(array_filter(
            $container->getCompiler()->getLog(),
            static fn (string $message): bool => str_contains($message, 'toolset differently'),
        ));
    }
}

/**
 * @internal
 */
#[McpTool(name: 'shopware-analysis-core-tool', description: 'analysis test core tool')]
class McpAnalysisTestCoreTool extends McpToolResponse
{
    public function __invoke(): McpToolResult
    {
        return McpToolResult::success([]);
    }
}

/**
 * @internal
 */
#[McpTool(name: 'my-analysis-dep-tool', description: 'tool that depends on shopware-analysis-core-tool')]
#[McpToolDependsOn('shopware-analysis-core-tool')]
class McpAnalysisTestDependentTool extends McpToolResponse
{
    public function __invoke(): McpToolResult
    {
        return McpToolResult::success([]);
    }
}

/**
 * @internal
 */
#[McpTool(name: 'my-analysis-static-priv-tool', description: 'tool with a static privilege')]
#[McpToolRequires('system_config:read')]
class McpAnalysisTestStaticPrivilegeTool extends McpToolResponse
{
    public function __invoke(): McpToolResult
    {
        return McpToolResult::success([]);
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
    public function __invoke(): McpToolResult
    {
        return McpToolResult::success([]);
    }
}

/**
 * @internal
 */
#[McpTool(name: 'swag-analysis-claims-discovery', description: 'extension tool claiming the reserved group')]
#[McpToolGroup(McpToolsetRegistry::DISCOVERY_GROUP, description: 'Always there.')]
class McpAnalysisTestClaimingDiscoveryTool extends McpToolResponse
{
    public function __invoke(): McpToolResult
    {
        return McpToolResult::success([]);
    }
}

/**
 * @internal
 */
#[McpTool(name: McpToolsetRegistry::ENABLE_TOOLSET_TOOL, description: 'the core enable meta-tool')]
#[McpToolGroup(McpToolsetRegistry::DISCOVERY_GROUP)]
class McpAnalysisTestDiscoveryMetaTool extends McpToolResponse
{
    public function __invoke(): McpToolResult
    {
        return McpToolResult::success([]);
    }
}

/**
 * @internal
 */
#[McpTool(name: 'shopware-analysis-explicit-group-tool', description: 'tool with explicit group')]
#[McpToolGroup('catalogue')]
class McpAnalysisTestExplicitGroupTool extends McpToolResponse
{
    public function __invoke(): McpToolResult
    {
        return McpToolResult::success([]);
    }
}

/**
 * @internal
 */
class McpAnalysisTestMethodLevelGroupTool extends McpToolResponse
{
    #[McpTool(name: 'shopware-analysis-method-level-group-tool', description: 'method-level group tool')]
    #[McpToolGroup('orders', description: 'Manage orders.')]
    public function __invoke(): McpToolResult
    {
        return McpToolResult::success([]);
    }
}

/**
 * @internal
 *
 * Intentionally has no #[McpTool] attribute — exercises the toolInfo === null continue path.
 */
class McpAnalysisTestNoAttributeTool extends McpToolResponse
{
    public function __invoke(): McpToolResult
    {
        return McpToolResult::success([]);
    }
}

/**
 * @internal
 */
#[McpTool(name: 'swag-analysis-describing-tool', description: 'tool that describes its group')]
#[McpToolGroup('catalogue', description: '  Browse and edit the catalogue. ')]
class McpAnalysisTestDescribingGroupTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'swag-analysis-z-conflicting-tool', description: 'tool that describes its group differently')]
#[McpToolGroup('catalogue', description: 'Something else.')]
class McpAnalysisTestConflictingDescriptionTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'swag-analysis-blank-description-tool', description: 'tool with a blank group description')]
#[McpToolGroup('blank', description: '   ')]
class McpAnalysisTestBlankDescriptionTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}

/**
 * @internal
 */
#[McpTool(name: 'acme-analysis-media-export', description: 'extension tool in a core group')]
#[McpToolGroup('media', description: 'Export media, and everything else.')]
class McpAnalysisTestDescribingCoreGroupTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}
