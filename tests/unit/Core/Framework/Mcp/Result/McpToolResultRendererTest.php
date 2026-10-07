<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use Mcp\Schema\Content\ResourceLink;
use Mcp\Schema\Content\TextContent;
use Mcp\Schema\Enum\ProtocolVersion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Result\McpToolResultLink;
use Shopware\Core\Framework\Mcp\Result\McpToolResultRenderer;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Clock\MockClock;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultRenderer::class)]
class McpToolResultRendererTest extends TestCase
{
    private const NOW = '2026-09-28T10:00:00+00:00';

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testRendersSuccessWithTheLegacyTextAndStructuredData(): void
    {
        $legacy = '{"success":true,"data":{"id":"a"}}';

        $result = $this->renderer()->render(McpToolResult::success(['id' => 'a']), ProtocolVersion::latestHandshake(), $legacy);

        static::assertFalse($result->isError);
        static::assertSame(['id' => 'a'], $result->structuredContent);
        static::assertSame($legacy, $this->text($result->content[0]), 'existing readers must see exactly the string the tool returned');
        static::assertSame(['shopware/generatedAt' => self::NOW], $result->meta);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testRendersFailureAsIsErrorWithAStructuredErrorObject(): void
    {
        $result = $this->renderer()->render(
            McpToolResult::failure('Missing privilege: product:read', McpToolError::MISSING_PRIVILEGE),
            ProtocolVersion::latestHandshake(),
        );

        static::assertTrue($result->isError);
        static::assertSame(['error' => ['code' => 'missing_privilege', 'message' => 'Missing privilege: product:read']], $result->structuredContent);
        static::assertSame('{"success":false,"error":"Missing privilege: product:read","code":"missing_privilege"}', $this->text($result->content[0]));
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

    public function testKeepsBothCopiesOfALargeResult(): void
    {
        // The spec asks for the data twice; large results are offloaded before rendering, not cut here.
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

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testBuildsTheLegacyEnvelopeWithMetaWhenTheToolReturnedAnObject(): void
    {
        $result = $this->renderer()->render(McpToolResult::success(['a' => 1], ['total' => 1]), ProtocolVersion::latestHandshake());

        static::assertSame('{"success":true,"data":{"a":1},"_meta":{"total":1}}', $this->text($result->content[0]));
    }

    public function testSpecOnlyModeSendsPlainDataAndPrefixedMeta(): void
    {
        $result = $this->renderer()->render(McpToolResult::success(['a' => 1], ['total' => 1]), ProtocolVersion::latestHandshake(), '{"success":true,"data":{"a":1}}');

        static::assertSame('{"a":1}', $this->text($result->content[0]));
        static::assertSame('{"_meta":{"total":1}}', $this->text($result->content[1]), 'hints written for the model stay in the text');
        static::assertCount(2, $result->content);
        static::assertSame(['shopware/total' => 1, 'shopware/generatedAt' => self::NOW], $result->meta);
    }

    public function testSpecOnlyModeKeepsTheDataFirstAddsTheSummaryAndSendsErrorsAsTheirMessage(): void
    {
        $success = $this->renderer()->render(McpToolResult::success(['a' => 1], summary: 'One product'), ProtocolVersion::latestHandshake());
        static::assertSame('{"a":1}', $this->text($success->content[0]), 'the summary never replaces the data');
        static::assertSame('One product', $this->text($success->content[1]));
        static::assertCount(2, $success->content);

        $failure = $this->renderer()->render(McpToolResult::failure('Not found'), ProtocolVersion::latestHandshake());
        static::assertSame('Not found', $this->text($failure->content[0]));
        static::assertCount(1, $failure->content);
    }

    public function testSpecOnlyModeSendsNoDataBlockForAResultWithoutData(): void
    {
        $result = $this->renderer()->render(new McpToolResult(summary: 'Stored as a resource.'), ProtocolVersion::latestHandshake());

        static::assertSame('Stored as a resource.', $this->text($result->content[0]));
        static::assertCount(1, $result->content);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testRendersALinkAsResourceLinkAfterTheText(): void
    {
        $result = $this->renderer()->render($this->linkedResult(), ProtocolVersion::latestHandshake(), '{"success":true,"data":null}');

        static::assertCount(2, $result->content);
        static::assertInstanceOf(TextContent::class, $result->content[0]);
        static::assertSame('{"success":true,"data":null}', $result->content[0]->text);

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

    public function testSpecOnlyModeSendsTheSummaryNextToTheLink(): void
    {
        $result = $this->renderer()->render($this->linkedResult(), ProtocolVersion::latestHandshake());

        static::assertInstanceOf(TextContent::class, $result->content[0]);
        static::assertSame('Too large to return inline.', $result->content[0]->text);
        static::assertInstanceOf(ResourceLink::class, $result->content[1]);
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
        return new McpToolResultRenderer(new MockClock(self::NOW));
    }

    private function text(mixed $content): string
    {
        static::assertInstanceOf(TextContent::class, $content);
        static::assertIsString($content->text);

        return $content->text;
    }
}
