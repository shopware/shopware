<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;
use Shopware\Core\Framework\Mcp\Result\McpToolResultParser;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultParser::class)]
class McpToolResultParserTest extends TestCase
{
    public function testParsesASuccessEnvelopeWithMeta(): void
    {
        $result = (new McpToolResultParser())->parse('{"success":true,"data":{"id":"a","tags":{}},"_meta":{"total":3}}');

        static::assertInstanceOf(McpToolResult::class, $result);
        static::assertFalse($result->isError());
        static::assertInstanceOf(\stdClass::class, $result->data);
        static::assertInstanceOf(\stdClass::class, $result->data->tags, 'an empty JSON object must stay an object');
        static::assertSame(['total' => 3], $result->meta);
    }

    public function testKeepsKeysNextToTheEnvelopeFieldsAsMetadata(): void
    {
        // The shape of agentic-commerce's UCP previews: the flags sit next to `data`, not in `_meta`.
        $result = (new McpToolResultParser())->parse('{"success":true,"data":{"id":"cart"},"dryRun":true,"preview":true,"_meta":{"total":1,"dryRun":false}}');

        static::assertInstanceOf(McpToolResult::class, $result);
        static::assertSame(['total' => 1, 'dryRun' => false, 'preview' => true], $result->meta, '`_meta` wins over a key of the same name');

        $failure = (new McpToolResultParser())->parse('{"success":false,"error":"Rejected","code":"invalid_arguments","dryRun":true}');
        static::assertInstanceOf(McpToolResult::class, $failure);
        static::assertSame(['dryRun' => true], $failure->meta);
        static::assertSame('invalid_arguments', $failure->error?->code);
    }

    public function testParsesASuccessEnvelopeWithoutData(): void
    {
        $result = (new McpToolResultParser())->parse('{"success":true}');

        static::assertInstanceOf(McpToolResult::class, $result);
        static::assertNull($result->data);
        static::assertSame([], $result->meta);
    }

    public function testParsesAStringErrorWithItsCode(): void
    {
        $result = (new McpToolResultParser())->parse('{"success":false,"error":"Missing privilege: product:read","code":"missing_privilege"}');

        static::assertInstanceOf(McpToolResult::class, $result);
        static::assertTrue($result->isError());
        static::assertEquals(new McpToolError('Missing privilege: product:read', McpToolError::MISSING_PRIVILEGE), $result->error);
    }

    public function testAStringErrorWithoutCodeGetsTheGenericCode(): void
    {
        $result = (new McpToolResultParser())->parse('{"success":false,"error":"Entity \"x\" not found."}');

        static::assertSame(McpToolError::TOOL_ERROR, $result?->error?->code);
    }

    public function testParsesAStructuredErrorObject(): void
    {
        $result = (new McpToolResultParser())->parse('{"success":false,"error":{"type":"validation","message":"Plain http is not allowed.","code":"invalid_request","severity":"recoverable"}}');

        static::assertEquals(
            new McpToolError('Plain http is not allowed.', 'invalid_request', ['type' => 'validation', 'severity' => 'recoverable']),
            $result?->error,
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function incompleteErrorProvider(): iterable
    {
        yield 'error object without message and code' => ['{"success":false,"error":{"type":"validation"}}', McpToolError::TOOL_ERROR];
        yield 'error of an unexpected type' => ['{"success":false,"error":42,"code":"not_found"}', McpToolError::NOT_FOUND];
    }

    #[DataProvider('incompleteErrorProvider')]
    public function testFallsBackToAGenericMessage(string $text, string $expectedCode): void
    {
        $result = (new McpToolResultParser())->parse($text);

        static::assertSame('The tool call failed.', $result?->error?->message);
        static::assertSame($expectedCode, $result->error->code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notAnEnvelopeProvider(): iterable
    {
        yield 'plain text' => ['Hello'];
        yield 'JSON without success' => ['{"data":[]}'];
        yield 'success that is not a boolean' => ['{"success":"yes"}'];
        yield 'a JSON list' => ['[{"success":true}]'];
    }

    #[DataProvider('notAnEnvelopeProvider')]
    public function testLeavesOtherStringsAlone(string $text): void
    {
        static::assertNull((new McpToolResultParser())->parse($text));
    }
}
