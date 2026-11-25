<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Result\McpToolResultStoredFormat;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultStoredFormat::class)]
class McpToolResultStoredFormatTest extends TestCase
{
    public function testTheLegacyFormatIsTheColumnDefault(): void
    {
        // Rows written before the format was recorded carry the column default and must read as the envelope.
        static::assertSame(McpToolResultStoredFormat::Legacy, McpToolResultStoredFormat::from('application/json'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function storedMimeTypeProvider(): iterable
    {
        yield 'the legacy envelope' => [McpToolResultStoredFormat::Legacy->value, 'application/json'];
        yield 'the plain data of the MCP format' => [McpToolResultStoredFormat::Spec->value, 'application/json'];
        yield 'a media type of another caller of the storage' => ['text/plain', 'text/plain'];
    }

    #[DataProvider('storedMimeTypeProvider')]
    public function testServesEveryFormatAsJsonAndAnyOtherMediaTypeAsItIs(string $storedMimeType, string $servedMimeType): void
    {
        static::assertSame($servedMimeType, McpToolResultStoredFormat::servedMimeType($storedMimeType));
    }

    public function testEveryFormatIsServedAsJson(): void
    {
        foreach (McpToolResultStoredFormat::cases() as $format) {
            static::assertSame('application/json', $format->mimeType());
        }
    }
}
