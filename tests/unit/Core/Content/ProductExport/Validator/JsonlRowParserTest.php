<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ProductExport\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ProductExportException;
use Shopware\Core\Content\ProductExport\Validator\JsonlRowParser;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(JsonlRowParser::class)]
class JsonlRowParserTest extends TestCase
{
    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testParseReturnsDecodedRowsWithLineNumbers(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $parser = new JsonlRowParser();

        $rows = $parser->parse("{\"id\":\"first\"}\n{\"id\":\"second\",\"nested\":{\"foo\":\"bar\"}}\n");

        static::assertSame(
            [
                ['line' => 1, 'row' => ['id' => 'first']],
                ['line' => 2, 'row' => ['id' => 'second', 'nested' => ['foo' => 'bar']]],
            ],
            $rows
        );
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testParseSkipsEmptyLines(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $parser = new JsonlRowParser();

        $rows = $parser->parse("\n  \n{\"id\":\"first\"}\n\n\t\n{\"id\":\"second\"}\n");

        static::assertSame(
            [
                ['line' => 3, 'row' => ['id' => 'first']],
                ['line' => 6, 'row' => ['id' => 'second']],
            ],
            $rows
        );
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testParseThrowsExceptionForMalformedJsonlLine(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $parser = new JsonlRowParser();

        try {
            $parser->parse("{\"id\":\"first\"}\n{\"id\": }\n");
            static::fail('Expected exception was not thrown.');
        } catch (ProductExportException $exception) {
            static::assertSame(ProductExportException::JSONL_MALFORMED_LINE_EXCEPTION, $exception->getErrorCode());
            static::assertSame(['line' => 2], $exception->getParameters());
        }
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testParseThrowsExceptionWhenJsonlLineDoesNotDecodeToObject(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $parser = new JsonlRowParser();

        try {
            $parser->parse("{\"id\":\"first\"}\n\"second\"\n");
            static::fail('Expected exception was not thrown.');
        } catch (ProductExportException $exception) {
            static::assertSame(ProductExportException::JSONL_LINE_NOT_OBJECT_EXCEPTION, $exception->getErrorCode());
            static::assertSame('Each JSONL line must decode to an object.', $exception->getMessage());
            static::assertSame(['line' => 2], $exception->getParameters());
        }
    }
}
