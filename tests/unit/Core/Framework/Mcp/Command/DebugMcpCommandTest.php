<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Command;

use Mcp\Capability\Registry;
use Mcp\Schema\Prompt;
use Mcp\Schema\PromptArgument;
use Mcp\Schema\Resource;
use Mcp\Schema\ResourceTemplate;
use Mcp\Schema\Tool;
use Mcp\Server;
use Mcp\Server\Builder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Shopware\Core\Framework\Mcp\Command\DebugMcpCommand;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DebugMcpCommand::class)]
#[CoversClass(McpCapabilityCatalog::class)]
class DebugMcpCommandTest extends TestCase
{
    protected function setUp(): void
    {
        $_SERVER['MCP_SERVER'] = '1';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['MCP_SERVER']);
    }

    public function testExecuteReturnsErrorWhenFeatureFlagIsOff(): void
    {
        $_SERVER['MCP_SERVER'] = false;
        try {
            $tester = new CommandTester($this->makeCommand(new Registry()));
            $tester->execute([]);

            static::assertSame(1, $tester->getStatusCode());
            static::assertStringContainsString('MCP bundle is not installed', $tester->getDisplay());
        } finally {
            $_SERVER['MCP_SERVER'] = '1';
        }
    }

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
        static::assertStringContainsString('Prompts', $output);
        static::assertStringContainsString('Resources', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testEmptyRegistryShowsNoCapabilitiesMessages(): void
    {
        $command = $this->makeCommand(new Registry());
        $tester = new CommandTester($command);
        $tester->execute([]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('No tools registered', $output);
        static::assertStringContainsString('No prompts registered', $output);
        static::assertStringContainsString('No resources registered', $output);
    }

    public function testToolIsRenderedCompactInListWithoutDescription(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', null, self::inputSchema(), 'Does things', null),
            'Acme\\MyTool',
            true,
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
            true,
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
            true,
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
            true,
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        static::assertStringContainsString('My Human-Readable Tool', $tester->getDisplay());
    }

    public function testDetailViewOmitsTitleWhenNull(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', null, self::inputSchema(), 'Does things', null),
            'Acme\\MyTool',
            true,
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'my-tool']);

        static::assertStringNotContainsString('Title', $tester->getDisplay());
    }

    public function testDetailViewShowsToolDescriptionAndSource(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', null, self::inputSchema(), 'Does things for you', null),
            'Acme\\MyTool',
            true,
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
            true,
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
            true,
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
            new Resource('shopware://test', 'my-resource', 'A helpful resource', null, null, null),
            'Acme\\MyResource',
            true,
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
            new Resource('shopware://entities', 'entities', 'All entity types', null, null, null),
            'Acme\\EntitiesResource',
            true,
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['name' => 'shopware://entities']);

        static::assertSame(0, $tester->getStatusCode());
        static::assertStringContainsString('shopware://entities', $tester->getDisplay());
    }

    public function testToolsFilterShowsOnlyTools(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('my-tool', null, self::inputSchema(), 'Tool desc', null), 'Acme\\MyTool', true);
        $registry->registerPrompt(new Prompt('my-prompt', null, 'Prompt desc', []), 'Acme\\MyPrompt', [], true);
        $registry->registerResource(new Resource('shopware://test', 'my-resource', 'Resource desc', null, null, null), 'Acme\\MyResource', true);

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['--tools' => true]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Tools', $output);
        static::assertStringContainsString('my-tool', $output);
        static::assertStringNotContainsString('Prompts', $output);
        static::assertStringNotContainsString('Resources', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testPromptsFilterShowsOnlyPrompts(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('my-tool', null, self::inputSchema(), 'Tool desc', null), 'Acme\\MyTool', true);
        $registry->registerPrompt(new Prompt('my-prompt', null, 'Prompt desc', []), 'Acme\\MyPrompt', [], true);
        $registry->registerResource(new Resource('shopware://test', 'my-resource', 'Resource desc', null, null, null), 'Acme\\MyResource', true);

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['--prompts' => true]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Prompts', $output);
        static::assertStringContainsString('my-prompt', $output);
        static::assertStringNotContainsString('Tools', $output);
        static::assertStringNotContainsString('Resources', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testResourcesFilterShowsOnlyResources(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('my-tool', null, self::inputSchema(), 'Tool desc', null), 'Acme\\MyTool', true);
        $registry->registerPrompt(new Prompt('my-prompt', null, 'Prompt desc', []), 'Acme\\MyPrompt', [], true);
        $registry->registerResource(new Resource('shopware://test', 'my-resource', 'Resource desc', null, null, null), 'Acme\\MyResource', true);

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['--resources' => true]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('Resources', $output);
        static::assertStringContainsString('my-resource', $output);
        static::assertStringNotContainsString('Tools', $output);
        static::assertStringNotContainsString('Prompts', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testIntegrationOptionWithNullAllowlistShowsAllToolsAndNote(): void
    {
        $registry = new Registry();
        $registry->registerTool(new Tool('tool-a', null, self::inputSchema(), null, null), 'Acme\\ToolA', true);
        $registry->registerTool(new Tool('tool-b', null, self::inputSchema(), null, null), 'Acme\\ToolB', true);

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(['tools' => null, 'resources' => null, 'prompts' => null]);

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
        $registry->registerTool(new Tool('tool-a', null, self::inputSchema(), null, null), 'Acme\\ToolA', true);
        $registry->registerTool(new Tool('tool-b', null, self::inputSchema(), null, null), 'Acme\\ToolB', true);

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(['tools' => ['tool-a'], 'resources' => null, 'prompts' => null]);

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
        $registry->registerTool(new Tool('tool-a', null, self::inputSchema(), null, null), 'Acme\\ToolA', true);

        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forAccessKey')->willReturn(['tools' => [], 'resources' => null, 'prompts' => null]);

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
            true,
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
            true,
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
            true,
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
            new Resource('shopware://json', 'json-resource', 'JSON resource', 'application/json', null, null),
            'Acme\\JsonResource',
            true,
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
            true,
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

    public function testResourceTemplatesAreRendered(): void
    {
        $registry = new Registry();
        $registry->registerResourceTemplate(
            new ResourceTemplate('shopware://{entity}/{id}', 'entity-by-id', 'Get entity by ID'),
            'Acme\\EntityByIdTemplate',
            [],
            true,
        );

        $tester = new CommandTester($this->makeCommand($registry));
        $tester->execute(['--resources' => true]);

        $output = $tester->getDisplay();
        static::assertStringContainsString('entity-by-id', $output);
        static::assertStringContainsString('shopware://{entity}/{id}', $output);
        static::assertSame(0, $tester->getStatusCode());
    }

    public function testDetailViewReturnsFailureForUnknownName(): void
    {
        $tester = new CommandTester($this->makeCommand(new Registry()));
        $tester->execute(['name' => 'does-not-exist']);

        static::assertSame(1, $tester->getStatusCode());
        static::assertStringContainsString('No capability found with name \'does-not-exist\'', $tester->getDisplay());
    }

    private function makeCommand(
        Registry $registry,
        ?McpAllowlistProvider $allowlistProvider = null,
        ?McpCapabilityCatalog $catalog = null,
    ): DebugMcpCommand {
        $builder = Server::builder()->setRegistry($registry);

        if ($allowlistProvider === null) {
            $allowlistProvider = static::createStub(McpAllowlistProvider::class);
            $allowlistProvider->method('forAccessKey')->willReturn(['tools' => null, 'resources' => null, 'prompts' => null]);
        }

        $catalog ??= new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        return new DebugMcpCommand($builder, $registry, $allowlistProvider, $catalog);
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
