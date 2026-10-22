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
use Shopware\Core\Framework\Mcp\Result\McpToolResultLink;
use Shopware\Core\Framework\Mcp\Result\McpToolResultPointer;
use Shopware\Core\Framework\Mcp\Result\McpToolResultRenderer;
use Shopware\Core\Framework\Mcp\Result\McpToolResultStoredFormat;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\PlatformRequest;
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
     * @return iterable<string, array{ProtocolVersion}>
     */
    public static function legacyRevisionProvider(): iterable
    {
        yield '2024-11-05' => [ProtocolVersion::V2024_11_05];
        yield '2025-03-26' => [ProtocolVersion::V2025_03_26];
    }

    #[DataProvider('legacyRevisionProvider')]
    public function testStoresALargeResultAndKeepsThePointerInTheLegacyText(ProtocolVersion $protocolVersion): void
    {
        $legacyText = $this->legacyText(110_000);
        $this->requestStack->push($this->adminRequest(sessionId: 'session-abc'));
        $stored = null;
        $this->storage->expects($this->once())
            ->method('storeFor')
            ->with('admin:integration-id:', static::isString(), 'session-abc')
            ->willReturnCallback(function (string $principal, string $content) use (&$stored): McpToolResultPointer {
                $stored = $content;

                return $this->pointer();
            });

        $result = $this->renderer->render($this->parsed(110_000), $protocolVersion, $legacyText, new CallToolRequest('shopware-entity-search', ['entity' => 'product']));

        $text = json_decode($this->text($result->content[0]), true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($text['success']);
        static::assertNull($text['data']);
        static::assertSame(self::URI, $text['_meta']['resourceUri']);
        static::assertSame('2026-09-28T11:00:00+00:00', $text['_meta']['expiresAt']);
        static::assertIsString($stored);
        static::assertSame(\strlen($stored), $text['_meta']['responseSize']);
        static::assertStringContainsString('_meta.resourceUri', $text['_meta']['note']);
        static::assertSame(['tool' => 'shopware-entity-search', 'arguments' => ['entity' => 'product']], $text['_meta']['query']);
        static::assertNull($result->structuredContent);
        static::assertCount(1, $result->content, 'without `resource_link` the text is the only place the client finds the pointer');
    }

    /**
     * @return iterable<string, array{ProtocolVersion}>
     */
    public static function specRevisionProvider(): iterable
    {
        yield '2025-06-18' => [ProtocolVersion::V2025_06_18];
        yield '2025-11-25' => [ProtocolVersion::V2025_11_25];
        yield '2026-07-28' => [ProtocolVersion::V2026_07_28];
    }

    #[DataProvider('specRevisionProvider')]
    public function testSendsTheLinkInsteadOfTheResourceUriSince20250618(ProtocolVersion $protocolVersion): void
    {
        $this->requestStack->push($this->adminRequest());
        $this->storage->expects($this->once())->method('storeFor')->willReturn($this->pointer());

        $result = $this->renderer->render($this->parsed(110_000), $protocolVersion, $this->legacyText(110_000));

        $meta = json_decode($this->text($result->content[1]), true, 512, \JSON_THROW_ON_ERROR)['_meta'];
        static::assertNull($result->structuredContent);
        static::assertArrayNotHasKey('query', $meta, 'without the call there is nothing to echo');
        static::assertArrayNotHasKey('success', $meta);
        static::assertArrayNotHasKey('resourceUri', $meta);
        static::assertArrayNotHasKey('shopware/resourceUri', $result->meta ?? []);
        static::assertStringContainsString('resource_link', $this->text($result->content[0]));
        static::assertSame([self::URI], $this->linkUris($result));
        static::assertSame('2026-09-28T11:00:00+00:00', $result->meta['shopware/expiresAt'] ?? null);
    }

    /**
     * @return iterable<string, array{ProtocolVersion, string|null, string, McpToolResultStoredFormat}>
     */
    public static function storedFormatProvider(): iterable
    {
        $data = '{"blob":"' . str_repeat('x', 110_000) . '"}';
        $legacyText = '{"success":true,"data":' . $data . ',"_meta":{"total":1}}';

        yield '2024-11-05 stores the tool\'s legacy string' => [ProtocolVersion::V2024_11_05, $legacyText, $legacyText, McpToolResultStoredFormat::Legacy];
        yield '2025-03-26 stores the tool\'s legacy string' => [ProtocolVersion::V2025_03_26, $legacyText, $legacyText, McpToolResultStoredFormat::Legacy];
        yield '2025-03-26 stores the envelope rebuilt from a result object' => [ProtocolVersion::V2025_03_26, null, $legacyText, McpToolResultStoredFormat::Legacy];
        yield '2025-06-18 stores the plain data' => [ProtocolVersion::V2025_06_18, $legacyText, $data, McpToolResultStoredFormat::Spec];
        yield '2025-11-25 stores the plain data of a result object' => [ProtocolVersion::V2025_11_25, null, $data, McpToolResultStoredFormat::Spec];
        yield '2026-07-28 stores the plain data' => [ProtocolVersion::V2026_07_28, $legacyText, $data, McpToolResultStoredFormat::Spec];
    }

    /**
     * The format follows the protocol revision: the legacy envelope only predates `structuredContent` and
     * `resource_link`.
     */
    #[DataProvider('storedFormatProvider')]
    public function testStoresTheFormatOfTheNegotiatedRevision(ProtocolVersion $protocolVersion, ?string $legacyText, string $expectedContent, McpToolResultStoredFormat $expectedFormat): void
    {
        $this->requestStack->push($this->adminRequest());
        $this->storage->expects($this->once())
            ->method('storeFor')
            ->with('admin:integration-id:', $expectedContent, '', $expectedFormat->value)
            ->willReturn($this->pointer());

        $result = $this->renderer->render(
            McpToolResult::success(['blob' => str_repeat('x', 110_000)], meta: ['total' => 1]),
            $protocolVersion,
            $legacyText,
        );

        static::assertSame(1, $this->linkedMeta($result)['total'] ?? null, 'the metadata of a stored result goes out with the link');
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
            ->with('store:' . $context->getSalesChannelId() . ':visitor-token', json_encode($data), '', McpToolResultStoredFormat::Spec->value)
            ->willReturn($this->pointer());

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

    public function testKeepsTheSummaryAndTheLinksOfTheTool(): void
    {
        $this->requestStack->push($this->adminRequest());
        $this->storage->expects($this->once())->method('storeFor')->willReturn($this->pointer());
        $ownLink = new McpToolResultLink('shopware://media/abc', 'media', mimeType: 'image/png');

        $result = $this->renderer->render(
            new McpToolResult(['blob' => str_repeat('x', 110_000)], 'Exported 3 products', links: [$ownLink]),
            ProtocolVersion::V2025_11_25,
        );

        static::assertStringStartsWith("Exported 3 products\n\nResponse too large", $this->text($result->content[0]));
        static::assertSame(['shopware://media/abc', self::URI], $this->linkUris($result), 'the stored result is linked after the tool\'s own links');
    }

    public function testCutsALongSummaryOfAStoredResult(): void
    {
        $this->requestStack->push($this->adminRequest());
        $this->storage->expects($this->once())->method('storeFor')->willReturn($this->pointer());

        $result = $this->renderer->render(
            new McpToolResult(['blob' => str_repeat('x', 110_000)], str_repeat('s', 50_000)),
            ProtocolVersion::V2025_11_25,
        );

        static::assertStringStartsWith(str_repeat('s', McpToolResultRenderer::MAX_LINKED_SUMMARY_SIZE) . "…\n\nResponse too large", $this->text($result->content[0]));
    }

    /**
     * @return iterable<string, array{ProtocolVersion}>
     */
    public static function everyRevisionProvider(): iterable
    {
        yield from self::legacyRevisionProvider();
        yield from self::specRevisionProvider();
    }

    /**
     * A large `preview` next to the envelope fields is metadata. Copied into the link, it would keep the
     * response as large as the result it replaces.
     */
    #[DataProvider('everyRevisionProvider')]
    public function testLeavesOversizedMetadataOutOfTheLinkedResponse(ProtocolVersion $protocolVersion): void
    {
        $this->requestStack->push($this->adminRequest());
        $this->storage->expects($this->once())->method('storeFor')->willReturn($this->pointer());
        $arguments = ['input' => str_repeat('a', 20_000)];

        $result = $this->renderer->render(
            McpToolResult::success(['id' => 'a'], ['dryRun' => true, 'preview' => str_repeat('p', 250_000), 'total' => 1]),
            $protocolVersion,
            call: new CallToolRequest('ucp-checkout', $arguments),
        );

        $meta = $this->linkedMeta($result);
        static::assertTrue($meta['dryRun']);
        static::assertSame(1, $meta['total'], 'metadata after an oversized value is still kept');
        static::assertArrayNotHasKey('preview', $meta);
        static::assertSame(['preview'], $meta['omittedMeta']);
        static::assertSame(['tool' => 'ucp-checkout'], $meta['query'], 'large arguments are not echoed');
        static::assertLessThan(McpToolResultRenderer::MAX_INLINE_SIZE_TEXT_ONLY, \strlen(Json::encode($result)));
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

    /**
     * The metadata of a linked result, from the legacy envelope or from the `{"_meta": …}` block of the MCP
     * format.
     *
     * @return array<string, mixed>
     */
    private function linkedMeta(CallToolResult $result): array
    {
        foreach ($result->content as $block) {
            if (!$block instanceof TextContent) {
                continue;
            }
            $decoded = json_decode((string) $block->text, true);
            if (\is_array($decoded) && \is_array($decoded['_meta'] ?? null)) {
                return $decoded['_meta'];
            }
        }

        return [];
    }

    private function text(mixed $content): string
    {
        static::assertInstanceOf(TextContent::class, $content);
        static::assertIsString($content->text);

        return $content->text;
    }
}
