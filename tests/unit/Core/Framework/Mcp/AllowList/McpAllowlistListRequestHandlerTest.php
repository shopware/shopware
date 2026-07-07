<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\AllowList;

use Mcp\Capability\Registry;
use Mcp\Capability\RegistryInterface;
use Mcp\Exception\InvalidCursorException;
use Mcp\Schema\ClientCapabilities;
use Mcp\Schema\Implementation;
use Mcp\Schema\Page;
use Mcp\Schema\Prompt;
use Mcp\Schema\Request\InitializeRequest;
use Mcp\Schema\Request\ListPromptsRequest;
use Mcp\Schema\Request\ListResourcesRequest;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\ResourceDefinition;
use Mcp\Schema\Result\ListPromptsResult;
use Mcp\Schema\Result\ListResourcesResult;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlist;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistListRequestHandler;
use Shopware\Core\Framework\Mcp\AllowList\McpAllowlistProvider;

/**
 * @internal
 */
#[CoversClass(McpAllowlistListRequestHandler::class)]
class McpAllowlistListRequestHandlerTest extends TestCase
{
    public function testSupportsOnlyListRequests(): void
    {
        $handler = $this->createHandler(new Registry(), new McpAllowlist(tools: null, resources: null, prompts: null));

        static::assertTrue($handler->supports(new ListToolsRequest()));
        static::assertTrue($handler->supports(new ListResourcesRequest()));
        static::assertTrue($handler->supports(new ListPromptsRequest()));
        static::assertFalse($handler->supports(new InitializeRequest('2025-03-26', new ClientCapabilities(), new Implementation('test', '1.0'))));
    }

    public function testToolsListFiltersAllowlistBeforePaginationAndReturnsCursorRemainder(): void
    {
        $registry = new Registry();
        foreach (['tool-a', 'tool-b', 'tool-c', 'tool-d', 'tool-e'] as $toolName) {
            $registry->registerTool($this->tool($toolName), static fn (): string => '');
        }

        $handler = $this->createHandler($registry, new McpAllowlist(
            tools: ['tool-b', 'tool-d', 'tool-e'],
            resources: null,
            prompts: null,
        ), ['tool-a', 'tool-b', 'tool-c', 'tool-d', 'tool-e']);

        $firstResult = $this->handleToolsList($handler, null);

        static::assertSame(['tool-b', 'tool-d'], array_map(static fn (Tool $tool): string => $tool->name, $firstResult->tools));
        static::assertSame(base64_encode('2'), $firstResult->nextCursor);

        $secondResult = $this->handleToolsList($handler, $firstResult->nextCursor);

        static::assertSame(['tool-e'], array_map(static fn (Tool $tool): string => $tool->name, $secondResult->tools));
        static::assertNull($secondResult->nextCursor);
    }

    public function testToolsListUsesAdvertisedToolsWhenToolAllowlistAllowsAllTools(): void
    {
        $registry = new Registry();
        foreach (['shopware-tool-search', 'shopware-entity-schema', 'shopware-entity-search', 'hidden-tool'] as $toolName) {
            $registry->registerTool($this->tool($toolName), static fn (): string => '');
        }

        $handler = $this->createHandler(
            $registry,
            new McpAllowlist(tools: null, resources: [], prompts: []),
            ['shopware-tool-search', 'shopware-entity-schema', 'shopware-entity-search'],
        );

        $firstResult = $this->handleToolsList($handler, null);

        static::assertSame(['shopware-tool-search', 'shopware-entity-schema'], array_map(static fn (Tool $tool): string => $tool->name, $firstResult->tools));
        static::assertSame(base64_encode('2'), $firstResult->nextCursor);

        $secondResult = $this->handleToolsList($handler, $firstResult->nextCursor);

        static::assertSame(['shopware-entity-search'], array_map(static fn (Tool $tool): string => $tool->name, $secondResult->tools));
        static::assertNull($secondResult->nextCursor);
    }

    public function testToolsListAlwaysAdvertisesToolSearch(): void
    {
        $registry = new Registry();
        foreach (['shopware-tool-search', 'shopware-entity-schema'] as $toolName) {
            $registry->registerTool($this->tool($toolName), static fn (): string => '');
        }

        $handler = $this->createHandler(
            $registry,
            new McpAllowlist(tools: null, resources: [], prompts: []),
            ['shopware-entity-schema'],
        );

        $result = $this->handleToolsList($handler, null);

        static::assertSame(['shopware-tool-search', 'shopware-entity-schema'], array_map(static fn (Tool $tool): string => $tool->name, $result->tools));
    }

