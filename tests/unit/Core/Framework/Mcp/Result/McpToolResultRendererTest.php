<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use Mcp\Schema\Content\ResourceLink;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\ProtocolVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Result\McpToolResultLink;
use Shopware\Core\Framework\Mcp\Result\McpToolResultRenderer;
use Shopware\Core\Framework\Mcp\ToolResultCacheStorage;
use Shopware\Core\Framework\Util\Json;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultRenderer::class)]
class McpToolResultRendererTest extends TestCase
{
    private const NOW = '2026-09-28T10:00:00+00:00';

    /**
     * @return iterable<string, array{ProtocolVersion}>
     */
    public static function legacyRevisionProvider(): iterable
    {
        yield '2024-11-05' => [ProtocolVersion::V2024_11_05];
        yield '2025-03-26' => [ProtocolVersion::V2025_03_26];
    }

    #[DataProvider('legacyRevisionProvider')]
    public function testSendsTheToolsLegacyTextUnchangedBefore20250618(ProtocolVersion $protocolVersion): void
    {
        $legacy = '{"success":true,"data":{"id":"a"},"_meta":{"total":1}}';

        $result = $this->renderer()->render(McpToolResult::success(['id' => 'a'], ['total' => 1], 'One product'), $protocolVersion, $legacy);

        static::assertFalse($result->isError);
        static::assertNull($result->structuredContent);
        static::assertCount(1, $result->content);
        static::assertSame($legacy, $this->text($result->content[0]), 'existing readers must see exactly the string the tool returned');
        static::assertSame(['shopware/generatedAt' => self::NOW], $result->meta, 'the metadata is inside the envelope');
    }

