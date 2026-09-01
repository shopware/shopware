<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Command;

use Mcp\Capability\Registry;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptArgument;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Tool;
use Mcp\Server;
use Mcp\Server\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\McpDebugCommandCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Command\DebugMcpCommand;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DebugMcpCommand::class)]
#[CoversClass(McpCapabilityCatalog::class)]
class DebugMcpCommandTest extends TestCase
{
    /**
     * @return iterable<string, array{?Builder, ?Registry}>
     */
    public static function nullableConstructorArgProvider(): iterable
    {
        yield 'builder is null' => [null, new Registry()];
        yield 'registry is null' => [Server::builder(), null];
    }

    #[DataProvider('nullableConstructorArgProvider')]
    public function testExecuteReturnsErrorWhenMcpBundleServiceIsNull(?Builder $builder, ?Registry $registry): void
    {
        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $catalog = new McpCapabilityCatalog(null, $this->stubPrivilegeProvider());

        $command = new DebugMcpCommand($builder, $registry, $allowlistProvider, $catalog);
        $tester = new CommandTester($command);
        $tester->execute([]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('MCP bundle is not installed', $tester->getDisplay());
    }

    public function testOutputsSectionHeaders(): void
    {
        $command = $this->makeCommand(new Registry());
        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Tools', $output);
        // Prompts and resources are listed by the bundle's command; this one points at it.
        static::assertStringContainsString('debug:mcp:native', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testEmptyRegistryShowsNoCapabilitiesMessages(): void
    {
        $command = $this->makeCommand(new Registry());
        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('No tools registered', $output);
    }

    public function testToolIsRenderedCompactInListWithoutDescription(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', null, self::inputSchema(), 'Does things', null),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('my-tool', $output);
        static::assertStringContainsString('Acme\\MyTool', $output);
        static::assertStringNotContainsString('Does things', $output);
        static::assertStringNotContainsString('Description', $output);
    }

    public function testAppProvidedToolShowsAppProvidedSource(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('McpHelloWorld-hello', null, self::inputSchema(), 'Says hello', null),
            static function (): string { return 'hello'; },
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('McpHelloWorld-hello', $output);
        static::assertStringContainsString('(app-provided)', $output);
    }

    public function testArrayHandlerShowsClassAndMethod(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('array-tool', null, self::inputSchema(), null, null),
            ['Acme\\MyTool', 'handle'],
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute([]);

        static::assertStringContainsString('Acme\\MyTool::handle', $tester->getDisplay());
    }

    public function testDetailViewShowsTitleWhenSet(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', 'My Human-Readable Tool', self::inputSchema(), 'Does things', null),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        static::assertStringContainsString('My Human-Readable Tool', $tester->getDisplay());
    }

    /**
     * Title is always rendered, with a dash when the capability carries none, so the block keeps the
     * same shape and the rows below it do not shift.
     */
    public function testDetailViewShowsADashWhenTitleIsNull(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', null, self::inputSchema(), 'Does things', null),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        static::assertMatchesRegularExpression('/Title\s+-/', $tester->getDisplay());
    }

    /**
     * The detail block reads top-down: what it is, where it lives, what governs reaching it, and only
     * then how it is implemented. Handler is last because it is the longest value and the least
     * common reason to open this view.
     */
    public function testDetailViewOrdersMetadataFromIdentityToImplementation(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', 'My Tool', self::inputSchema(), 'Does things', null),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        $output = $tester->getDisplay();
        $positions = [];
        foreach (['Title', 'Type', 'Scope', 'Group', 'Handler'] as $label) {
            $position = mb_strpos($output, $label);
            static::assertNotFalse($position, \sprintf('The detail view is missing the "%s" row.', $label));
            $positions[] = $position;
        }

        $sorted = $positions;
        sort($sorted);
        static::assertSame($sorted, $positions, 'Detail rows must read Title, Type, Scope, Group, Handler.');
    }

    public function testDetailViewShowsToolDescriptionAndSource(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', null, self::inputSchema(), 'Does things for you', null),
            'Acme\\MyTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('my-tool', $output);
        static::assertStringContainsString('Does things for you', $output);
        static::assertStringContainsString('Acme\\MyTool', $output);
        static::assertStringContainsString('tool', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testDetailViewShowsToolParameters(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'entity' => ['type' => 'string', 'description' => 'Entity type to search'],
                'limit' => ['type' => 'int', 'description' => 'Page size', 'default' => 25],
            ],
            'required' => ['entity'],
        ];
        $registry = new Registry();
        $registry->registerTool(
            new Tool('search-tool', null, $schema, 'Searches entities', null),
            'Acme\\SearchTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'search-tool']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('entity', $output);
        static::assertStringContainsString('required', $output);
        static::assertStringContainsString('limit', $output);
        static::assertStringContainsString('optional', $output);
        static::assertStringContainsString('Default: 25', $output);
    }

    public function testDetailViewShowsPromptDescriptionAndSource(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('my-prompt', null, 'Explains everything', []),
            'Acme\\MyPrompt',
            [],
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-prompt']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('my-prompt', $output);
        static::assertStringContainsString('Explains everything', $output);
        static::assertStringContainsString('Acme\\MyPrompt', $output);
        static::assertStringContainsString('prompt', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testDetailViewShowsResourceUriAndDescription(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new ResourceDefinition('shopware://test', 'my-resource', null, 'A helpful resource', null, null, null),
            'Acme\\MyResource',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-resource']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('my-resource', $output);
        static::assertStringContainsString('shopware://test', $output);
        static::assertStringContainsString('A helpful resource', $output);
        static::assertStringContainsString('resource', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testDetailViewCanLookUpResourceByUri(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new ResourceDefinition('shopware://entities', 'entities', null, 'All entity types', null, null, null),
            'Acme\\EntitiesResource',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'shopware://entities']);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('shopware://entities', $tester->getDisplay());
    }

    public function testToolsFilterShowsOnlyTools(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('my-tool', null, self::inputSchema(), 'Tool desc', null), 'Acme\\MyTool');
        $registry->registerPrompt(new Prompt('my-prompt', null, 'Prompt desc', []), 'Acme\\MyPrompt', []);
        $registry->registerResource(new ResourceDefinition('shopware://test', 'my-resource', null, 'Resource desc', null, null, null), 'Acme\\MyResource');

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['--tools' => true]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Tools', $output);
        static::assertStringContainsString('my-tool', $output);
        static::assertStringNotContainsString('Prompts', $output);
        static::assertStringNotContainsString('Resources', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testIntegrationOptionWithNullAllowlistShowsAllToolsAndNote(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('tool-a', null, self::inputSchema(), null, null), 'Acme\\ToolA');
        $registry->registerTool(new Tool('tool-b', null, self::inputSchema(), null, null), 'Acme\\ToolB');

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: null, resources: null, prompts: null));

        $tester = new CommandTester($this->makeCommand($registry, allowlistProvider: $allowlistProvider));
        $tester->execute(['--integration' => 'SWIA-test-key']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('no tool restriction', $output);
        static::assertStringContainsString('tool-a', $output);
        static::assertStringContainsString('tool-b', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testIntegrationOptionFiltersToAllowedTools(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('tool-a', null, self::inputSchema(), null, null), 'Acme\\ToolA');
        $registry->registerTool(new Tool('tool-b', null, self::inputSchema(), null, null), 'Acme\\ToolB');

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: ['tool-a'], resources: null, prompts: null));

        $tester = new CommandTester($this->makeCommand($registry, allowlistProvider: $allowlistProvider));
        $tester->execute(['--integration' => 'SWIA-restricted']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('1/2 allowed', $output);
        static::assertStringContainsString('tool-a', $output);
        static::assertStringNotContainsString('tool-b', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testIntegrationOptionWithEmptyAllowlistShowsNoTools(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('tool-a', null, self::inputSchema(), null, null), 'Acme\\ToolA');

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: [], resources: null, prompts: null));

        $tester = new CommandTester($this->makeCommand($registry, allowlistProvider: $allowlistProvider));
        $tester->execute(['--integration' => 'SWIA-empty']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('0/1 allowed', $output);
        static::assertStringNotContainsString('tool-a', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testDetailViewShowsDependenciesAndStaticPrivileges(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('shopware-entity-delete', null, self::inputSchema(), 'Delete entities', null),
            'Acme\\DeleteTool',
        );

        $catalog = new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            ['shopware-entity-delete' => ['shopware-entity-search']],
            ['shopware-entity-delete' => ['static' => ['system_config:read'], 'entityParam' => null, 'operations' => []]],
        );

        $tester = new CommandTester($this->makeCommand($registry, catalog: $catalog));
        $tester->execute(['name' => 'shopware-entity-delete']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Dependencies', $output);
        static::assertStringContainsString('shopware-entity-search', $output);
        static::assertStringContainsString('Privileges', $output);
        static::assertStringContainsString('system_config:read', $output);
    }

    public function testDetailViewShowsDynamicPrivilegesWithEntityParam(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('shopware-entity-search', null, self::inputSchema(), 'Search entities', null),
            'Acme\\SearchTool',
        );

        $catalog = new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            [],
            ['shopware-entity-search' => ['static' => [], 'entityParam' => 'entity', 'operations' => ['read']]],
        );

        $tester = new CommandTester($this->makeCommand($registry, catalog: $catalog));
        $tester->execute(['name' => 'shopware-entity-search']);

        static::assertStringContainsString('<entity>:read', $tester->getDisplay());
    }

    public function testDetailViewShowsPromptArguments(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('my-prompt', null, 'Explains things', [
                new PromptArgument('topic', 'What to explain', true),
                new PromptArgument('depth', 'Detail level', false),
            ]),
            'Acme\\MyPrompt',
            [],
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-prompt']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Arguments', $output);
        static::assertStringContainsString('topic', $output);
        static::assertStringContainsString('required', $output);
        static::assertStringContainsString('depth', $output);
        static::assertStringContainsString('optional', $output);
    }

    public function testDetailViewShowsResourceMimeType(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new ResourceDefinition('shopware://json', 'json-resource', null, 'JSON resource', 'application/json', null, null),
            'Acme\\JsonResource',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'json-resource']);

        static::assertStringContainsString('application/json', $tester->getDisplay());
    }

    public function testListShowsPrivilegesColumn(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('shopware-entity-search', null, self::inputSchema(), 'Search entities', null),
            'Acme\\SearchTool',
        );

        $catalog = new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            [],
            ['shopware-entity-search' => ['static' => ['system_config:read'], 'entityParam' => 'entity', 'operations' => ['read']]],
        );

