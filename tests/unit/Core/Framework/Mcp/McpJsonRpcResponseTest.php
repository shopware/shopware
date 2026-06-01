<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpJsonRpcResponse;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpJsonRpcResponse::class)]
class McpJsonRpcResponseTest extends TestCase
{
    // ── fromJson ─────────────────────────────────────────────────────────────

    public function testFromJsonReturnsNullForInvalidJson(): void
    {
        static::assertNull(McpJsonRpcResponse::fromJson('not-json'));
    }

    public function testFromJsonReturnsNullForJsonArray(): void
    {
        static::assertNull(McpJsonRpcResponse::fromJson('[]'));
    }

    public function testFromJsonReturnsNullForUnknownResultType(): void
    {
        static::assertNull(McpJsonRpcResponse::fromJson('{"id":1,"jsonrpc":"2.0","result":{"unknown":"value"}}'));
    }

    public function testFromJsonReturnsNullWhenResultIsNotArray(): void
    {
        static::assertNull(McpJsonRpcResponse::fromJson('{"id":1,"jsonrpc":"2.0","result":"not-an-object"}'));
    }

    public function testFromJsonParsesToolsListResponse(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->toolsListJson(['tool-a', 'tool-b']));

        static::assertNotNull($response);
    }

    public function testFromJsonParsesResourcesListResponse(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->resourcesListJson([
            ['uri' => 'shopware://entities', 'name' => 'shopware-entities'],
        ]));

        static::assertNotNull($response);
    }

    public function testFromJsonParsesPromptsListResponse(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->promptsListJson(['shopware-context']));

        static::assertNotNull($response);
    }

    public function testFromJsonParsesInitializeResponse(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->initializeJson());

        static::assertNotNull($response);
    }

    // ── filterTools ──────────────────────────────────────────────────────────

    public function testFilterToolsKeepsAllowedTools(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->toolsListJson(['tool-a', 'tool-b', 'tool-c']));
        static::assertNotNull($response);

        $response->filterTools(['tool-a', 'tool-c']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        $names = array_column($data['result']['tools'], 'name');
        static::assertSame(['tool-a', 'tool-c'], $names);
    }

    public function testFilterToolsWithEmptyAllowlistRemovesAll(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->toolsListJson(['tool-a']));
        static::assertNotNull($response);

        $response->filterTools([]);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([], $data['result']['tools']);
    }

    public function testFilterToolsReindexesArray(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->toolsListJson(['tool-a', 'tool-b']));
        static::assertNotNull($response);

        $response->filterTools(['tool-b']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey(0, $data['result']['tools']);
        static::assertSame('tool-b', $data['result']['tools'][0]['name']);
    }

    public function testFilterToolsIsNoOpForNonToolsResponse(): void
    {
        $json = $this->resourcesListJson([['uri' => 'shopware://entities', 'name' => 'shopware-entities']]);
        $response = McpJsonRpcResponse::fromJson($json);
        static::assertNotNull($response);

        $response->filterTools(['tool-a']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('tools', $data['result']);
    }

    public function testFilterToolsPreservesEmptyInputSchemaAsObject(): void
    {
        $json = (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'tools' => [
                    ['name' => 'tool-a', 'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()]],
                ],
            ],
        ]);

        $response = McpJsonRpcResponse::fromJson($json);
        static::assertNotNull($response);

        $response->filterTools(['tool-a']);

        static::assertStringContainsString('"properties":{}', $response->encode(), 'Empty properties must encode as {} not []');
    }

    // ── filterResources ───────────────────────────────────────────────────────

    public function testFilterResourcesKeepsAllowedResources(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->resourcesListJson([
            ['uri' => 'shopware://entities', 'name' => 'shopware-entities'],
            ['uri' => 'shopware://currencies', 'name' => 'shopware-currencies'],
            ['uri' => 'shopware://state-machines', 'name' => 'shopware-state-machines'],
        ]));
        static::assertNotNull($response);

        $response->filterResources(['shopware://entities', 'shopware://currencies']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        $uris = array_column($data['result']['resources'], 'uri');
        static::assertSame(['shopware://entities', 'shopware://currencies'], $uris);
    }

    public function testFilterResourcesWithEmptyAllowlistRemovesAll(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->resourcesListJson([
            ['uri' => 'shopware://entities', 'name' => 'shopware-entities'],
        ]));
        static::assertNotNull($response);

        $response->filterResources([]);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([], $data['result']['resources']);
    }

    public function testFilterResourcesReindexesArray(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->resourcesListJson([
            ['uri' => 'shopware://entities', 'name' => 'shopware-entities'],
            ['uri' => 'shopware://currencies', 'name' => 'shopware-currencies'],
        ]));
        static::assertNotNull($response);

        $response->filterResources(['shopware://currencies']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey(0, $data['result']['resources']);
        static::assertSame('shopware://currencies', $data['result']['resources'][0]['uri']);
    }

    // ── filterPrompts ─────────────────────────────────────────────────────────

    public function testFilterPromptsKeepsAllowedPrompts(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->promptsListJson(['shopware-context', 'shopware-developer']));
        static::assertNotNull($response);

        $response->filterPrompts(['shopware-context']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        $names = array_column($data['result']['prompts'], 'name');
        static::assertSame(['shopware-context'], $names);
    }

    public function testFilterPromptsWithEmptyAllowlistRemovesAll(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->promptsListJson(['shopware-context']));
        static::assertNotNull($response);

        $response->filterPrompts([]);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([], $data['result']['prompts']);
    }

    // ── addShopwareMeta ───────────────────────────────────────────────────────

    public function testAddShopwareMetaReturnsFalseWhenBothNull(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->initializeJson());
        static::assertNotNull($response);

        static::assertFalse($response->addShopwareMeta(null, null));
    }

    public function testAddShopwareMetaReturnsFalseForNonInitializeResponse(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->toolsListJson(['tool-a']));
        static::assertNotNull($response);

        static::assertFalse($response->addShopwareMeta('user-123', null));
    }

    public function testAddShopwareMetaAddsUserId(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->initializeJson());
        static::assertNotNull($response);

        $added = $response->addShopwareMeta('user-123', null);

        static::assertTrue($added);
        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('user-123', $data['result']['_meta']['shopware']['user']['id']);
        static::assertArrayNotHasKey('integration', $data['result']['_meta']['shopware']);
    }

    public function testAddShopwareMetaAddsIntegrationId(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->initializeJson());
        static::assertNotNull($response);

        $added = $response->addShopwareMeta(null, 'integration-456');

        static::assertTrue($added);
        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('integration-456', $data['result']['_meta']['shopware']['integration']['id']);
        static::assertArrayNotHasKey('user', $data['result']['_meta']['shopware']);
    }

    public function testAddShopwareMetaAddsBoth(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->initializeJson());
        static::assertNotNull($response);

        $response->addShopwareMeta('user-123', 'integration-456');

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('user-123', $data['result']['_meta']['shopware']['user']['id']);
        static::assertSame('integration-456', $data['result']['_meta']['shopware']['integration']['id']);
    }

    public function testAddShopwareMetaPreservesServerCapabilitiesAsObjects(): void
    {
        $response = McpJsonRpcResponse::fromJson($this->initializeJson());
        static::assertNotNull($response);

        $response->addShopwareMeta('user-123', null);

        $encoded = $response->encode();
        static::assertStringContainsString('"tools":{', $encoded, 'capabilities.tools must encode as {} not []');
        static::assertStringContainsString('"prompts":{', $encoded, 'capabilities.prompts must encode as {} not []');
        static::assertStringContainsString('"resources":{', $encoded, 'capabilities.resources must encode as {} not []');
    }

    // ── helpers ───────────────────────────────────────────────────────────────

    /**
     * @param list<string> $toolNames
     */
    private function toolsListJson(array $toolNames): string
    {
        $tools = array_map(
            static fn (string $name): array => [
                'name' => $name,
                'inputSchema' => ['type' => 'object', 'properties' => new \stdClass()],
            ],
            $toolNames,
        );

        return (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['tools' => $tools],
        ]);
    }

    /**
     * @param list<array{uri: string, name: string}> $resources
     */
    private function resourcesListJson(array $resources): string
    {
        return (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['resources' => $resources],
        ]);
    }

    /**
     * @param list<string> $promptNames
     */
    private function promptsListJson(array $promptNames): string
    {
        $prompts = array_map(
            static fn (string $name): array => ['name' => $name],
            $promptNames,
        );

        return (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['prompts' => $prompts],
        ]);
    }

    private function initializeJson(): string
    {
        return (string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => new \stdClass(),
                    'prompts' => new \stdClass(),
                    'resources' => new \stdClass(),
                ],
                'serverInfo' => ['name' => 'shopware', 'version' => '6.7.0'],
            ],
        ]);
    }
}