    #[DataProvider('legacyRevisionProvider')]
    public function testSendsAFailureAsTheLegacyEnvelopeBefore20250618(ProtocolVersion $protocolVersion): void
    {
        $result = $this->renderer()->render(McpToolResult::failure('Missing privilege: product:read', McpToolError::MISSING_PRIVILEGE), $protocolVersion);

        static::assertTrue($result->isError, '`isError` exists on every revision');
        static::assertNull($result->structuredContent);
        static::assertSame('{"success":false,"error":"Missing privilege: product:read","code":"missing_privilege"}', $this->text($result->content[0]));
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
    public function testSendsTheMcpFormatInsteadOfTheLegacyTextSince20250618(ProtocolVersion $protocolVersion): void
    {
        $result = $this->renderer()->render(McpToolResult::success(['id' => 'a'], ['total' => 1]), $protocolVersion, '{"success":true,"data":{"id":"a"},"_meta":{"total":1}}');

        static::assertSame(['id' => 'a', '_meta' => ['total' => 1]], $result->structuredContent);
        static::assertSame('{"id":"a"}', $this->text($result->content[0]), 'the legacy text is not sent next to the data');
        static::assertSame('{"_meta":{"total":1}}', $this->text($result->content[1]));
        static::assertCount(2, $result->content);
    }

    #[DataProvider('specRevisionProvider')]
    public function testCarriesTheMetadataForTheModelInStructuredContentSince20250618(ProtocolVersion $protocolVersion): void
    {
        // Some clients pass only `structuredContent` to the model. Without the metadata it would take a dry run for a committed write.
        $written = [['entity' => 'tax', 'ids' => ['a'], 'operation' => 'upsert']];

        $result = $this->renderer()->render(McpToolResult::success($written, ['dryRun' => true, 'responseSize' => 80]), $protocolVersion);

        static::assertSame(['result' => $written, '_meta' => ['dryRun' => true]], $result->structuredContent, 'client bookkeeping such as `responseSize` is left out');
        static::assertSame(Json::encode($written), $this->text($result->content[0]), 'the text copy for clients that read only text stays');
        static::assertSame('{"_meta":{"dryRun":true,"responseSize":80}}', $this->text($result->content[1]));
    }

    #[DataProvider('legacyRevisionProvider')]
    public function testSendsNoStructuredContentForTheMetadataBefore20250618(ProtocolVersion $protocolVersion): void
    {
        $result = $this->renderer()->render(McpToolResult::success([['id' => 'a']], ['dryRun' => true]), $protocolVersion);

        static::assertNull($result->structuredContent);
        static::assertSame('{"success":true,"data":[{"id":"a"}],"_meta":{"dryRun":true}}', $this->text($result->content[0]));
    }

    #[DataProvider('specRevisionProvider')]
    public function testAddsTheSummaryToTheMetadataInStructuredContent(ProtocolVersion $protocolVersion): void
    {
        $result = $this->renderer()->render(McpToolResult::success(['a' => 1], summary: 'One product'), $protocolVersion);

        static::assertSame(['a' => 1, '_meta' => ['summary' => 'One product']], $result->structuredContent);
    }

    public function testKeepsNumericKeysOfTheDataNextToTheMetadata(): void
    {
        $result = $this->renderer()->render(McpToolResult::success([5 => 'a'], ['total' => 1]), ProtocolVersion::latestHandshake());

        static::assertSame([5 => 'a', '_meta' => ['total' => 1]], $result->structuredContent);
    }

    public function testAddsTheMetadataToAJsonObject(): void
    {
        $data = new \stdClass();
        $data->id = 'a';

        $result = $this->renderer()->render(McpToolResult::success($data, ['total' => 1]), ProtocolVersion::V2026_07_28);

        static::assertSame(['id' => 'a', '_meta' => ['total' => 1]], $result->structuredContent);
    }

    public function testLeavesDataAloneThatHasAMetaKeyOfItsOwn(): void
    {
        $result = $this->renderer()->render(McpToolResult::success(['_meta' => 'own'], ['total' => 1]), ProtocolVersion::latestHandshake());

        static::assertSame(['_meta' => 'own'], $result->structuredContent);
    }

    public function testSendsOnlyTheDataWithoutMetadataForTheModel(): void
    {
        $result = $this->renderer()->render(McpToolResult::success([1, 2], ['responseSize' => 5]), ProtocolVersion::V2026_07_28);

        static::assertSame([1, 2], $result->structuredContent);
    }

    #[DataProvider('specRevisionProvider')]
    public function testSendsExactlyTheDataOfAToolWithAnOutputSchema(ProtocolVersion $protocolVersion): void
    {
        $result = $this->renderer()->render(McpToolResult::success(['id' => 'a'], ['dryRun' => true], 'Done'), $protocolVersion, hasOutputSchema: true);

        static::assertSame(['id' => 'a'], $result->structuredContent, 'the schema describes the data, not the metadata');
        static::assertSame('{"_meta":{"dryRun":true}}', $this->text($result->content[2]));
    }

    #[DataProvider('specRevisionProvider')]
    public function testRendersFailureAsIsErrorWithAStructuredErrorObject(ProtocolVersion $protocolVersion): void
    {
        $result = $this->renderer()->render(McpToolResult::failure('Missing privilege: product:read', McpToolError::MISSING_PRIVILEGE), $protocolVersion);

        static::assertTrue($result->isError);
        static::assertSame(['error' => ['code' => 'missing_privilege', 'message' => 'Missing privilege: product:read']], $result->structuredContent);
        static::assertSame('Missing privilege: product:read', $this->text($result->content[0]));
    }

    public function testKeepsErrorDetailsInTheStructuredErrorObject(): void
    {
        $result = $this->renderer()->render(McpToolResult::failure('Invalid', 'invalid_request', ['field' => 'email']), ProtocolVersion::latestHandshake());

        static::assertSame(['error' => ['code' => 'invalid_request', 'message' => 'Invalid', 'details' => ['field' => 'email']]], $result->structuredContent);
    }

    /**
     * @return iterable<string, array{mixed, mixed}>
     */
    public static function nonObjectDataProvider(): iterable
    {
        yield 'a list' => [[['id' => 'a']], ['result' => [['id' => 'a']]]];
        yield 'an empty array' => [[], ['result' => []]];
        yield 'a scalar' => [42, ['result' => 42]];
    }

    #[DataProvider('nonObjectDataProvider')]
    public function testWrapsNonObjectDataForTheHandshakeEra(mixed $data, mixed $expected): void
    {
        $result = $this->renderer()->render(McpToolResult::success($data), ProtocolVersion::latestHandshake());

        static::assertSame($expected, $result->structuredContent);
    }

    public function testKeepsAnEmptyJsonObjectAsIs(): void
    {
        $data = new \stdClass();

        $result = $this->renderer()->render(McpToolResult::success($data), ProtocolVersion::latestHandshake());

        static::assertSame($data, $result->structuredContent);
    }

    public function testSendsListsUnwrappedFromTheStatelessEraOn(): void
    {
        $result = $this->renderer()->render(McpToolResult::success([1, 2]), ProtocolVersion::V2026_07_28);

        static::assertSame([1, 2], $result->structuredContent);
    }

    public function testSendsNoStructuredContentWithoutData(): void
    {
        $result = $this->renderer()->render(McpToolResult::success(null), ProtocolVersion::latestHandshake());

        static::assertNull($result->structuredContent);
    }

    public function testSendsTheMetadataOfAResultWithoutData(): void
    {
        $result = $this->renderer()->render(new McpToolResult(meta: ['dryRun' => true], summary: 'Nothing written.'), ProtocolVersion::latestHandshake());

        static::assertSame(['result' => null, '_meta' => ['dryRun' => true, 'summary' => 'Nothing written.']], $result->structuredContent);
    }

    public function testSendsNoStructuredContentForALinkedResult(): void
    {
        $result = $this->renderer()->render($this->linkedResult(), ProtocolVersion::latestHandshake(), '{"success":true,"data":null}');

        static::assertNull($result->structuredContent);
    }

    public function testKeepsBothCopiesOfALargeResult(): void
    {
        // The spec asks for the data twice. A large result is stored as a whole, never sent with one copy only.
        $data = ['blob' => str_repeat('x', 60_000)];

        $result = $this->renderer()->render(McpToolResult::success($data), ProtocolVersion::latestHandshake());

        static::assertSame($data, $result->structuredContent);
        static::assertStringContainsString('"blob"', $this->text($result->content[0]));
    }

    public function testCarriesTheTimestampsOfTheResult(): void
    {
        $result = $this->renderer()->render(
            new McpToolResult(data: ['a' => 1], generatedAt: new \DateTimeImmutable('2026-09-01T08:00:00+00:00'), expiresAt: new \DateTimeImmutable('2026-09-02T08:00:00+00:00')),
            ProtocolVersion::latestHandshake(),
        );

        static::assertSame(['shopware/generatedAt' => '2026-09-01T08:00:00+00:00', 'shopware/expiresAt' => '2026-09-02T08:00:00+00:00'], $result->meta);
    }

    public function testBuildsTheLegacyEnvelopeWithMetaWhenTheToolReturnedAnObject(): void
    {
        $result = $this->renderer()->render(McpToolResult::success(['a' => 1], ['total' => 1]), ProtocolVersion::V2025_03_26);

        static::assertSame('{"success":true,"data":{"a":1},"_meta":{"total":1}}', $this->text($result->content[0]));
    }

    public function testMcpFormatSendsPlainDataAndPrefixedMeta(): void
    {
        $result = $this->renderer()->render(McpToolResult::success(['a' => 1], ['total' => 1]), ProtocolVersion::latestHandshake(), '{"success":true,"data":{"a":1}}');

        static::assertSame('{"a":1}', $this->text($result->content[0]));
        static::assertSame('{"_meta":{"total":1}}', $this->text($result->content[1]), 'hints written for the model stay in the text');
        static::assertCount(2, $result->content);
        static::assertSame(['shopware/total' => 1, 'shopware/generatedAt' => self::NOW], $result->meta);
    }

    public function testMcpFormatKeepsTheDataFirstAddsTheSummaryAndSendsErrorsAsTheirMessage(): void
    {
        $success = $this->renderer()->render(McpToolResult::success(['a' => 1], summary: 'One product'), ProtocolVersion::latestHandshake());
        static::assertSame('{"a":1}', $this->text($success->content[0]), 'the summary never replaces the data');
        static::assertSame('One product', $this->text($success->content[1]));
        static::assertCount(2, $success->content);

        $failure = $this->renderer()->render(McpToolResult::failure('Not found'), ProtocolVersion::latestHandshake());
        static::assertSame('Not found', $this->text($failure->content[0]));
        static::assertCount(1, $failure->content);

        $rejected = $this->renderer()->render(McpToolResult::failure('Invalid cart', McpToolError::INVALID_ARGUMENTS, ['violations' => ['quantity']]), ProtocolVersion::latestHandshake());
        static::assertSame('Invalid cart', $this->text($rejected->content[0]));
        static::assertSame('{"details":{"violations":["quantity"]}}', $this->text($rejected->content[1]), 'the model needs the details to fix its call');
    }

    public function testMcpFormatSendsNoDataBlockForAResultWithoutData(): void
    {
        $result = $this->renderer()->render(new McpToolResult(summary: 'Stored as a resource.'), ProtocolVersion::latestHandshake());

        static::assertSame('Stored as a resource.', $this->text($result->content[0]));
        static::assertCount(1, $result->content);
    }

    public function testMcpFormatSendsTheMetadataOfAFailure(): void
    {
        $result = $this->renderer()->render(new McpToolResult(error: new McpToolError('Rejected'), meta: ['dryRun' => true]), ProtocolVersion::latestHandshake());

        static::assertSame('Rejected', $this->text($result->content[0]));
        static::assertSame('{"_meta":{"dryRun":true}}', $this->text($result->content[1]));
    }

    public function testRendersALinkAsResourceLinkAfterTheSummary(): void
    {
        $result = $this->renderer()->render($this->linkedResult(), ProtocolVersion::latestHandshake(), '{"success":true,"data":null}');

        static::assertCount(2, $result->content);
        static::assertSame('Too large to return inline.', $this->text($result->content[0]));

        $link = $result->content[1];
        static::assertInstanceOf(ResourceLink::class, $link);
        static::assertSame('shopware://tool-result/abc', $link->uri);
        static::assertSame('tool-result', $link->name);
        static::assertSame('application/json', $link->mimeType);
        static::assertSame(123456, $link->size);
        static::assertSame('2026-09-28T11:00:00+00:00', $result->meta['shopware/expiresAt'] ?? null);
    }

    public function testLeavesOutTheLinkForClientsThatPredateResourceLinks(): void
    {
        $result = $this->renderer()->render($this->linkedResult(), ProtocolVersion::V2025_03_26);

        static::assertCount(1, $result->content);
    }

    private function linkedResult(): McpToolResult
    {
        return new McpToolResult(
            summary: 'Too large to return inline.',
            expiresAt: new \DateTimeImmutable('2026-09-28T11:00:00+00:00'),
            links: [new McpToolResultLink('shopware://tool-result/abc', 'tool-result', 'Too large to return inline.', 'application/json', 123456)],
        );
    }

    private function renderer(): McpToolResultRenderer
    {
        return new McpToolResultRenderer(new MockClock(self::NOW), static::createStub(ToolResultCacheStorage::class), new RequestStack(), new NullLogger());
    }

    private function text(mixed $content): string
    {
        static::assertInstanceOf(TextContent::class, $content);
        static::assertIsString($content->text);

        return $content->text;
    }
}
