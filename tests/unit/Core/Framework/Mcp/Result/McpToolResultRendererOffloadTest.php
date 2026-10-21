<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use Mcp\Schema\Content\ResourceLink;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Schema\Request\CallToolRequest;
use Mcp\Schema\Result\CallToolResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPointer;
use Shopware\Core\Framework\Mcp\Result\McpToolResultRenderer;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The renderer decides whether a result is too large to send inline, for every tool and app alike, and
 * stores it for the calling principal.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultRenderer::class)]
class McpToolResultRendererOffloadTest extends TestCase
{
    private const URI = 'shopware://tool-result/token';

    private ToolResultCacheStorage&MockObject $storage;

    private RequestStack $requestStack;

    private McpToolResultRenderer $renderer;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(ToolResultCacheStorage::class);
        $this->requestStack = new RequestStack();
        $this->renderer = new McpToolResultRenderer(new MockClock('2026-09-28T10:00:00+00:00'), $this->storage, $this->requestStack, new NullLogger());
    }

    /**
     * @return iterable<string, array{ProtocolVersion}>
     */
    public static function protocolVersionProvider(): iterable
    {
        yield 'a revision before resource_link and structuredContent' => [ProtocolVersion::V2025_03_26];
        yield 'the latest handshake revision' => [ProtocolVersion::V2025_11_25];
        yield 'the stateless revision' => [ProtocolVersion::V2026_07_28];
    }

    /**
     * @deprecated tag:v6.8.0 - Tests the legacy envelope, will be removed
     */
    #[DataProvider('protocolVersionProvider')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testStoresALargeResultAndKeepsThePointerInTheLegacyText(ProtocolVersion $protocolVersion): void
    {
        $legacyText = $this->legacyText(110_000);
        $this->requestStack->push($this->adminRequest(sessionId: 'session-abc'));
        $this->storage->expects($this->once())
            ->method('storeFor')
            ->with('admin:integration-id:', $legacyText, 'session-abc')
            ->willReturn($this->pointer());

        $result = $this->renderer->render($this->parsed(110_000), $protocolVersion, $legacyText, new CallToolRequest('shopware-entity-search', ['entity' => 'product']));

        $text = json_decode($this->text($result->content[0]), true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($text['success']);
        static::assertNull($text['data']);
        static::assertSame(self::URI, $text['_meta']['resourceUri']);
        static::assertSame('2026-09-28T11:00:00+00:00', $text['_meta']['expiresAt']);
        static::assertSame(\strlen($legacyText), $text['_meta']['responseSize']);
        static::assertStringContainsString('_meta.resourceUri', $text['_meta']['note']);
        static::assertSame(['tool' => 'shopware-entity-search', 'arguments' => ['entity' => 'product']], $text['_meta']['query']);
        static::assertNull($result->structuredContent);
        static::assertSame($protocolVersion === ProtocolVersion::V2025_03_26 ? [] : [self::URI], $this->linkUris($result));
    }

    #[DataProvider('protocolVersionProvider')]
    public function testSendsTheLinkInsteadOfTheResourceUriWithTheSpecFormat(ProtocolVersion $protocolVersion): void
    {
        $this->requestStack->push($this->adminRequest());
        $this->storage->expects($this->once())->method('storeFor')->willReturn($this->pointer());

        $result = $this->renderer->render($this->parsed(110_000), $protocolVersion, $this->legacyText(110_000));

        $meta = json_decode($this->text($result->content[1]), true, 512, \JSON_THROW_ON_ERROR)['_meta'];
        static::assertNull($result->structuredContent);
        static::assertArrayNotHasKey('query', $meta, 'without the call there is nothing to echo');
        if ($protocolVersion === ProtocolVersion::V2025_03_26) {
            // Without `resource_link` the text is the only place the client finds the pointer.
            static::assertSame(self::URI, $meta['resourceUri']);
            static::assertStringContainsString('_meta.resourceUri', $this->text($result->content[0]));
            static::assertSame([], $this->linkUris($result));

            return;
        }

        static::assertArrayNotHasKey('resourceUri', $meta);
        static::assertArrayNotHasKey('shopware/resourceUri', $result->meta ?? []);
        static::assertStringContainsString('resource_link', $this->text($result->content[0]));
        static::assertSame([self::URI], $this->linkUris($result));
        static::assertSame('2026-09-28T11:00:00+00:00', $result->meta['shopware/expiresAt'] ?? null);
    }

    /**
     * @return iterable<string, array{ProtocolVersion, int, bool}>
     */
    public static function dataSizeProvider(): iterable
    {
        foreach (self::protocolVersionProvider() as $name => [$protocolVersion]) {
            yield 'about 90 KB of data stays inline on ' . $name => [$protocolVersion, 90_000, false];
            yield 'about 110 KB of data is stored on ' . $name => [$protocolVersion, 110_000, true];
        }
    }

    #[DataProvider('dataSizeProvider')]
    public function testOffloadsAtTheSameAmountOfDataOnEveryRevision(ProtocolVersion $protocolVersion, int $dataSize, bool $stored): void
    {
        $this->requestStack->push($this->adminRequest());
        $this->storage->expects($stored ? $this->once() : $this->never())->method('storeFor')->willReturn($this->pointer());

        $result = $this->renderer->render($this->parsed($dataSize), $protocolVersion, $this->legacyText($dataSize));

        if ($stored) {
            static::assertNull($result->structuredContent);
            static::assertStringNotContainsString('xxx', $this->text($result->content[0]));

            return;
        }

        static::assertStringContainsString(str_repeat('x', $dataSize), $this->text($result->content[0]));
        if ($protocolVersion->isAtLeast(ProtocolVersion::V2025_06_18)) {
            static::assertSame(['blob' => str_repeat('x', $dataSize)], $result->structuredContent, 'structuredContent is never dropped to fit the limit');
        }
    }

    public function testStoresAStoreApiResultForTheSalesChannelAndContextToken(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'visitor-token');
        $request = new Request(server: ['HTTP_SW_CONTEXT_TOKEN' => 'visitor-token']);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);
        $this->requestStack->push($request);

        $data = ['blob' => str_repeat('x', 110_000)];
        $this->storage->expects($this->once())
            ->method('storeFor')
            ->with('store:' . $context->getSalesChannelId() . ':visitor-token', '{"success":true,"data":' . json_encode($data) . '}', '')
            ->willReturn($this->pointer());

        // A tool that returns the result object has no legacy text; the stored form is the rebuilt envelope.
        $result = $this->renderer->render(McpToolResult::success($data), ProtocolVersion::V2025_11_25);

        static::assertSame([self::URI], $this->linkUris($result));
    }

    public function testKeepsAnAnonymousStoreApiResultInline(): void
    {
        // The context resolver minted the token for this request only; nobody could read the result back.
        $request = new Request();
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'minted-for-this-request');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, Generator::generateSalesChannelContext(token: 'minted-for-this-request'));
        $this->requestStack->push($request);
        $this->storage->expects($this->never())->method('storeFor');

        $result = $this->renderer->render($this->parsed(110_000), ProtocolVersion::V2025_11_25, $this->legacyText(110_000));

        static::assertSame(['blob' => str_repeat('x', 110_000)], $result->structuredContent);
    }

    /**
     * @return iterable<string, array{Request|null}>
     */
    public static function requestWithoutPrincipalProvider(): iterable
    {
        yield 'no request at all, as on the CLI' => [null];
        yield 'a request without an authenticated source' => [new Request()];
    }

    #[DataProvider('requestWithoutPrincipalProvider')]
    public function testKeepsALargeResultInlineWithoutAPrincipal(?Request $request): void
    {
        if ($request !== null) {
            $this->requestStack->push($request);
        }
        $this->storage->expects($this->never())->method('storeFor');

        $result = $this->renderer->render($this->parsed(110_000), ProtocolVersion::V2025_11_25, $this->legacyText(110_000));

        static::assertSame(['blob' => str_repeat('x', 110_000)], $result->structuredContent);
        static::assertSame([], $this->linkUris($result));
    }

    public function testNeverStoresAFailure(): void
    {
        $this->requestStack->push($this->adminRequest());
        $this->storage->expects($this->never())->method('storeFor');

        $result = $this->renderer->render(McpToolResult::failure('Invalid', details: ['blob' => str_repeat('x', 250_000)]), ProtocolVersion::V2025_11_25);

        static::assertTrue($result->isError);
        static::assertSame([], $this->linkUris($result));
    }

    private function parsed(int $dataSize): McpToolResult
    {
        return McpToolResult::success(['blob' => str_repeat('x', $dataSize)]);
    }

    private function legacyText(int $dataSize): string
    {
        return '{"success":true,"data":{"blob":"' . str_repeat('x', $dataSize) . '"}}';
    }

    private function adminRequest(?string $sessionId = null): Request
    {
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, new Context(new AdminApiSource(null, 'integration-id')));
        if ($sessionId !== null) {
            $request->headers->set('Mcp-Session-Id', $sessionId);
        }

        return $request;
    }

    private function pointer(): McpToolResultPointer
    {
        return new McpToolResultPointer('token', new \DateTimeImmutable('2026-09-28T11:00:00+00:00'));
    }

    /**
     * @return list<string>
     */
    private function linkUris(CallToolResult $result): array
    {
        return array_values(array_map(
            static fn (ResourceLink $link): string => $link->uri,
            array_filter($result->content, static fn (mixed $block): bool => $block instanceof ResourceLink),
        ));
    }

    private function text(mixed $content): string
    {
        static::assertInstanceOf(TextContent::class, $content);
        static::assertIsString($content->text);

        return $content->text;
    }
}
