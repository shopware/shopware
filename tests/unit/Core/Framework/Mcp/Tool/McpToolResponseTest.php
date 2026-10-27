<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use Doctrine\DBAL\Connection;
use Mcp\Schema\Enum\ProtocolVersion;
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
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Result\McpToolResultParser;
use Shopware\Core\Framework\Mcp\Result\McpToolResultRenderer;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResponse::class)]
class McpToolResponseTest extends TestCase
{
    public function testTinyPayloadHasNoResponseSizeHint(): void
    {
        $result = (new TestTool())->callSuccess(['items' => ['a', 'b']]);

        static::assertFalse($result->isError());
        static::assertSame(['items' => ['a', 'b']], $result->data);
        static::assertSame([], $result->meta);
        static::assertNull($result->summary);
    }

    public function testMidSizedPayloadIncludesResponseSizeHint(): void
    {
        $payload = ['items' => array_fill(0, 1000, str_repeat('x', 30))];
        $result = (new TestTool())->callSuccess($payload);

        static::assertFalse($result->isError());
        static::assertSame($payload, $result->data);
        static::assertIsInt($result->meta['responseSize']);
        static::assertGreaterThanOrEqual(20_000, $result->meta['responseSize']);
        static::assertLessThanOrEqual(100_000, $result->meta['responseSize']);
        static::assertArrayNotHasKey('resourceUri', $result->meta);
    }

    public function testALargePayloadIsReturnedInFullBecauseTheRendererDecidesAboutOffloading(): void
    {
        $result = (new TestTool())->callSuccess(['items' => array_fill(0, 5_000, str_repeat('x', 30))]);

        static::assertIsArray($result->data);
        static::assertCount(5_000, $result->data['items']);
        static::assertGreaterThan(100_000, $result->meta['responseSize']);
        static::assertArrayNotHasKey('resourceUri', $result->meta);
    }

    public function testSuccessKeepsMetaAndSummary(): void
    {
        $result = (new TestTool())->callSuccess(['x' => 1], ['total' => 5], '1 product found');

        static::assertSame(['x' => 1], $result->data);
        static::assertSame(['total' => 5], $result->meta);
        static::assertSame('1 product found', $result->summary);
    }

    public function testErrorCarriesMessageCodeAndDetails(): void
    {
        $result = (new TestTool())->callError('Something broke', McpToolError::INVALID_ARGUMENTS, ['field' => 'name']);

        static::assertTrue($result->isError());
        static::assertSame('Something broke', $result->error->message);
        static::assertSame(McpToolError::INVALID_ARGUMENTS, $result->error->code);
        static::assertSame(['field' => 'name'], $result->error->details);
        static::assertNull($result->data);
    }

    public function testErrorDefaultsToTheToolErrorCode(): void
    {
        $result = (new TestTool())->callError('Something broke');

        static::assertTrue($result->isError());
        static::assertSame(McpToolError::TOOL_ERROR, $result->error->code);
        static::assertSame([], $result->error->details);
    }

    public function testRequirePrivilegeNamesTheFirstMissingPrivilege(): void
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions(['product:read']);
        $context = new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        static::assertNull((new TestTool())->callRequirePrivilege($context, 'product:read'));

        $result = (new TestTool())->callRequirePrivilege($context, 'product:read', 'order:read', 'tax:read');

