<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Util\ExternalMediaUrl;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ExternalMediaUrl::class)]
class ExternalMediaUrlTest extends TestCase
{
    #[DataProvider('strictExternalUrlProvider')]
    public function testAcceptsStrictExternalUrl(string $url): void
    {
        static::assertTrue(ExternalMediaUrl::isStrict($url));
    }

    public static function strictExternalUrlProvider(): \Generator
    {
        yield 'https url' => ['https://cdn.example.com/photo/2024/sunflowers.jpg'];
        yield 'http url with port and query' => ['http://localhost:8000/media/photo.jpg?width=200'];
        yield 'dots inside a file name are no dot segments' => ['https://cdn.example.com/photo..final.jpg'];
    }

    #[DataProvider('rejectedUrlProvider')]
    public function testRejectsUrlThatIsNoStrictExternalUrl(string $url): void
    {
        static::assertFalse(ExternalMediaUrl::isStrict($url));
    }

    public static function rejectedUrlProvider(): \Generator
    {
        yield 'relative path is a local file' => ['media/ab/cd/foo.png'];
        yield 'scheme without slashes is no url' => ['https:document/invoice.pdf'];
        yield 'upper case scheme is not recognized as external by the filesystem guards' => ['HTTPS://cdn.example.com/photo.jpg'];
        yield 'newline after the scheme is not recognized as external by the filesystem guards' => ["http://\ncdn.example.com/photo.jpg"];
        yield 'dot-dot segments resolve to a local file' => ['https://cdn.example.com/../../document/invoice.pdf'];
        yield 'single dot segment is normalized by the filesystem' => ['https://cdn.example.com/./photo.jpg'];
        yield 'backslashes are normalized to separators' => ['https://cdn.example.com\..\..\document\invoice.pdf'];
        yield 'percent encoded dot segments could be decoded later' => ['https://cdn.example.com/%2E%2E/document/invoice.pdf'];
        yield 'url without host' => ['https:///photo.jpg'];
    }
}