    public function testToolsListWithEmptyToolAllowlistOnlyAdvertisesToolSearch(): void
    {
        $registry = new Registry();
        foreach (['shopware-tool-search', 'shopware-entity-schema'] as $toolName) {
            $registry->registerTool($this->tool($toolName), static fn (): string => '');
        }

        $handler = $this->createHandler(
            $registry,
            new McpAllowlist(tools: [], resources: [], prompts: []),
            ['shopware-tool-search', 'shopware-entity-schema'],
        );

        $result = $this->handleToolsList($handler, null);

        static::assertSame(['shopware-tool-search'], array_map(static fn (Tool $tool): string => $tool->name, $result->tools));
    }

    public function testToolsListIgnoresNonToolReferences(): void
    {
        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getTools')->willReturn(new Page([
            $this->tool('tool-a'),
            $this->resource('resource-a'),
        ], null));

        $handler = $this->createHandler($registry, new McpAllowlist(tools: null, resources: [], prompts: []), ['tool-a']);

        $result = $this->handleToolsList($handler, null);

        static::assertSame(['tool-a'], array_map(static fn (Tool $tool): string => $tool->name, $result->tools));
    }

    public function testResourcesListFiltersAllowlistBeforePaginationAndReturnsCursorRemainder(): void
    {
        $registry = new Registry();
        foreach (['resource-a', 'resource-b', 'resource-c', 'resource-d', 'resource-e'] as $resourceName) {
            $registry->registerResource($this->resource($resourceName), static fn (): string => '');
        }

        $handler = $this->createHandler($registry, new McpAllowlist(
            tools: null,
            resources: ['shopware://resource-b', 'shopware://resource-d', 'shopware://resource-e'],
            prompts: null,
        ));

        $firstResult = $this->handleResourcesList($handler, null);

        static::assertSame(
            ['shopware://resource-b', 'shopware://resource-d'],
            array_map(static fn (ResourceDefinition $resource): string => $resource->uri, $firstResult->resources),
        );
        static::assertSame(base64_encode('2'), $firstResult->nextCursor);

        $secondResult = $this->handleResourcesList($handler, $firstResult->nextCursor);

        static::assertSame(
            ['shopware://resource-e'],
            array_map(static fn (ResourceDefinition $resource): string => $resource->uri, $secondResult->resources),
        );
        static::assertNull($secondResult->nextCursor);
    }

    public function testResourcesListUsesRegistryPaginationWhenResourceAllowlistAllowsAllResources(): void
    {
        $registry = new Registry();
        foreach (['resource-a', 'resource-b', 'resource-c'] as $resourceName) {
            $registry->registerResource($this->resource($resourceName), static fn (): string => '');
        }

        $handler = $this->createHandler($registry, new McpAllowlist(tools: [], resources: null, prompts: []));

        $firstResult = $this->handleResourcesList($handler, null);

        static::assertSame(
            ['shopware://resource-a', 'shopware://resource-b'],
            array_map(static fn (ResourceDefinition $resource): string => $resource->uri, $firstResult->resources),
        );
        static::assertSame(base64_encode('2'), $firstResult->nextCursor);

        $secondResult = $this->handleResourcesList($handler, $firstResult->nextCursor);

        static::assertSame(
            ['shopware://resource-c'],
            array_map(static fn (ResourceDefinition $resource): string => $resource->uri, $secondResult->resources),
        );
        static::assertNull($secondResult->nextCursor);
    }

    public function testResourcesListIgnoresNonResourceReferences(): void
    {
        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getResources')->willReturn(new Page([
            $this->resource('resource-a'),
            $this->tool('tool-a'),
        ], null));

        $handler = $this->createHandler($registry, new McpAllowlist(tools: [], resources: null, prompts: []));

        $result = $this->handleResourcesList($handler, null);

        static::assertSame(['shopware://resource-a'], array_map(static fn (ResourceDefinition $resource): string => $resource->uri, $result->resources));
    }

    public function testPromptsListFiltersAllowlistBeforePaginationAndReturnsCursorRemainder(): void
    {
        $registry = new Registry();
        foreach (['prompt-a', 'prompt-b', 'prompt-c', 'prompt-d', 'prompt-e'] as $promptName) {
            $registry->registerPrompt(new Prompt($promptName), static fn (): array => []);
        }

        $handler = $this->createHandler($registry, new McpAllowlist(
            tools: null,
            resources: null,
            prompts: ['prompt-b', 'prompt-d', 'prompt-e'],
        ));

        $firstResult = $this->handlePromptsList($handler, null);

        static::assertSame(['prompt-b', 'prompt-d'], array_map(static fn (Prompt $prompt): string => $prompt->name, $firstResult->prompts));
        static::assertSame(base64_encode('2'), $firstResult->nextCursor);

        $secondResult = $this->handlePromptsList($handler, $firstResult->nextCursor);

        static::assertSame(['prompt-e'], array_map(static fn (Prompt $prompt): string => $prompt->name, $secondResult->prompts));
        static::assertNull($secondResult->nextCursor);
    }

    public function testPromptsListUsesRegistryPaginationWhenPromptAllowlistAllowsAllPrompts(): void
    {
        $registry = new Registry();
        foreach (['prompt-a', 'prompt-b', 'prompt-c'] as $promptName) {
            $registry->registerPrompt(new Prompt($promptName), static fn (): array => []);
        }

        $handler = $this->createHandler($registry, new McpAllowlist(tools: [], resources: [], prompts: null));

        $firstResult = $this->handlePromptsList($handler, null);

        static::assertSame(['prompt-a', 'prompt-b'], array_map(static fn (Prompt $prompt): string => $prompt->name, $firstResult->prompts));
        static::assertSame(base64_encode('2'), $firstResult->nextCursor);

        $secondResult = $this->handlePromptsList($handler, $firstResult->nextCursor);

        static::assertSame(['prompt-c'], array_map(static fn (Prompt $prompt): string => $prompt->name, $secondResult->prompts));
        static::assertNull($secondResult->nextCursor);
    }

    public function testPromptsListIgnoresNonPromptReferences(): void
    {
        $registry = $this->createMock(RegistryInterface::class);
        $registry->method('getPrompts')->willReturn(new Page([
            new Prompt('prompt-a'),
            $this->tool('tool-a'),
        ], null));

        $handler = $this->createHandler($registry, new McpAllowlist(tools: [], resources: [], prompts: null));

        $result = $this->handlePromptsList($handler, null);

        static::assertSame(['prompt-a'], array_map(static fn (Prompt $prompt): string => $prompt->name, $result->prompts));
    }

    public function testListWithMalformedCursorThrowsInvalidCursorException(): void
    {
        $registry = new Registry();
        $registry->registerTool($this->tool('tool-a'), static fn (): string => '');

        $handler = $this->createHandler($registry, new McpAllowlist(tools: ['tool-a'], resources: null, prompts: null), ['tool-a']);

        $this->expectException(InvalidCursorException::class);

        $this->handleToolsList($handler, 'not-base64');
    }

    public function testListWithOutOfRangeCursorThrowsInvalidCursorException(): void
    {
        $registry = new Registry();
        $registry->registerTool($this->tool('tool-a'), static fn (): string => '');

        $handler = $this->createHandler($registry, new McpAllowlist(tools: ['tool-a'], resources: null, prompts: null), ['tool-a']);

        $this->expectException(InvalidCursorException::class);

        $this->handleToolsList($handler, base64_encode('2'));
    }

    /**
     * @param list<string> $advertisedTools
     */
    private function createHandler(RegistryInterface $registry, McpAllowlist $allowlist, array $advertisedTools = ['shopware-tool-search', 'tool-a', 'tool-b', 'tool-c', 'tool-d', 'tool-e']): McpAllowlistListRequestHandler
    {
        $allowlistProvider = static::createStub(McpAllowlistProvider::class);
        $allowlistProvider->method('forCurrentRequest')->willReturn($allowlist);

        return new McpAllowlistListRequestHandler($registry, $allowlistProvider, 2, $advertisedTools);
    }

    private function handleToolsList(McpAllowlistListRequestHandler $handler, ?string $cursor): ListToolsResult
    {
        $response = $handler->handle((new ListToolsRequest($cursor))->withId(1), static::createStub(SessionInterface::class));
        static::assertInstanceOf(ListToolsResult::class, $response->result);

        return $response->result;
    }

    private function handleResourcesList(McpAllowlistListRequestHandler $handler, ?string $cursor): ListResourcesResult
    {
        $response = $handler->handle((new ListResourcesRequest($cursor))->withId(1), static::createStub(SessionInterface::class));
        static::assertInstanceOf(ListResourcesResult::class, $response->result);

        return $response->result;
    }

    private function handlePromptsList(McpAllowlistListRequestHandler $handler, ?string $cursor): ListPromptsResult
    {
        $response = $handler->handle((new ListPromptsRequest($cursor))->withId(1), static::createStub(SessionInterface::class));
        static::assertInstanceOf(ListPromptsResult::class, $response->result);

        return $response->result;
    }

    private function tool(string $name): Tool
    {
        return new Tool(
            name: $name,
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => null],
            description: null,
            annotations: null,
        );
    }

    private function resource(string $name): ResourceDefinition
    {
        return new ResourceDefinition(
            uri: 'shopware://' . $name,
            name: $name,
        );
    }
}
