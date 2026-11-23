<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentInlineMappingParseBatchRequest;
use Shopware\Core\Framework\ContentSystem\Api\ContentInlineMappingParseController;
use Shopware\Core\Framework\ContentSystem\Api\ContentInlineMappingParseRequest;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentInlineMappingParseController::class)]
class ContentInlineMappingParseControllerTest extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('parseResponseProvider')]
    public function testBatchPreservesTokenSpansRevisionsAndDuplicateEntries(string $text, int $revision, array $expected): void
    {
        $controller = new ContentInlineMappingParseController(new InlineMappingTokenParser());
        $request = new ContentInlineMappingParseRequest($text, $revision);

        $response = $controller->parseBatch(new ContentInlineMappingParseBatchRequest([
            $request,
            new ContentInlineMappingParseRequest('Plain text', 0),
            $request,
        ]));

        static::assertSame(200, $response->getStatusCode());
        static::assertSame(['results' => [$expected, ['revision' => 0, 'tokens' => []], $expected]], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testBatchReportsOversizedTextWithoutRejectingOtherFields(): void
    {
        $controller = new ContentInlineMappingParseController(new InlineMappingTokenParser());
        $exception = ContentSystemException::inlineMappingInputTooLarge(InlineMappingTokenParser::MAX_INPUT_BYTES);

        $response = $controller->parseBatch(new ContentInlineMappingParseBatchRequest([
            new ContentInlineMappingParseRequest('Before', 1),
            new ContentInlineMappingParseRequest('{{map:product.name}}' . str_repeat('x', InlineMappingTokenParser::MAX_INPUT_BYTES), 2),
            new ContentInlineMappingParseRequest('After', 3),
        ]));

        static::assertSame(['results' => [
            ['revision' => 1, 'tokens' => []],
            ['revision' => 2, 'error' => ['code' => $exception->getErrorCode(), 'message' => $exception->getMessage()]],
            ['revision' => 3, 'tokens' => []],
        ]], json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR));
    }

    public function testBatchPropagatesEngineFailuresInsteadOfReturningPartialResults(): void
    {
        $controller = new ContentInlineMappingParseController(new InlineMappingTokenParser());
        $payload = new ContentInlineMappingParseBatchRequest([
            new ContentInlineMappingParseRequest('Before', 1),
            new ContentInlineMappingParseRequest('{{map:product.name}} {{map:' . str_repeat('x', 500) . '}}', 18),
        ]);
        $previousLimit = ini_get('pcre.backtrack_limit');
        static::assertNotFalse($previousLimit);
        static::assertNotFalse(ini_set('pcre.backtrack_limit', '100'));

        try {
            $this->expectExceptionObject(ContentSystemException::inlineMappingParsingFailed('scan tokens in', 'Backtrack limit exhausted'));

            $controller->parseBatch($payload);
        } finally {
            static::assertNotFalse(ini_set('pcre.backtrack_limit', $previousLimit));
        }
    }

    /**
     * @return iterable<string, array{string, int, array<string, mixed>}>
     */
    public static function parseResponseProvider(): iterable
    {
        yield 'ReturnsServerParsedTokenSpansAndEchoesTheRevision' => ['<p>🙂 Buy {{ map:product.name }}.</p>',
            17, [
                'revision' => 17,
                'tokens' => [[
                    'path' => 'product.name',
                    'match' => '{{ map:product.name }}',
                    'canonicalToken' => '{{map:product.name}}',
                    'offset' => 12,
                    'insideMarkup' => false,
                    'malformed' => false,
                ]],
            ]];
        yield 'ForwardsTheInsideMarkupFlagForAttributeTokens' => ['<a title="{{ map:product.name }}">', 6, [
            'revision' => 6,
            'tokens' => [[
                'path' => 'product.name',
                'match' => '{{ map:product.name }}',
                'canonicalToken' => '{{map:product.name}}',
                'offset' => 10,
                'insideMarkup' => true,
                'malformed' => false,
            ]],
        ]];
        yield 'MarksMalformedMappingMarkersForTheEditorToLeaveAsText' => ['Buy {{map:product}} today', 4, [
            'revision' => 4,
            'tokens' => [[
                'path' => 'product',
                'match' => '{{map:product}}',
                'canonicalToken' => null,
                'offset' => 4,
                'insideMarkup' => false,
                'malformed' => true,
            ]],
        ]];
        yield 'MarksMarkupBearingPathAsMalformed' => ['{{map:x" onmouseover="alert(1)}}', 5, [
            'revision' => 5,
            'tokens' => [[
                'path' => 'x" onmouseover="alert(1)',
                'match' => '{{map:x" onmouseover="alert(1)}}',
                'canonicalToken' => null,
                'offset' => 0,
                'insideMarkup' => false,
                'malformed' => true,
            ]],
        ]];
    }
}
