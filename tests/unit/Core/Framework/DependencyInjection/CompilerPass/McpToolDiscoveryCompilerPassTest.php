<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpToolDiscoveryCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Symfony\AI\McpBundle\DependencyInjection\ElementMatcher;
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
    /**
     * @return iterable<string, array{list<mixed>}>
     */
    public static function coveringPatternProvider(): iterable
    {
        yield 'namespace prefix' => [['Shopware\\Tests\\Unit\\Core\\Framework\\DependencyInjection\\CompilerPass\\']];
        yield 'exact class name' => [[McpDiscoveryTestNamespacedTool::class]];
        yield 'wildcard' => [['*']];
        yield 'ignores a non-string entry before the match' => [[42, McpDiscoveryTestNamespacedTool::class]];
    }

    /**
     * The bundle stops at the first pattern that matches and treats a pattern which matched nothing
     * as a fatal typo, so a class any configured pattern already reaches must not be added again.
     *
     * @param list<mixed> $patterns
     */
    #[DataProvider('coveringPatternProvider')]
    public function testClassCoveredByAConfiguredPatternIsNotAddedAgain(array $patterns): void
    {
        $container = $this->createContainer();
        $elements = $this->emptyElements();
        $elements['admin']['tools'] = $patterns;
        $container->setParameter('mcp.servers.elements', $elements);
        $container->register(McpDiscoveryTestNamespacedTool::class, McpDiscoveryTestNamespacedTool::class)
            ->addTag('shopware.mcp.tool');

        (new McpToolDiscoveryCompilerPass())->process($container);

        $result = $container->getParameter('mcp.servers.elements');
        static::assertIsArray($result);
        static::assertSame($patterns, $result['admin']['tools']);
    }

    /**
     * The parameter comes from the MCP bundle. If it is ever not the shape we expect, skip the
     * assignment rather than fataling the container build.
     */
    public function testAMalformedElementsParameterIsIgnored(): void
    {
        $container = $this->createContainer();
        $container->setParameter('mcp.servers.elements', 'not-an-array');
        $container->register(McpDiscoveryTestNamespacedTool::class, McpDiscoveryTestNamespacedTool::class)
            ->addTag('shopware.mcp.tool');

        (new McpToolDiscoveryCompilerPass())->process($container);

        static::assertSame('not-an-array', $container->getParameter('mcp.servers.elements'));
    }

    /**
     * A server Shopware knows about but that is not configured in packages/mcp.php simply gets
     * nothing assigned; the servers that are configured are unaffected.
     */
    public function testAScopeMissingFromTheElementsParameterIsSkipped(): void
    {
        $container = $this->createContainer();
        $elements = $this->emptyElements();
        unset($elements['store_api']);
        $container->setParameter('mcp.servers.elements', $elements);
        $container->register(McpDiscoveryTestStoreApiTool::class, McpDiscoveryTestStoreApiTool::class)
            ->addTag('mcp.tool')
            ->addTag('shopware.store_api_mcp.tool');
        $container->register(McpDiscoveryTestNamespacedTool::class, McpDiscoveryTestNamespacedTool::class)
            ->addTag('shopware.mcp.tool');

        (new McpToolDiscoveryCompilerPass())->process($container);

        $result = $container->getParameter('mcp.servers.elements');
        static::assertIsArray($result);
        static::assertArrayNotHasKey('store_api', $result);
        static::assertContains(McpDiscoveryTestNamespacedTool::class, $result['admin']['tools']);
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
     * @return iterable<string, array{string, string, string}>
     */
    public static function storeApiCapabilityProvider(): iterable
    {
        yield 'prompt' => ['shopware.store_api_mcp.prompt', 'mcp.prompt', 'prompts'];
        yield 'resource' => ['shopware.store_api_mcp.resource', 'mcp.resource', 'resources'];
    }

    /**
     * The bundle only collects services carrying an SDK tag, so a Store API prompt or resource has to
     * be remapped like a tool. Without the remap its class is still appended to the Store API element
     * list, and a pattern that matches no registered service is fatal in the bundle's compiler pass,
     * so the container build breaks rather than the capability quietly disappearing.
     */
    #[DataProvider('storeApiCapabilityProvider')]
    public function testStoreApiPromptsAndResourcesAreRemappedAndScopedToTheirServer(string $shopwareTag, string $sdkTag, string $kind): void
    {
        $container = $this->createContainer();
        $container->setParameter('mcp.servers.elements', $this->emptyElements());
        $container->register('store_api.capability', McpDiscoveryTestStoreApiTool::class)->addTag($shopwareTag);

        (new McpToolDiscoveryCompilerPass())->process($container);

        static::assertTrue(
            $container->getDefinition('store_api.capability')->hasTag($sdkTag),
            \sprintf('"%s" must be remapped to "%s" or the bundle never collects it.', $shopwareTag, $sdkTag),
        );

        $elements = $container->getParameter('mcp.servers.elements');
        static::assertIsArray($elements);
        static::assertContains(McpDiscoveryTestStoreApiTool::class, $elements['store_api'][$kind]);
        static::assertNotContains(McpDiscoveryTestStoreApiTool::class, $elements['admin'][$kind]);
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function allowlistProvider(): iterable
    {
        yield 'an allowlist that keeps the theme tool' => [['shopware-discovery-core-tool', 'shopware-theme-config']];
        yield 'an allowlist that drops the theme tool' => [['shopware-discovery-core-tool']];
        yield 'an allowlist that drops every tool' => [['a-tool-no-service-provides']];
    }

    /**
     * packages/mcp.php claims `Shopware\Storefront\Mcp\` for the Admin API server, and that
     * namespace holds exactly one tool. The bundle's McpPass treats a configured pattern matching no
     * remaining service as a fatal typo, so an allowlist that removes shopware-theme-config must not
     * leave the prefix behind: the container would refuse to build — taking down every request and
     * console command — instead of just hiding the tool.
     *
     * @param list<string> $allowedTools
     */
    #[DataProvider('allowlistProvider')]
    #[TestDox('$_dataName leaves no configured pattern without a match')]
    public function testTheAllowlistNeverLeavesAConfiguredPatternWithoutAMatch(array $allowedTools): void
    {
        $container = $this->createContainer();
        $container->setParameter('shopware.mcp.allowed_tools', $allowedTools);

        // The Admin API registry is one list in packages/mcp.php; the bundle copies it into every kind.
        $elements = $this->emptyElements();
        foreach (array_keys($elements['admin']) as $kind) {
            $elements['admin'][$kind] = ['Shopware\\Core\\Framework\\Mcp\\', 'Shopware\\Storefront\\Mcp\\'];
        }
        $container->setParameter('mcp.servers.elements', $elements);

        // The production service ids stand in for the production classes: the bundle matches a
        // namespace prefix against the service id as well as the class.
        $container->register('Shopware\\Core\\Framework\\Mcp\\Tool\\EntitySearchTool', McpDiscoveryTestCoreTool::class)->addTag('mcp.tool');
        $container->register('Shopware\\Storefront\\Mcp\\Tool\\ThemeConfigTool', McpDiscoveryTestThemeConfigTool::class)->addTag('mcp.tool');

        (new McpToolDiscoveryCompilerPass())->process($container);

        static::assertSame([], $this->patternsMatchingNoService($container));
    }

    #[TestDox('A pattern is only dropped when it is orphaned, never while it still reaches a service')]
    public function testAPatternThatStillMatchesIsKept(): void
    {
        $container = $this->createContainer();
        $container->setParameter('shopware.mcp.allowed_tools', ['shopware-discovery-core-tool']);

        $elements = $this->emptyElements();
        $elements['admin']['tools'] = ['Shopware\\Core\\Framework\\Mcp\\', 'Shopware\\Storefront\\Mcp\\', '*'];
        $container->setParameter('mcp.servers.elements', $elements);

        $container->register('Shopware\\Core\\Framework\\Mcp\\Tool\\EntitySearchTool', McpDiscoveryTestCoreTool::class)->addTag('mcp.tool');
        $container->register('Shopware\\Storefront\\Mcp\\Tool\\ThemeConfigTool', McpDiscoveryTestThemeConfigTool::class)->addTag('mcp.tool');

        (new McpToolDiscoveryCompilerPass())->process($container);

        /** @var array<string, array<string, list<string>>> $result */
        $result = $container->getParameter('mcp.servers.elements');

        static::assertContains('Shopware\\Core\\Framework\\Mcp\\', $result['admin']['tools']);
        static::assertNotContains('Shopware\\Storefront\\Mcp\\', $result['admin']['tools']);
        // The bundle exempts the wildcard from the check, so pruning it would only lose elements.
        static::assertContains('*', $result['admin']['tools']);
    }

    /**
     * The invariant, checked against the bundle's own matcher instead of a copy of its rules: every
     * pattern left in `mcp.servers.elements` still has to reach a registered service.
     *
     * @return list<string> "<server>: <pattern>" for every pattern that matches nothing
     */
    private function patternsMatchingNoService(ContainerBuilder $container): array
    {
        /** @var array<string, array<string, list<string>>> $elements */
        $elements = $container->getParameter('mcp.servers.elements');

        $matcher = new ElementMatcher($elements);

        $kindTags = [
            'tools' => 'mcp.tool',
            'prompts' => 'mcp.prompt',
            'resources' => 'mcp.resource',
            'resource_templates' => 'mcp.resource_template',
            'apps' => 'mcp.app',
        ];

        foreach ($kindTags as $kind => $tag) {
            foreach (array_keys($container->findTaggedServiceIds($tag)) as $serviceId) {
                $definition = $container->getDefinition($serviceId);
                /** @var class-string $class */
                $class = $definition->getClass() ?? $serviceId;

                $matcher->match($kind, $serviceId, $class);
            }
        }

        return array_map(
            static fn (array $entry): string => $entry[0] . ': ' . $entry[1],
            $matcher->getUnusedPatterns(),
        );
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

/**
 * @internal
 */
#[McpTool(name: 'shopware-theme-config', description: 'stands in for the Storefront theme config tool')]
class McpDiscoveryTestThemeConfigTool extends McpToolResponse
{
    public function __invoke(): string
    {
        return '';
    }
}
