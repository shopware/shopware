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

    public function testFromJsonReturnsNullForJsonScalar(): void
    {
        static::assertNull(McpJsonRpcResponse::fromJson('"string"'));
    }

    public function testFromJsonReturnsInstanceForJsonObject(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"jsonrpc":"2.0","id":1,"result":{}}');

        static::assertInstanceOf(McpJsonRpcResponse::class, $response);
    }

    // ── filterTools ──────────────────────────────────────────────────────────

    public function testFilterToolsKeepsAllowedTools(): void
    {
        $response = McpJsonRpcResponse::fromJson((string) json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => [
                'tools' => [
                    ['name' => 'tool-a', 'description' => 'A'],
                    ['name' => 'tool-b', 'description' => 'B'],
                    ['name' => 'tool-c', 'description' => 'C'],
                ],
            ],
        ]));
        static::assertNotNull($response);

        $response->filterTools(['tool-a', 'tool-c']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        $names = array_column($data['result']['tools'], 'name');
        static::assertSame(['tool-a', 'tool-c'], $names);
    }

    public function testFilterToolsWithEmptyAllowlistRemovesAll(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{"tools":[{"name":"tool-a"}]}}');
        static::assertNotNull($response);

        $response->filterTools([]);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([], $data['result']['tools']);
    }

    public function testFilterToolsReindexesArray(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{"tools":[{"name":"tool-a"},{"name":"tool-b"}]}}');
        static::assertNotNull($response);

        $response->filterTools(['tool-b']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey(0, $data['result']['tools']);
        static::assertSame('tool-b', $data['result']['tools'][0]['name']);
    }

    public function testFilterToolsIsNoOpWhenNoToolsKey(): void
    {
        $json = '{"result":{"nextCursor":null}}';
        $response = McpJsonRpcResponse::fromJson($json);
        static::assertNotNull($response);

        $response->filterTools(['tool-a']);

        static::assertSame($json, $response->encode());
    }

    public function testFilterToolsIsNoOpWhenToolsIsNotArray(): void
    {
        $json = '{"result":{"tools":"invalid"}}';
        $response = McpJsonRpcResponse::fromJson($json);
        static::assertNotNull($response);

        $response->filterTools(['tool-a']);

        static::assertSame($json, $response->encode());
    }

    public function testFilterToolsIsNoOpWhenResultIsNotObject(): void
    {
        $json = '{"result":"not-an-object"}';
        $response = McpJsonRpcResponse::fromJson($json);
        static::assertNotNull($response);

        $response->filterTools(['tool-a']);

        static::assertSame($json, $response->encode());
    }

    public function testFilterToolsPreservesEmptyObjectsInInputSchema(): void
    {
        $json = '{"result":{"tools":[{"name":"tool-a","inputSchema":{"type":"object","properties":{}}}]}}';
        $response = McpJsonRpcResponse::fromJson($json);
        static::assertNotNull($response);

        $response->filterTools(['tool-a']);

        static::assertStringContainsString('"properties":{}', $response->encode(), 'Empty properties must encode as {} not []');
    }

    // ── filterResources ───────────────────────────────────────────────────────

    public function testFilterResourcesKeepsAllowedResources(): void
    {
        $response = McpJsonRpcResponse::fromJson((string) json_encode([
            'result' => [
                'resources' => [
                    ['uri' => 'shopware://entities', 'name' => 'Entities'],
                    ['uri' => 'shopware://currencies', 'name' => 'Currencies'],
                    ['uri' => 'shopware://state-machines', 'name' => 'State Machines'],
                ],
            ],
        ]));
        static::assertNotNull($response);

        $response->filterResources(['shopware://entities', 'shopware://currencies']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        $uris = array_column($data['result']['resources'], 'uri');
        static::assertSame(['shopware://entities', 'shopware://currencies'], $uris);
    }

    public function testFilterResourcesWithEmptyAllowlistRemovesAll(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{"resources":[{"uri":"shopware://entities"}]}}');
        static::assertNotNull($response);

        $response->filterResources([]);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([], $data['result']['resources']);
    }

    public function testFilterResourcesReindexesArray(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{"resources":[{"uri":"shopware://entities"},{"uri":"shopware://currencies"}]}}');
        static::assertNotNull($response);

        $response->filterResources(['shopware://currencies']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey(0, $data['result']['resources']);
        static::assertSame('shopware://currencies', $data['result']['resources'][0]['uri']);
    }

    public function testFilterResourcesIsNoOpWhenNoResourcesKey(): void
    {
        $json = '{"result":{"nextCursor":null}}';
        $response = McpJsonRpcResponse::fromJson($json);
        static::assertNotNull($response);

        $response->filterResources(['shopware://entities']);

        static::assertSame($json, $response->encode());
    }

    // ── filterPrompts ─────────────────────────────────────────────────────────

    public function testFilterPromptsKeepsAllowedPrompts(): void
    {
        $response = McpJsonRpcResponse::fromJson((string) json_encode([
            'result' => [
                'prompts' => [
                    ['name' => 'shopware-context', 'description' => 'Context'],
                    ['name' => 'shopware-developer', 'description' => 'Dev'],
                ],
            ],
        ]));
        static::assertNotNull($response);

        $response->filterPrompts(['shopware-context']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        $names = array_column($data['result']['prompts'], 'name');
        static::assertSame(['shopware-context'], $names);
    }

    public function testFilterPromptsWithEmptyAllowlistRemovesAll(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{"prompts":[{"name":"shopware-context"}]}}');
        static::assertNotNull($response);

        $response->filterPrompts([]);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame([], $data['result']['prompts']);
    }

    public function testFilterPromptsReindexesArray(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{"prompts":[{"name":"shopware-context"},{"name":"shopware-developer"}]}}');
        static::assertNotNull($response);

        $response->filterPrompts(['shopware-developer']);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey(0, $data['result']['prompts']);
        static::assertSame('shopware-developer', $data['result']['prompts'][0]['name']);
    }

    public function testFilterPromptsIsNoOpWhenNoPromptsKey(): void
    {
        $json = '{"result":{"nextCursor":null}}';
        $response = McpJsonRpcResponse::fromJson($json);
        static::assertNotNull($response);

        $response->filterPrompts(['shopware-context']);

        static::assertSame($json, $response->encode());
    }

    // ── addShopwareMeta ───────────────────────────────────────────────────────

    public function testAddShopwareMetaReturnsFalseWhenBothNull(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{}}');
        static::assertNotNull($response);

        static::assertFalse($response->addShopwareMeta(null, null));
    }

    public function testAddShopwareMetaAddsUserId(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{}}');
        static::assertNotNull($response);

        $added = $response->addShopwareMeta('user-123', null);

        static::assertTrue($added);
        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('user-123', $data['result']['_meta']['shopware']['user']['id']);
        static::assertArrayNotHasKey('integration', $data['result']['_meta']['shopware']);
    }

    public function testAddShopwareMetaAddsIntegrationId(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{}}');
        static::assertNotNull($response);

        $added = $response->addShopwareMeta(null, 'integration-456');

        static::assertTrue($added);
        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('integration-456', $data['result']['_meta']['shopware']['integration']['id']);
        static::assertArrayNotHasKey('user', $data['result']['_meta']['shopware']);
    }

    public function testAddShopwareMetaAddsBoth(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{}}');
        static::assertNotNull($response);

        $response->addShopwareMeta('user-123', 'integration-456');

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('user-123', $data['result']['_meta']['shopware']['user']['id']);
        static::assertSame('integration-456', $data['result']['_meta']['shopware']['integration']['id']);
    }

    public function testAddShopwareMetaMergesWithExistingMeta(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{"_meta":{"other":"value"}}}');
        static::assertNotNull($response);

        $response->addShopwareMeta('user-123', null);

        $data = json_decode($response->encode(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('value', $data['result']['_meta']['other']);
        static::assertSame('user-123', $data['result']['_meta']['shopware']['user']['id']);
    }

    public function testAddShopwareMetaReturnsFalseWhenResultIsNotObject(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":"not-an-object"}');
        static::assertNotNull($response);

        static::assertFalse($response->addShopwareMeta('user-123', null));
    }

    public function testAddShopwareMetaPreservesEmptyObjectsInResult(): void
    {
        $response = McpJsonRpcResponse::fromJson('{"result":{"capabilities":{}}}');
        static::assertNotNull($response);

        $response->addShopwareMeta('user-123', null);

        static::assertStringContainsString('"capabilities":{}', $response->encode(), 'Empty capabilities must encode as {} not []');
    }
}
