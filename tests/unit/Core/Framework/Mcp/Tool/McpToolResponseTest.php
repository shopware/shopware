<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\ApiProtectionException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidAggregationQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\RuntimeFieldInCriteriaException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Controller\McpServerController;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
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
        static::assertArrayNotHasKey('resourceUri', $data['_meta']);
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
        $cache = static::createStub(ToolResultCacheStorage::class);
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

    public function testSuccessWithMetaIncludesMetaKey(): void
    {
        $tool = new TestTool();

        $data = json_decode($tool->callSuccess(['x' => 1], ['total' => 5]), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(5, $data['_meta']['total']);
    }

    public function testErrorReturnsCorrectStructure(): void
    {
        $tool = new TestTool();

        $data = json_decode($tool->callError('Something broke'), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertSame('Something broke', $data['error']);
        static::assertArrayNotHasKey('data', $data);
    }

    public function testOversizedPayloadFallsBackToInlineWhenTheSessionHeaderIsMissing(): void
    {
        $cache = $this->createMock(ToolResultCacheStorage::class);
        $cache->expects($this->never())->method('store');

        $requestStack = new RequestStack();
        $requestStack->push(new Request());

        $tool = new TestTool();
        $tool->setToolResultCache($cache, $requestStack, new NullLogger());

        $json = $tool->callSuccess(['items' => array_fill(0, 5_000, str_repeat('x', 30))]);
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertIsArray($data['data']['items']);
        static::assertArrayNotHasKey('_meta', $data);
    }

    /**
     * @return iterable<string, array{array<string, mixed>|null}>
     */
    public static function bodyWithoutAToolCallProvider(): iterable
    {
        yield 'no JSON-RPC body on the request at all' => [null];
        yield 'a method other than tools/call' => [['method' => 'resources/read', 'params' => ['uri' => 'shopware://something']]];
        yield 'a tools/call without a tool name' => [['method' => 'tools/call', 'params' => ['arguments' => ['entity' => 'product']]]];
    }

    /**
     * @param array<string, mixed>|null $body
     */
    #[DataProvider('bodyWithoutAToolCallProvider')]
    #[TestDox('_meta.query is omitted for $_dataName, because there is no call to echo back')]
    public function testOversizedPayloadOmitsQueryWhenTheBodyIsNotAToolCall(?array $body): void
    {
        $cache = static::createStub(ToolResultCacheStorage::class);
        $cache->method('store')->willReturn('cached-uuid');

        $request = new Request();
        $request->headers->set('Mcp-Session-Id', 'session-abc');
        if ($body !== null) {
            $request->attributes->set(McpServerController::ATTRIBUTE_JSONRPC_BODY, $body);
        }

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $tool = new TestTool();
        $tool->setToolResultCache($cache, $requestStack, new NullLogger());

        $json = $tool->callSuccess(['items' => array_fill(0, 5_000, str_repeat('x', 30))]);
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('resourceUri', $data['_meta']);
        static::assertArrayNotHasKey('query', $data['_meta']);
    }

    public function testDecodeJsonOrErrorReturnsArrayOnValidInput(): void
    {
        $result = (new TestTool())->callDecodeJsonOrError('{"key":"value"}', 'criteria');

        static::assertSame(['key' => 'value'], $result);
    }

    public function testDecodeJsonOrErrorReturnsErrorJsonOnMalformedInput(): void
    {
        $result = (new TestTool())->callDecodeJsonOrError('not-json', 'criteria');

        static::assertIsString($result);
        $data = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('criteria', $data['error']);
        static::assertStringContainsString('Invalid JSON', $data['error']);
    }

    public function testDecodeJsonOrErrorReturnsErrorJsonWhenNotAnArray(): void
    {
        $result = (new TestTool())->callDecodeJsonOrError('"just-a-string"', 'payload');

        static::assertIsString($result);
        $data = json_decode($result, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('payload', $data['error']);
    }

    public function testDryRunReturnsErrorOnRollbackFailure(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('rollBack')->willThrowException(new \RuntimeException('rollback failed'));

        $json = (new TestTool())->callDryRun($connection, $this->createContext(), fn () => '{"success":true}');
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Dry-run rollback failed', $data['error']);
        static::assertStringContainsString('rollback failed', $data['error']);
    }

    public function testDryRunReturnsErrorWhenOperationThrows(): void
    {
        $connection = static::createStub(Connection::class);

        $json = (new TestTool())->callDryRun(
            $connection,
            $this->createContext(),
            fn (): string => throw new \RuntimeException('boom inside operation'),
        );
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertSame('boom inside operation', $data['error']);
    }

    #[TestDox('The pointer of each rejected element is named, because the caller cannot infer which one was wrong')]
    public function testInvalidCriteriaErrorNamesThePointerOfEachRejectedElement(): void
    {
        $exception = new SearchRequestException();
        $exception->add(
            new InvalidAggregationQueryException('The aggregation should contain a "field".'),
            '/aggregations/0/avg/field'
        );
        $exception->add(
            DataAbstractionLayerException::invalidFilterQuery('The filter should contain a "field".', '/filter/1/equals/field'),
            '/filter/1/equals/field'
        );

        $data = json_decode(
            (new TestTool())->callInvalidCriteriaError($exception),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        static::assertFalse($data['success']);
        static::assertStringContainsString('/aggregations/0/avg/field', $data['error']);
        static::assertStringContainsString('The aggregation should contain a "field".', $data['error']);
        static::assertStringContainsString('/filter/1/equals/field', $data['error']);
    }

    /**
     * `RequestCriteriaBuilder::fromArray()` does not funnel every bad payload through
     * `SearchRequestException`; several classes are thrown directly. Each one already names
     * the offending part of the payload, so its own message is what the caller needs.
     *
     * @return iterable<string, array{ShopwareHttpException, list<string>}>
     */
    public static function directThrowProvider(): iterable
    {
        yield 'InvalidAggregationQueryException from the aggregation parser' => [
            new InvalidAggregationQueryException('The aggregations parameter has to be a list of aggregations.'),
            ['The aggregations parameter has to be a list of aggregations.'],
        ];
        yield 'the DataAbstractionLayerException base class, e.g. for {"includes":"id"}' => [
            DataAbstractionLayerException::expectedArrayWithType('includes', 'string'),
            ['includes', 'array'],
        ];
        yield 'FrameworkException for an association that does not exist' => [
            FrameworkException::associationNotFound('lineItem'),
            ['lineItem'],
        ];
        yield 'ApiProtectionException from the ApiCriteriaValidator' => [
            new ApiProtectionException('product.internalNote'),
            ['product.internalNote', 'not allowed'],
        ];
        yield 'RuntimeFieldInCriteriaException from the ApiCriteriaValidator' => [
            new RuntimeFieldInCriteriaException('product.variation'),
            ['product.variation', 'Runtime field'],
        ];
    }

    /**
     * @param list<string> $expectedFragments
     */
    #[DataProvider('directThrowProvider')]
    #[TestDox('$_dataName is rendered by its own message instead of the SDK\'s generic error')]
    public function testInvalidCriteriaErrorCarriesTheMessageOfADirectThrow(ShopwareHttpException $exception, array $expectedFragments): void
    {
        $data = json_decode(
            (new TestTool())->callInvalidCriteriaError($exception),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        static::assertFalse($data['success']);
        static::assertStringNotContainsString('Invalid criteria: ', $data['error']);

        foreach ($expectedFragments as $fragment) {
            static::assertStringContainsString($fragment, $data['error']);
        }
    }

    public function testInvalidCriteriaErrorFallsBackToTheMessageWhenThereAreNoDetails(): void
    {
        $data = json_decode(
            (new TestTool())->callInvalidCriteriaError(new SearchRequestException()),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        static::assertFalse($data['success']);
        static::assertNotSame('', $data['error']);
        static::assertStringNotContainsString('Invalid criteria: ', $data['error']);
    }

    private function createContext(): Context
    {
        return new Context(new AdminApiSource(null, null), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
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

    public function callInvalidCriteriaError(ShopwareHttpException $e): string
    {
        return $this->invalidCriteriaError($e);
    }
}
