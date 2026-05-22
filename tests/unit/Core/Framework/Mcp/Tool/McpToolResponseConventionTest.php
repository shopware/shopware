<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResponse::class)]
class McpToolResponseConventionTest extends TestCase
{
    public function testSuccessWithoutMetaOmitsMetaKey(): void
    {
        $helper = new McpToolResponseTestHelper();
        $result = json_decode($helper->callSuccess(['key' => 'value']), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame(['key' => 'value'], $result['data']);
        static::assertArrayNotHasKey('_meta', $result);
    }

    public function testSuccessWithMetaIncludesMetaKey(): void
    {
        $helper = new McpToolResponseTestHelper();
        $result = json_decode($helper->callSuccess(['x' => 1], ['total' => 5]), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(5, $result['_meta']['total']);
    }

    public function testErrorReturnsCorrectStructure(): void
    {
        $helper = new McpToolResponseTestHelper();
        $result = json_decode($helper->callError('Something broke'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertSame('Something broke', $result['error']);
        static::assertArrayNotHasKey('data', $result);
    }

    public function testDecodeJsonOrErrorReturnsArrayOnValidInput(): void
    {
        $helper = new McpToolResponseTestHelper();
        $result = $helper->callDecodeJsonOrError('{"key":"value"}', 'criteria');

        static::assertIsArray($result);
        static::assertSame(['key' => 'value'], $result);
    }

    public function testDecodeJsonOrErrorReturnsErrorJsonOnMalformedInput(): void
    {
        $helper = new McpToolResponseTestHelper();
        $result = $helper->callDecodeJsonOrError('not-json', 'criteria');

        static::assertIsString($result);
        $decoded = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($decoded['success']);
        static::assertStringContainsString('criteria', $decoded['error']);
        static::assertStringContainsString('Invalid JSON', $decoded['error']);
    }

    public function testDecodeJsonOrErrorReturnsErrorJsonWhenNotAnArray(): void
    {
        $helper = new McpToolResponseTestHelper();
        $result = $helper->callDecodeJsonOrError('"just-a-string"', 'payload');

        static::assertIsString($result);
        $decoded = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($decoded['success']);
        static::assertStringContainsString('payload', $decoded['error']);
    }

    public function testDryRunReturnsErrorOnRollbackFailure(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('rollBack')->willThrowException(new \RuntimeException('rollback failed'));

        $context = new Context(new AdminApiSource(null, null), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $helper = new McpToolResponseTestHelper();
        $result = json_decode($helper->callDryRun($connection, $context, fn () => '{"success":true}'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($result['success']);
        static::assertStringContainsString('Dry-run rollback failed', $result['error']);
        static::assertStringContainsString('rollback failed', $result['error']);
    }

    public function testDryRunReturnsErrorWhenOperationThrows(): void
    {
        $connection = static::createStub(Connection::class);
        $context = new Context(new AdminApiSource(null, null), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        $helper = new McpToolResponseTestHelper();
        $result = json_decode(
            $helper->callDryRun($connection, $context, fn (): string => throw new \RuntimeException('boom inside operation')),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        static::assertFalse($result['success']);
        static::assertSame('boom inside operation', $result['error']);
    }

    public function testResponseAtSizeHintThresholdIncludesResponseSizeInMeta(): void
    {
        $helper = new McpToolResponseTestHelper();

        $payload = ['content' => str_repeat('x', 25_000)];
        $result = json_decode($helper->callSuccess($payload), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame($payload, $result['data']);
        static::assertGreaterThanOrEqual(20_000, $result['_meta']['responseSize']);
        static::assertArrayNotHasKey('resourceUri', $result['_meta']);
    }

    public function testOversizedResponseWithoutSessionReturnsInline(): void
    {
        $helper = new McpToolResponseTestHelper();

        $payload = ['content' => str_repeat('x', 200_000)];
        $result = json_decode($helper->callSuccess($payload), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame($payload, $result['data']);
        static::assertArrayNotHasKey('_meta', $result);
    }

    public function testOversizedResponseFallsBackToInlineWhenCacheIsSetButSessionIdIsEmpty(): void
    {
        $cache = $this->createMock(ToolResultCacheStorage::class);
        $cache->expects($this->never())->method('store');

        $stack = new RequestStack();
        $stack->push(new Request());

        $helper = new McpToolResponseTestHelper();
        $helper->setToolResultCache($cache, $stack, new NullLogger());

        $payload = ['content' => str_repeat('x', 200_000)];
        $result = json_decode($helper->callSuccess($payload), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($result['success']);
        static::assertSame($payload, $result['data']);
        static::assertArrayNotHasKey('_meta', $result);
    }

    public function testOversizedResponseStoresAsResourceAndIncludesQueryFromRequestBody(): void
    {
        $cache = $this->createMock(ToolResultCacheStorage::class);
        $cache->expects($this->once())
            ->method('store')
            ->with('session-123', static::stringContains('"content":"xxxxx'))
            ->willReturn('019ddd92145f7089a9c717ebea9ec3d3');

        $request = new Request();
        $request->headers->set('Mcp-Session-Id', 'session-123');
        $request->attributes->set(McpServerController::ATTRIBUTE_JSONRPC_BODY, [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'shopware-entity-search',
                'arguments' => ['entity' => 'product', 'limit' => 500],
            ],
            'id' => 1,
        ]);

        $stack = new RequestStack();
        $stack->push($request);

        $helper = new McpToolResponseTestHelper();
        $helper->setToolResultCache($cache, $stack, new NullLogger());

        $result = json_decode(
            $helper->callSuccess(['content' => str_repeat('x', 200_000)]),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        static::assertTrue($result['success']);
        static::assertNull($result['data']);
        static::assertSame('shopware://tool-result/019ddd92145f7089a9c717ebea9ec3d3', $result['_meta']['resourceUri']);
        static::assertGreaterThan(100_000, $result['_meta']['responseSize']);
        static::assertSame('shopware-entity-search', $result['_meta']['query']['tool']);
        static::assertSame(['entity' => 'product', 'limit' => 500], $result['_meta']['query']['arguments']);
    }

    public function testOversizedResponseOmitsQueryWhenMethodIsNotToolsCall(): void
    {
        $cache = $this->createMock(ToolResultCacheStorage::class);
        $cache->method('store')->willReturn('019ddd9876543210abcdef0123456789');

        $request = new Request();
        $request->headers->set('Mcp-Session-Id', 'session-456');
        $request->attributes->set(McpServerController::ATTRIBUTE_JSONRPC_BODY, [
            'method' => 'resources/read',
            'params' => ['uri' => 'shopware://something'],
        ]);

        $stack = new RequestStack();
        $stack->push($request);

        $helper = new McpToolResponseTestHelper();
        $helper->setToolResultCache($cache, $stack, new NullLogger());

        $result = json_decode(
            $helper->callSuccess(['content' => str_repeat('x', 200_000)]),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        static::assertArrayHasKey('resourceUri', $result['_meta']);
        static::assertArrayNotHasKey('query', $result['_meta']);
    }

    public function testOversizedResponseOmitsQueryWhenToolNameIsMissing(): void
    {
        $cache = $this->createMock(ToolResultCacheStorage::class);
        $cache->method('store')->willReturn('019ddd9876543210abcdef0123456789');

        $request = new Request();
        $request->headers->set('Mcp-Session-Id', 'session-999');
        $request->attributes->set(McpServerController::ATTRIBUTE_JSONRPC_BODY, [
            'method' => 'tools/call',
            'params' => ['arguments' => ['entity' => 'product']],
        ]);

        $stack = new RequestStack();
        $stack->push($request);

        $helper = new McpToolResponseTestHelper();
        $helper->setToolResultCache($cache, $stack, new NullLogger());

        $result = json_decode(
            $helper->callSuccess(['content' => str_repeat('x', 200_000)]),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        static::assertArrayHasKey('resourceUri', $result['_meta']);
        static::assertArrayNotHasKey('query', $result['_meta']);
    }

    public function testOversizedResponseOmitsQueryWhenJsonRpcAttributeMissing(): void
    {
        $cache = $this->createMock(ToolResultCacheStorage::class);
        $cache->method('store')->willReturn('019ddd9876543210abcdef0123456789');

        $request = new Request();
        $request->headers->set('Mcp-Session-Id', 'session-789');

        $stack = new RequestStack();
        $stack->push($request);

        $helper = new McpToolResponseTestHelper();
        $helper->setToolResultCache($cache, $stack, new NullLogger());

        $result = json_decode(
            $helper->callSuccess(['content' => str_repeat('x', 200_000)]),
            true,
            512,
            \JSON_THROW_ON_ERROR,
        );

        static::assertArrayHasKey('resourceUri', $result['_meta']);
        static::assertArrayNotHasKey('query', $result['_meta']);
    }
}

/**
 * @internal
 */
class McpToolResponseTestHelper extends McpToolResponse
{
    /**
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $meta
     */
    public function callSuccess(array $data, array $meta = []): string
    {
        return $this->success($data, $meta);
    }

    public function callError(string $message): string
    {
        return $this->error($message);
    }

    /**
     * @return array<mixed>|string
     */
    public function callDecodeJsonOrError(string $json, string $fieldName = 'input'): array|string
    {
        return $this->decodeJsonOrError($json, $fieldName);
    }

    /**
     * @param callable(): string $operation
     */
    public function callDryRun(Connection $connection, Context $context, callable $operation): string
    {
        return $this->executeWithDryRun($connection, $context, $operation);
    }
}
