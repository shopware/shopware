<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Mcp\Capability\Registry;
use Mcp\Schema\Prompt;
use Mcp\Schema\Resource;
use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Shopware\Core\Framework\Mcp\McpCapabilityCatalog;

/**
 * @internal
 */
#[CoversClass(McpCapabilityCatalog::class)]
class McpCapabilityCatalogTest extends TestCase
{
    public function testEnrichedToolsReturnsEmptyListWhenNoToolsRegistered(): void
    {
        $catalog = new McpCapabilityCatalog(new Registry(), $this->stubPrivilegeProvider());

        static::assertSame([], $catalog->enrichedTools());
        static::assertSame(0, $catalog->totalToolCount());
    }

    public function testEnrichedToolsReturnsEntriesSortedByName(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'zeta-tool', 'Zeta');
        $this->registerTool($registry, 'alpha-tool', 'Alpha');
        $this->registerTool($registry, 'middle-tool', 'Middle');

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        $names = array_column($catalog->enrichedTools(), 'name');
        static::assertSame(['alpha-tool', 'middle-tool', 'zeta-tool'], $names);
    }

    public function testEnrichedToolsAttachesDependenciesAndCorePrivileges(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'shopware-entity-delete', 'Delete');

        $catalog = new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            ['shopware-entity-delete' => ['shopware-entity-search', 'shopware-entity-schema']],
            ['shopware-entity-delete' => ['static' => [], 'entityParam' => 'entity', 'operations' => ['delete']]],
        );

        $tools = $catalog->enrichedTools();

        static::assertCount(1, $tools);
        static::assertSame(
            ['shopware-entity-search', 'shopware-entity-schema'],
            $tools[0]['dependencies'],
        );
        static::assertSame(
            ['static' => [], 'entityParam' => 'entity', 'operations' => ['delete']],
            $tools[0]['requiredPrivileges'],
        );
    }

    public function testEnrichedToolsFallsBackToAppPrivilegesWhenNoCorePrivilegesDeclared(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'my-erp-sync-orders', 'Sync orders');

        $catalog = new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(['my-erp-sync-orders' => ['order:read', 'order:update']]),
        );

        $tools = $catalog->enrichedTools();

        static::assertSame(
            ['static' => ['order:read', 'order:update'], 'entityParam' => null, 'operations' => []],
            $tools[0]['requiredPrivileges'],
        );
    }

    public function testEnrichedToolsReturnsNullPrivilegesWhenNoneDeclared(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'no-privs-tool', null);

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        $tools = $catalog->enrichedTools();

        static::assertNull($tools[0]['requiredPrivileges']);
        static::assertSame([], $tools[0]['dependencies']);
    }

    public function testEnrichedToolsAppliesAllowlistFilter(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', 'A');
        $this->registerTool($registry, 'tool-b', 'B');
        $this->registerTool($registry, 'tool-c', 'C');

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        $names = array_column($catalog->enrichedTools(['tool-a', 'tool-c']), 'name');
        static::assertSame(['tool-a', 'tool-c'], $names);
    }

    public function testEnrichedToolsWithEmptyAllowlistReturnsNothing(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', 'A');

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        static::assertSame([], $catalog->enrichedTools([]));
    }

    public function testFindToolReturnsNullForUnknownName(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', 'A');

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        static::assertNull($catalog->findTool('does-not-exist'));
    }

    public function testFindToolReturnsEnrichedEntry(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', 'A description');

        $catalog = new McpCapabilityCatalog(
            $registry,
            $this->stubPrivilegeProvider(),
            ['tool-a' => ['dep-1']],
        );

        $entry = $catalog->findTool('tool-a');

        static::assertNotNull($entry);
        static::assertSame('tool-a', $entry['name']);
        static::assertSame('A description', $entry['description']);
        static::assertSame(['dep-1'], $entry['dependencies']);
    }

    public function testEnrichedResourcesReturnsSortedList(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new Resource('shopware://zzz', 'zzz-resource', 'Z Resource', null, null, null),
            'Acme\\ZzzResource',
            true,
        );
        $registry->registerResource(
            new Resource('shopware://aaa', 'aaa-resource', 'A Resource', null, null, null),
            'Acme\\AaaResource',
            true,
        );

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        $resources = $catalog->enrichedResources();

        static::assertCount(2, $resources);
        static::assertSame('shopware://aaa', $resources[0]['uri']);
        static::assertSame('shopware://zzz', $resources[1]['uri']);
    }

    public function testEnrichedResourcesAppliesAllowlistFilter(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new Resource('shopware://aaa', 'aaa-resource', 'A', null, null, null),
            'Acme\\AaaResource',
            true,
        );
        $registry->registerResource(
            new Resource('shopware://bbb', 'bbb-resource', 'B', null, null, null),
            'Acme\\BbbResource',
            true,
        );

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        $resources = $catalog->enrichedResources(['shopware://aaa']);

        static::assertCount(1, $resources);
        static::assertSame('shopware://aaa', $resources[0]['uri']);
    }

    public function testEnrichedResourcesWithEmptyAllowlistReturnsNothing(): void
    {
        $registry = new Registry();
        $registry->registerResource(
            new Resource('shopware://aaa', 'aaa-resource', 'A', null, null, null),
            'Acme\\AaaResource',
            true,
        );

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        static::assertSame([], $catalog->enrichedResources([]));
    }

    public function testEnrichedPromptsReturnsSortedList(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('zzz-prompt', null, 'Z prompt', []),
            'Acme\\ZzzPrompt',
            [],
            true,
        );
        $registry->registerPrompt(
            new Prompt('aaa-prompt', null, 'A prompt', []),
            'Acme\\AaaPrompt',
            [],
            true,
        );

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        $prompts = $catalog->enrichedPrompts();

        static::assertCount(2, $prompts);
        static::assertSame('aaa-prompt', $prompts[0]['name']);
        static::assertSame('zzz-prompt', $prompts[1]['name']);
    }

    public function testEnrichedPromptsAppliesAllowlistFilter(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('prompt-a', null, 'A', []),
            'Acme\\PromptA',
            [],
            true,
        );
        $registry->registerPrompt(
            new Prompt('prompt-b', null, 'B', []),
            'Acme\\PromptB',
            [],
            true,
        );

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        $prompts = $catalog->enrichedPrompts(['prompt-a']);

        static::assertCount(1, $prompts);
        static::assertSame('prompt-a', $prompts[0]['name']);
    }

    public function testEnrichedPromptsWithEmptyAllowlistReturnsNothing(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('prompt-a', null, 'A', []),
            'Acme\\PromptA',
            [],
            true,
        );

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        static::assertSame([], $catalog->enrichedPrompts([]));
    }

    public function testEnrichedToolsIncludesTitle(): void
    {
        $registry = new Registry();
        $registry->registerTool(
            new Tool('my-tool', 'My Human-Readable Tool', ['type' => 'object', 'properties' => [], 'required' => []], 'desc', null),
            'Acme\\MyTool',
            true,
        );

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());
        $tools = $catalog->enrichedTools();

        static::assertSame('My Human-Readable Tool', $tools[0]['title']);
    }

    public function testEnrichedToolsIncludesNullTitleWhenNotSet(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', 'desc');

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        static::assertNull($catalog->enrichedTools()[0]['title']);
    }

    public function testEnrichedPromptsIncludesTitle(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('my-prompt', 'My Human-Readable Prompt', 'desc', []),
            'Acme\\MyPrompt',
            [],
            true,
        );

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());
        $prompts = $catalog->enrichedPrompts();

        static::assertSame('My Human-Readable Prompt', $prompts[0]['title']);
    }

    public function testEnrichedPromptsIncludesNullTitleWhenNotSet(): void
    {
        $registry = new Registry();
        $registry->registerPrompt(
            new Prompt('prompt-a', null, 'A', []),
            'Acme\\PromptA',
            [],
            true,
        );

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        static::assertNull($catalog->enrichedPrompts()[0]['title']);
    }

    public function testTotalToolCountReportsRegistrySize(): void
    {
        $registry = new Registry();
        $this->registerTool($registry, 'tool-a', null);
        $this->registerTool($registry, 'tool-b', null);

        $catalog = new McpCapabilityCatalog($registry, $this->stubPrivilegeProvider());

        static::assertSame(2, $catalog->totalToolCount());
    }

    public function testAllMethodsReturnEmptyOrNullWhenRegistryIsNull(): void
    {
        $catalog = new McpCapabilityCatalog(null, $this->stubPrivilegeProvider());

        static::assertSame([], $catalog->enrichedTools());
        static::assertSame([], $catalog->enrichedResources());
        static::assertSame([], $catalog->enrichedPrompts());
        static::assertNull($catalog->findTool('any-tool'));
        static::assertSame(0, $catalog->totalToolCount());
    }

    /**
     * @param array<string, list<string>> $appPrivileges
     */
    private function stubPrivilegeProvider(array $appPrivileges = []): AppMcpPrivilegeProvider
    {
        $stub = static::createStub(AppMcpPrivilegeProvider::class);
        $stub->method('getAppToolPrivileges')->willReturn($appPrivileges);

        return $stub;
    }

    private function registerTool(Registry $registry, string $name, ?string $description): void
    {
        $registry->registerTool(
            new Tool($name, null, ['type' => 'object', 'properties' => [], 'required' => []], $description, null),
            'Acme\\' . str_replace('-', '', ucwords($name, '-')),
            true,
        );
    }
}
