<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(McpToolResponse::class)]
class McpToolResponseTest extends TestCase
{
    public function testTinyPayloadIsReturnedInlineWithoutResponseSizeHint(): void
    {
        $tool = new TestTool();

        $json = $tool->callSuccess(['items' => ['a', 'b']]);
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame(['items' => ['a', 'b']], $data['data']);
        static::assertArrayNotHasKey('_meta', $data);
    }

    public function testMidSizedPayloadIncludesResponseSizeHint(): void
    {
        $tool = new TestTool();

        $payload = ['items' => array_fill(0, 1000, str_repeat('x', 30))];
        $json = $tool->callSuccess($payload);

        static::assertGreaterThanOrEqual(20_000, \strlen($json));
        static::assertLessThanOrEqual(100_000, \strlen($json));

        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertArrayHasKey('_meta', $data);
        static::assertArrayHasKey('responseSize', $data['_meta']);
        static::assertGreaterThanOrEqual(20_000, $data['_meta']['responseSize']);
    }

    public function testOversizedPayloadWithSessionIsCachedAndReturnsResourceUri(): void
    {
        $cache = $this->createMock(ToolResultCacheStorage::class);
        $cache->expects($this->once())
            ->method('store')
            ->with('session-abc', static::isString())
            ->willReturn('cached-uuid-123');

        $request = new Request();
        $request->headers->set('Mcp-Session-Id', 'session-abc');

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $tool = new TestTool();
        $tool->setToolResultCache($cache, $requestStack, new NullLogger());

        $payload = ['items' => array_fill(0, 5_000, str_repeat('x', 30))];
        $json = $tool->callSuccess($payload);
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertNull($data['data']);
        static::assertSame('shopware://tool-result/cached-uuid-123', $data['_meta']['resourceUri']);
        static::assertGreaterThan(100_000, $data['_meta']['responseSize']);
        static::assertArrayHasKey('note', $data['_meta']);
    }

    public function testOversizedPayloadIncludesQueryWhenJsonRpcBodyIsPresent(): void
    {
        $cache = $this->createMock(ToolResultCacheStorage::class);
        $cache->method('store')->willReturn('cached-uuid');

        $request = new Request();
        $request->headers->set('Mcp-Session-Id', 'session-abc');
        $request->attributes->set(McpServerController::ATTRIBUTE_JSONRPC_BODY, [
            'method' => 'tools/call',
            'params' => [
                'name' => 'shopware-entity-search',
                'arguments' => ['entity' => 'product'],
            ],
        ]);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $tool = new TestTool();
        $tool->setToolResultCache($cache, $requestStack, new NullLogger());

        $json = $tool->callSuccess(['items' => array_fill(0, 5_000, str_repeat('x', 30))]);
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('shopware-entity-search', $data['_meta']['query']['tool']);
        static::assertSame(['entity' => 'product'], $data['_meta']['query']['arguments']);
    }

    public function testOversizedPayloadWithoutSessionFallsBackToInline(): void
    {
        $cache = $this->createMock(ToolResultCacheStorage::class);
        $cache->expects($this->never())->method('store');

        $requestStack = new RequestStack();

        $tool = new TestTool();
        $tool->setToolResultCache($cache, $requestStack, new NullLogger());

        $json = $tool->callSuccess(['items' => array_fill(0, 5_000, str_repeat('x', 30))]);
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertIsArray($data['data']['items']);
        static::assertArrayNotHasKey('resourceUri', $data['_meta'] ?? []);
    }
}

/**
 * @internal
 */
class TestTool extends McpToolResponse
{
    /**
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $meta
     */
    public function callSuccess(array $data, array $meta = []): string
    {
        return $this->success($data, $meta);
    }
}