        $tester = new CommandTester($this->makeCommand($registry, catalog: $catalog));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Privileges', $output);
        static::assertStringContainsString('system_config:read', $output);
        static::assertStringContainsString('<entity>:read', $output);
    }

    public function testListShowsGroupColumn(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('shopware-entity-search', null, self::inputSchema(), 'Search entities', null),
            'Acme\\SearchTool',
        );

        $catalog = new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            toolGroups: ['shopware-entity-search' => 'catalogue'],
        );

        $tester = new CommandTester($this->makeCommand($registry, catalog: $catalog));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Group', $output);
        static::assertStringContainsString('catalogue', $output);
    }

    public function testDetailViewReturnsFailureForUnknownName(): void
    {
        $tester = new CommandTester($this->makeCommand(new Registry()));
        $tester->execute(['name' => 'does-not-exist']);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('No capability found with name \'does-not-exist\'', $tester->getDisplay());
    }

    public function testDetailViewSkipsNonArrayPropertyDefinitions(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'valid-param' => ['type' => 'string', 'description' => 'A valid param'],
                'bad-param' => 'not-an-array',
            ],
            'required' => ['valid-param'],
        ];
        $registry = new Registry();
        $registry->registerTool(
            new Tool('schema-tool', null, $schema, 'Does things', null),
            'Acme\\SchemaTool',
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'schema-tool']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('valid-param', $output);
        static::assertStringNotContainsString('bad-param', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testArrayHandlerWithObjectInstanceShowsClassName(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('object-tool', null, self::inputSchema(), null, null),
            [new \stdClass(), 'handle'],
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute([]);

        static::assertStringContainsString('stdClass::handle', $tester->getDisplay());
    }

    public function testStoreApiCapabilitiesAreListedAlongsideAdminOnesByDefault(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');

        $storeApiRegistry = new Registry();
        $storeApiRegistry->registerTool(new Tool('store-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $tester = new CommandTester($this->makeCommand($registry, storeApiRegistry: $storeApiRegistry));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Admin API (/api/_mcp)', $output);
        static::assertStringContainsString('admin-tool', $output);
        static::assertStringContainsString('Store API (/store-api/_mcp)', $output);
        static::assertStringContainsString('store-tool', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testSectionHeadingsNameTheirScope(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');

        $storeApiRegistry = new Registry();
        $storeApiRegistry->registerTool(new Tool('store-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $tester = new CommandTester($this->makeCommand($registry, storeApiRegistry: $storeApiRegistry));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Tools (1) [Admin API]', $output);
        static::assertStringContainsString('Tools (1) [Store API]', $output);
    }

    public function testAllowlistCountsStayOnTheAdminSectionHeading(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('tool-a', null, self::inputSchema(), null, null), 'Acme\\ToolA');
        $registry->registerTool(new Tool('tool-b', null, self::inputSchema(), null, null), 'Acme\\ToolB');

        $storeApiRegistry = new Registry();
        $storeApiRegistry->registerTool(new Tool('store-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: ['tool-a'], resources: null, prompts: null));

        $tester = new CommandTester($this->makeCommand($registry, $allowlistProvider, storeApiRegistry: $storeApiRegistry));
        $tester->execute(['--integration' => 'SWIA-restricted']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Tools (1/2 allowed) [Admin API]', $output);
        static::assertStringContainsString('Tools (1) [Store API]', $output);
    }

    public function testScopeOptionLimitsOutputToStoreApi(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');

        $storeApiRegistry = new Registry();
        $storeApiRegistry->registerTool(new Tool('store-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $tester = new CommandTester($this->makeCommand($registry, storeApiRegistry: $storeApiRegistry));
        $tester->execute(['--scope' => 'store-api']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('store-tool', $output);
        static::assertStringNotContainsString('admin-tool', $output);
        static::assertStringNotContainsString('Admin API (/api/_mcp)', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testScopeOptionLimitsOutputToAdminApi(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');

        $storeApiRegistry = new Registry();
        $storeApiRegistry->registerTool(new Tool('store-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $tester = new CommandTester($this->makeCommand($registry, storeApiRegistry: $storeApiRegistry));
        $tester->execute(['--scope' => 'api']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('admin-tool', $output);
        static::assertStringNotContainsString('store-tool', $output);
        static::assertStringNotContainsString('Store API (/store-api/_mcp)', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testUnknownScopeIsRejected(): void
    {
        $tester = new CommandTester($this->makeCommand(new Registry()));
        $tester->execute(['--scope' => 'nonsense']);

        static::assertSame(2, $tester->getStatusCode());
        static::assertStringContainsString('Invalid scope "nonsense"', $tester->getDisplay());
    }

    public function testStoreApiScopeIsSkippedWhenOnlyAdminIsAvailable(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('admin-tool', $output);
        static::assertStringNotContainsString('Store API (/store-api/_mcp)', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testDetailViewResolvesStoreApiCapabilityAndShowsItsScope(): void
    {
        $storeApiRegistry = new Registry();
        $storeApiRegistry->registerTool(
            new Tool('store-tool', null, self::inputSchema(), 'Runs in the sales channel context', null),
            'Acme\\StoreTool',
        );

        $tester = new CommandTester($this->makeCommand(new Registry(), storeApiRegistry: $storeApiRegistry));
        $tester->execute(['name' => 'store-tool']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Runs in the sales channel context', $output);
        static::assertStringContainsString('Store API (/store-api/_mcp)', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testIntegrationAllowlistDoesNotFilterStoreApiTools(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('admin-tool', null, self::inputSchema(), null, null), 'Acme\\AdminTool');
        $registry->registerTool(new Tool('admin-hidden', null, self::inputSchema(), null, null), 'Acme\\AdminHidden');

        $storeApiRegistry = new Registry();
        $storeApiRegistry->registerTool(new Tool('store-tool', null, self::inputSchema(), null, null), 'Acme\\StoreTool');

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: ['admin-tool'], resources: null, prompts: null));

        $tester = new CommandTester($this->makeCommand($registry, $allowlistProvider, storeApiRegistry: $storeApiRegistry));
        $tester->execute(['--integration' => 'SWIA-restricted']);

        $output = $tester->getDisplay();
        static::assertStringContainsString('only apply to the admin scope', $output);
        static::assertStringContainsString('admin-tool', $output);
        static::assertStringNotContainsString('admin-hidden', $output);
        static::assertStringContainsString('store-tool', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    /**
     * --native hands over to the MCP bundle's own command, which McpDebugCommandCompilerPass renamed
     * so both can keep their own output.
     */
    public function testNativeOptionRunsTheBundleCommand(): void
    {
        $native = new Command(McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME);
        $native->setCode(static function (InputInterface $input, OutputInterface $output): int {
            $output->writeln('native command ran');

            return Command::SUCCESS;
        });

        $application = new Application();
        $application->addCommand($native);
        $application->addCommand($this->makeCommand(new Registry()));

        $tester = new CommandTester($application->find('debug:mcp'));
        $tester->execute(['--native' => true]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('native command ran', $tester->getDisplay());
        static::assertStringNotContainsString('Admin API', $tester->getDisplay());
    }

    /**
     * A capability name given alongside --native is forwarded, so `debug:mcp <name> --native` shows
     * the bundle's detail view for that capability instead of its server list.
     */
    public function testNativeOptionForwardsTheCapabilityName(): void
    {
        $seen = null;
        $native = new Command(McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME);
        $native->addArgument('name', InputArgument::OPTIONAL);
        $native->setCode(static function (InputInterface $input, OutputInterface $output) use (&$seen): int {
            $seen = $input->getArgument('name');

            return Command::SUCCESS;
        });

        $application = new Application();
        $application->addCommand($native);
        $application->addCommand($this->makeCommand(new Registry()));

        $tester = new CommandTester($application->find('debug:mcp'));
        $tester->execute(['name' => 'shopware-entity-search', '--native' => true]);

        static::assertSame(0, $tester->getStatusCode());
        static::assertSame('shopware-entity-search', $seen);
    }

    public function testNativeOptionFailsWhenTheBundleCommandIsMissing(): void
    {
        $application = new Application();
        $application->addCommand($this->makeCommand(new Registry()));

        $tester = new CommandTester($application->find('debug:mcp'));
        $tester->execute(['--native' => true]);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString(McpDebugCommandCompilerPass::NATIVE_COMMAND_NAME, $tester->getDisplay());
    }

    public function testUnassignedCapabilitiesAreReported(): void
    {
        $command = new DebugMcpCommand(
            Server::builder(),
            new Registry(),
            static::createStub(McpAllowlistProvider::class),
            new McpCapabilityCatalog(null, $this->stubPrivilegeProvider()),
            unassigned: ['tools' => ['Acme\\OrphanTool'], 'prompts' => []],
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('exposed by no server', $output);
        static::assertStringContainsString('Acme\\OrphanTool', $output);
        static::assertStringContainsString('tools', $output);
    }

    public function testNothingIsReportedWhenEveryCapabilityIsAssigned(): void
    {
        $tester = new CommandTester($this->makeCommand(new Registry()));
        $tester->execute([]);

        static::assertStringNotContainsString('exposed by no server', $tester->getDisplay());
    }

    private function makeCommand(
        Registry $registry,
        ?McpAllowlistProvider $allowlistProvider = null,
        ?McpCapabilityCatalog $catalog = null,
        ?Registry $storeApiRegistry = null,
    ): DebugMcpCommand {
        $builder = Server::builder()->setRegistry($registry);

        if ($allowlistProvider === null) {
            $allowlistProvider = static::createStub(McpAllowlistProvider::class);
            $allowlistProvider->method('forAccessKey')->willReturn(new McpAllowlist(tools: null, resources: null, prompts: null));
        }

        $catalog ??= new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        if ($storeApiRegistry === null) {
            return new DebugMcpCommand($builder, $registry, $allowlistProvider, $catalog);
        }

        return new DebugMcpCommand(
            $builder,
            $registry,
            $allowlistProvider,
            $catalog,
            Server::builder()->setRegistry($storeApiRegistry),
            $storeApiRegistry,
            new McpCapabilityCatalog($storeApiRegistry, $this->stubPrivilegeProvider()),
        );
    }

    private function stubPrivilegeProvider(): AppMcpPrivilegeProvider
    {
        $stub = static::createStub(AppMcpPrivilegeProvider::class);
        $stub->method('getAppToolPrivileges')->willReturn([]);

        return $stub;
    }

    /**
     * @return array{type: 'object', properties: array<string, mixed>, required: array<string>|null}
     */
    private static function inputSchema(): array
    {
        return ['type' => 'object', 'properties' => [], 'required' => []];
    }
}