        static::assertNotNull($result);
        static::assertTrue($result->isError());
        static::assertSame('Missing privilege: order:read', $result->error->message);
        static::assertSame(McpToolError::MISSING_PRIVILEGE, $result->error->code);
    }

    public function testDecodeJsonOrErrorReturnsArrayOnValidInput(): void
    {
        $result = (new TestTool())->callDecodeJsonOrError('{"key":"value"}', 'criteria');

        static::assertSame(['key' => 'value'], $result);
    }

    public function testDecodeJsonOrErrorReturnsAFailureOnMalformedInput(): void
    {
        $result = (new TestTool())->callDecodeJsonOrError('not-json', 'criteria');

        static::assertInstanceOf(McpToolResult::class, $result);
        static::assertTrue($result->isError());
        static::assertSame(McpToolError::INVALID_ARGUMENTS, $result->error->code);
        static::assertStringContainsString('criteria', $result->error->message);
        static::assertStringContainsString('Invalid JSON', $result->error->message);
    }

    public function testDecodeJsonOrErrorReturnsAFailureWhenNotAnArray(): void
    {
        $result = (new TestTool())->callDecodeJsonOrError('"just-a-string"', 'payload');

        static::assertInstanceOf(McpToolResult::class, $result);
        static::assertTrue($result->isError());
        static::assertStringContainsString('payload', $result->error->message);
    }

    public function testDryRunReturnsTheResultOfTheOperation(): void
    {
        $context = $this->createContext();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->once())->method('rollBack');

        $result = (new TestTool())->callDryRun($connection, $context, function () use ($context): McpToolResult {
            static::assertTrue($context->hasState(Context::SKIP_TRIGGER_FLOW));

            return McpToolResult::success(['id' => 'a'], ['dryRun' => true]);
        });

        static::assertSame(['id' => 'a'], $result->data);
        static::assertSame(['dryRun' => true], $result->meta);
        static::assertFalse($context->hasState(Context::SKIP_TRIGGER_FLOW));
    }

    public function testDryRunReturnsAFailureOnRollbackFailure(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('rollBack')->willThrowException(new \RuntimeException('rollback failed'));

        $result = (new TestTool())->callDryRun($connection, $this->createContext(), static fn (): McpToolResult => McpToolResult::success([]));

        static::assertTrue($result->isError());
        static::assertStringContainsString('Dry-run rollback failed', $result->error->message);
        static::assertStringContainsString('rollback failed', $result->error->message);
    }

    public function testDryRunReturnsAFailureWhenOperationThrows(): void
    {
        $connection = static::createStub(Connection::class);

        $result = (new TestTool())->callDryRun(
            $connection,
            $this->createContext(),
            static fn (): McpToolResult => throw new \RuntimeException('boom inside operation'),
        );

        static::assertTrue($result->isError());
        static::assertSame('boom inside operation', $result->error->message);
    }

    /**
     * @return iterable<string, array{ProtocolVersion, \Closure(TestTool): McpToolResult, \Closure(TestTool): string}>
     */
    public static function resultAndLegacyStringProvider(): iterable
    {
        $cases = [
            'an object' => [
                static fn (TestTool $tool): McpToolResult => $tool->callSuccess(['id' => 'a', 'nested' => ['x' => 1]]),
                static fn (TestTool $tool): string => $tool->callLegacySuccess(['id' => 'a', 'nested' => ['x' => 1]]),
            ],
            'a list with metadata' => [
                static fn (TestTool $tool): McpToolResult => $tool->callSuccess([['entity' => 'tax', 'ids' => ['a']]], ['dryRun' => true]),
                static fn (TestTool $tool): string => $tool->callLegacySuccess([['entity' => 'tax', 'ids' => ['a']]], ['dryRun' => true]),
            ],
            'a result with a response size hint' => [
                static fn (TestTool $tool): McpToolResult => $tool->callSuccess(['items' => array_fill(0, 1000, str_repeat('x', 30))], ['total' => 1000]),
                static fn (TestTool $tool): string => $tool->callLegacySuccess(['items' => array_fill(0, 1000, str_repeat('x', 30))], ['total' => 1000]),
            ],
            'a failure' => [
                static fn (TestTool $tool): McpToolResult => $tool->callError('Order not found.', McpToolError::NOT_FOUND),
                static fn (TestTool $tool): string => $tool->callLegacyError('Order not found.', McpToolError::NOT_FOUND),
            ],
        ];

        foreach ([ProtocolVersion::V2025_03_26, ProtocolVersion::V2025_06_18, ProtocolVersion::V2025_11_25, ProtocolVersion::V2026_07_28] as $revision) {
            foreach ($cases as $name => [$result, $legacy]) {
                yield $name . ' on ' . $revision->value => [$revision, $result, $legacy];
            }
        }
    }

    /**
     * @param \Closure(TestTool): McpToolResult $result
     * @param \Closure(TestTool): string $legacy
     */
    #[DataProvider('resultAndLegacyStringProvider')]
    #[TestDox('Returning the result object renders $_dataName exactly like the legacy string')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testTheResultObjectRendersLikeTheLegacyString(ProtocolVersion $revision, \Closure $result, \Closure $legacy): void
    {
        $renderer = new McpToolResultRenderer(new MockClock('2026-10-26 12:00:00'), static::createStub(ToolResultCacheStorage::class), new RequestStack(), new NullLogger());
        $tool = new TestTool();
        $legacyText = $legacy($tool);
        $parsed = (new McpToolResultParser())->parse($legacyText);
        static::assertNotNull($parsed);

        static::assertSame(
            Json::encode($renderer->render($parsed, $revision, $legacyText)),
            Json::encode($renderer->render($result($tool), $revision)),
        );
    }

    #[TestDox('The deprecated string helpers still build the legacy envelope, with responseSize from 20 KB')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacySuccessBuildsTheEnvelope(): void
    {
        $tool = new TestTool();

        static::assertSame('{"success":true,"data":{"items":["a","b"]}}', $tool->callLegacySuccess(['items' => ['a', 'b']]));
        static::assertSame('{"success":true,"data":[],"_meta":{"total":0}}', $tool->callLegacySuccess([], ['total' => 0]));

        $json = $tool->callLegacySuccess(['items' => array_fill(0, 1000, str_repeat('x', 30))]);
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($data);
        static::assertGreaterThanOrEqual(20_000, $data['_meta']['responseSize']);
        static::assertSame(\strlen($tool->callLegacySuccess(['items' => array_fill(0, 1000, str_repeat('x', 30))])), \strlen($json));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testLegacyErrorHelpersBuildTheEnvelope(): void
    {
        $tool = new TestTool();
        $context = $this->createContext();

        static::assertSame('{"success":false,"error":"Something broke","code":"tool_error"}', $tool->callLegacyError('Something broke'));
        static::assertSame('{"success":false,"error":"Missing privilege: product:read","code":"missing_privilege"}', $tool->callLegacyRequirePrivilege($context, 'product:read'));
        static::assertSame('{"success":false,"error":"Missing privilege: product:read, order:read","code":"missing_privilege"}', $tool->callLegacyMissingPrivileges(['product:read', 'order:read']));
        static::assertSame(['a' => 1], $tool->callLegacyDecodeJsonOrError('{"a":1}'));
        static::assertSame('{"success":false,"error":"\"payload\" must be a JSON object or array","code":"invalid_arguments"}', $tool->callLegacyDecodeJsonOrError('1', 'payload'));
        static::assertSame('{"success":false,"error":"boom","code":"tool_error"}', $tool->callLegacyDryRun(static::createStub(Connection::class), $context, static fn (): string => throw new \RuntimeException('boom')));
        static::assertSame('{"success":true}', $tool->callLegacyDryRun(static::createStub(Connection::class), $context, static fn (): string => '{"success":true}'));
        static::assertSame(
            '{"success":false,"error":"Invalid criteria: \\/filter\\/0: bad","code":"invalid_arguments"}',
            $tool->callLegacyInvalidCriteriaError($this->searchRequestException('bad', '/filter/0')),
        );
    }

    /**
     * @return iterable<string, array{string, string, \Closure(TestTool): mixed}>
     */
    public static function deprecatedStringHelperProvider(): iterable
    {
        $context = new Context(new AdminApiSource(null, null), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);

        yield 'success()' => ['success', 'successResult()', static fn (TestTool $tool): string => $tool->callLegacySuccess([])];
        yield 'error()' => ['error', 'errorResult()', static fn (TestTool $tool): string => $tool->callLegacyError('boom')];
        yield 'invalidCriteriaError()' => ['invalidCriteriaError', 'invalidCriteriaResult()', static fn (TestTool $tool): string => $tool->callLegacyInvalidCriteriaError(new SearchRequestException())];
        yield 'decodeJsonOrError()' => ['decodeJsonOrError', 'decodeJsonOrErrorResult()', static fn (TestTool $tool): array|string => $tool->callLegacyDecodeJsonOrError('{}')];
        yield 'requirePrivilege()' => ['requirePrivilege', 'requirePrivilegeResult()', static fn (TestTool $tool): ?string => $tool->callLegacyRequirePrivilege($context, 'product:read')];
        yield 'missingPrivilegesError()' => ['missingPrivilegesError', 'missingPrivilegesResult()', static fn (TestTool $tool): string => $tool->callLegacyMissingPrivileges(['product:read'])];
        yield 'executeWithDryRun()' => ['executeWithDryRun', 'executeWithDryRunResult()', static fn (TestTool $tool): string => $tool->callLegacyDryRun(static::createStub(Connection::class), $context, static fn (): string => '{"success":true}')];
    }

    /**
     * @param \Closure(TestTool): mixed $call
     */
    #[DataProvider('deprecatedStringHelperProvider')]
    #[TestDox('The deprecated $_dataName throws once the 6.8.0.0 flag is active')]
    public function testDeprecatedStringHelperThrowsWithTheMajorFlag(string $method, string $replacement, \Closure $call): void
    {
        $this->expectExceptionObject(FeatureException::error(\sprintf(
            'Tried to access deprecated functionality: Method "%s::%s()" is deprecated and will be removed in v6.8.0.0. Use "%s" instead.',
            McpToolResponse::class,
            $method,
            $replacement,
        )));

        $call(new TestTool());
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

        $data = (new TestTool())->callInvalidCriteriaError($exception);

        static::assertTrue($data->isError());
        static::assertStringContainsString('/aggregations/0/avg/field', $data->error->message);
        static::assertStringContainsString('The aggregation should contain a "field".', $data->error->message);
        static::assertStringContainsString('/filter/1/equals/field', $data->error->message);
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
        $data = (new TestTool())->callInvalidCriteriaError($exception);

        static::assertTrue($data->isError());
        static::assertStringNotContainsString('Invalid criteria: ', $data->error->message);

        foreach ($expectedFragments as $fragment) {
            static::assertStringContainsString($fragment, $data->error->message);
        }
    }

    public function testInvalidCriteriaErrorFallsBackToTheMessageWhenThereAreNoDetails(): void
    {
        $data = (new TestTool())->callInvalidCriteriaError(new SearchRequestException());

        static::assertTrue($data->isError());
        static::assertNotSame('', $data->error->message);
        static::assertStringNotContainsString('Invalid criteria: ', $data->error->message);
    }

    private function searchRequestException(string $message, string $pointer): SearchRequestException
    {
        $exception = new SearchRequestException();
        $exception->add(new InvalidAggregationQueryException($message), $pointer);

        return $exception;
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
     * @param array<string, mixed> $meta
     */
    public function callSuccess(mixed $data, array $meta = [], ?string $summary = null): McpToolResult
    {
        return $this->successResult($data, $meta, $summary);
    }

    /**
     * @param array<string, mixed> $details
     */
    public function callError(string $message, string $code = McpToolError::TOOL_ERROR, array $details = []): McpToolResult
    {
        return $this->errorResult($message, $code, $details);
    }

    /**
     * @return array<mixed>|McpToolResult
     */
    public function callDecodeJsonOrError(string $json, string $fieldName = 'input'): array|McpToolResult
    {
        return $this->decodeJsonOrErrorResult($json, $fieldName);
    }

    /**
     * @param callable(): McpToolResult $operation
     */
    public function callDryRun(Connection $connection, Context $context, callable $operation): McpToolResult
    {
        return $this->executeWithDryRunResult($connection, $context, $operation);
    }

    public function callInvalidCriteriaError(ShopwareHttpException $e): McpToolResult
    {
        return $this->invalidCriteriaResult($e);
    }

    public function callRequirePrivilege(Context $context, string ...$privileges): ?McpToolResult
    {
        return $this->requirePrivilegeResult($context, ...$privileges);
    }

    /**
     * @param array<string, mixed>|list<mixed> $data
     * @param array<string, mixed> $meta
     */
    public function callLegacySuccess(array $data, array $meta = []): string
    {
        return $this->success($data, $meta);
    }

    public function callLegacyError(string $message, string $code = McpToolError::TOOL_ERROR): string
    {
        return $this->error($message, $code);
    }

    /**
     * @return array<mixed>|string
     */
    public function callLegacyDecodeJsonOrError(string $json, string $fieldName = 'input'): array|string
    {
        return $this->decodeJsonOrError($json, $fieldName);
    }

    public function callLegacyRequirePrivilege(Context $context, string ...$privileges): ?string
    {
        return $this->requirePrivilege($context, ...$privileges);
    }

    public function callLegacyInvalidCriteriaError(ShopwareHttpException $e): string
    {
        return $this->invalidCriteriaError($e);
    }

    /**
     * @param non-empty-list<string> $privileges
     */
    public function callLegacyMissingPrivileges(array $privileges): string
    {
        return $this->missingPrivilegesError($privileges);
    }

    /**
     * @param callable(): string $operation
     */
    public function callLegacyDryRun(Connection $connection, Context $context, callable $operation): string
    {
        return $this->executeWithDryRun($connection, $context, $operation);
    }
}
