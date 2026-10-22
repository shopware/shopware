<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use Mcp\Capability\Discovery\SchemaValidator;
use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\PromptReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Mcp\Capability\Registry\ToolReference;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Prompt;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use Mcp\Schema\Tool;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Session\Session;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Result\McpToolResultParser;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPointer;
use Shopware\Core\Framework\Mcp\Result\McpToolResultReferenceHandler;
use Shopware\Core\Framework\Mcp\Result\McpToolResultRenderer;
use Shopware\Core\Framework\Mcp\Result\McpToolResultStoredFormat;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\PlatformRequest;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultReferenceHandler::class)]
class McpToolResultReferenceHandlerTest extends TestCase
{
    public function testRendersAResultObject(): void
    {
        $result = $this->handler(McpToolResult::success(['a' => 1]))->handle($this->toolReference(), []);

        static::assertInstanceOf(CallToolResult::class, $result);
        static::assertSame(['a' => 1], $result->structuredContent);
    }

    public function testSendsAToolWithAnOutputSchemaAResultThatValidates(): void
    {
        // mcp/sdk 0.9 validates `structuredContent` against the declared `outputSchema`.
        $outputSchema = [
            'type' => 'object',
            'properties' => ['id' => ['type' => 'string']],
            'required' => ['id'],
            'additionalProperties' => false,
        ];
        $reference = new ToolReference(new Tool('t', null, ['type' => 'object', 'properties' => [], 'required' => null], null, null, outputSchema: $outputSchema), static fn (): string => '');

        $result = $this->handler('{"success":true,"data":{"id":"a"},"_meta":{"dryRun":true,"total":1}}')->handle($reference, []);

        static::assertInstanceOf(CallToolResult::class, $result);
        static::assertSame('{"id":"a"}', Json::encode($result->structuredContent));
        static::assertSame([], (new SchemaValidator())->validateAgainstJsonSchema(json_decode(Json::encode($result->structuredContent), false, 512, \JSON_THROW_ON_ERROR), $outputSchema), 'decoded as the SDK sends it');
    }

    public function testAddsTheMetadataForTheModelForAToolWithoutOutputSchema(): void
    {
        $result = $this->handler('{"success":true,"data":{"id":"a"},"_meta":{"dryRun":true}}')->handle($this->toolReference(), []);

        static::assertInstanceOf(CallToolResult::class, $result);
        static::assertSame('{"id":"a","_meta":{"dryRun":true}}', Json::encode($result->structuredContent));
    }

    public function testRendersTheLegacyEnvelopeAndKeepsItsText(): void
    {
        $result = $this->handler('{"success":false,"error":"Nope","code":"not_found"}')->handle($this->toolReference(), []);

        static::assertInstanceOf(CallToolResult::class, $result);
        static::assertTrue($result->isError);
    }

    public function testPassesOtherToolResultsThrough(): void
    {
        static::assertSame('plain text', $this->handler('plain text')->handle($this->toolReference(), []));
    }

    public function testLeavesPromptsAlone(): void
    {
        $prompt = new PromptReference(new Prompt('p'), static fn (): string => '');

        static::assertSame('{"success":true}', $this->handler('{"success":true}')->handle($prompt, []));
    }

    public function testUsesTheNegotiatedProtocolVersion(): void
    {
        $session = new Session(new InMemorySessionStore());
        $session->set('protocol_version', ProtocolVersion::V2026_07_28->value);

        $result = $this->handler(McpToolResult::success([1, 2]))->handle($this->toolReference(), [
            '_session' => $session,
            '_request' => CallToolRequest::fromArray(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/call', 'params' => ['name' => 't', 'arguments' => []]]),
        ]);

        static::assertInstanceOf(CallToolResult::class, $result);
        static::assertSame([1, 2], $result->structuredContent, 'from 2026-07-28 on a list is sent unwrapped');
    }

    public function testOffloadsAHandBuiltStringAndEchoesTheCall(): void
    {
        // App tools and tools that build their JSON by hand never went through McpToolResponse::success().
        $handBuilt = '{"success":true,"data":{"blob":"' . str_repeat('x', 110_000) . '"}}';

        $storage = $this->createMock(ToolResultCacheStorage::class);
        $storage->expects($this->once())
            ->method('storeFor')
            ->with('admin:integration-id:', '{"blob":"' . str_repeat('x', 110_000) . '"}', '', McpToolResultStoredFormat::Spec->value)
            ->willReturn(new McpToolResultPointer('token', new \DateTimeImmutable('2026-09-28T11:00:00+00:00')));

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, new Context(new AdminApiSource(null, 'integration-id')));
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $result = $this->handler($handBuilt, $storage, $requestStack)->handle($this->toolReference(), [
            '_request' => new CallToolRequest('app-tool', ['entity' => 'product']),
        ]);

        static::assertInstanceOf(CallToolResult::class, $result);
        static::assertNull($result->structuredContent, 'the data is behind the link');
        static::assertSame(['tool' => 'app-tool', 'arguments' => ['entity' => 'product']], $result->meta['shopware/query'] ?? null);
    }

    private function handler(mixed $innerResult, ?ToolResultCacheStorage $storage = null, RequestStack $requestStack = new RequestStack()): McpToolResultReferenceHandler
    {
        $inner = static::createStub(ReferenceHandlerInterface::class);
        $inner->method('handle')->willReturn($innerResult);

        $renderer = new McpToolResultRenderer(new MockClock(), $storage ?? static::createStub(ToolResultCacheStorage::class), $requestStack, new NullLogger());

        return new McpToolResultReferenceHandler($inner, $renderer, new McpToolResultParser());
    }

    private function toolReference(): ElementReference
    {
        return new ToolReference(new Tool('t', null, ['type' => 'object', 'properties' => [], 'required' => null], null, null), static fn (): string => '');
    }
}
