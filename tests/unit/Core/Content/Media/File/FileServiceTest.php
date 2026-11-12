<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileService;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FileService::class)]
class FileServiceTest extends TestCase
{
    #[DataProvider('urlDataProvider')]
    public function testIsUrl(string $url, bool $expectedResult): void
    {
        $fileService = new FileService(['http', 'https', 'ftp']);

        static::assertSame($expectedResult, $fileService->isUrl($url));
    }

    public function testIsUrlRejectsProtocolMissingFromConfiguration(): void
    {
        static::assertFalse((new FileService(['https']))->isUrl('ftp://example.com'));
    }

    public static function urlDataProvider(): \Generator
    {
        yield 'http protocol' => ['http://example.com', true];
        yield 'https protocol' => ['https://example.com', true];
        yield 'ftp protocol' => ['ftp://example.com', true];
        yield 'uppercase protocol' => ['HTTPS://example.com', true];
        yield 'sftp protocol' => ['sftp://example.com', false];
        yield 'unsupported protocol' => ['file://example.com', false];
        yield 'no protocol' => ['example.com', false];
        yield 'no url' => ['no url', false];
    }
}
