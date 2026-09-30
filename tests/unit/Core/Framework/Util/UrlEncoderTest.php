<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\UrlEncoder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UrlEncoder::class)]
class UrlEncoderTest extends TestCase
{
    public function testHappyPath(): void
    {
        $url = 'https://shopware.com:80/some/thing';
        static::assertSame($url, UrlEncoder::encodeUrl($url));
    }

    public function testReturnsNullIfNoUrlIsGiven(): void
    {
        static::assertNull(UrlEncoder::encodeUrl(null));
    }

    public function testItEncodesWithoutPort(): void
    {
        $url = 'https://shopware.com/some/thing';
        static::assertSame($url, UrlEncoder::encodeUrl($url));
    }

    public function testRespectsQueryParameter(): void
    {
        $url = 'https://shopware.com/some/thing?a=3&b=25';
        static::assertSame($url, UrlEncoder::encodeUrl($url));
    }

    public function testReturnsEncodedPathsWithoutHostAndScheme(): void
    {
        static::assertSame(
            'shopware.com/some/thing',
            UrlEncoder::encodeUrl('shopware.com/some/thing')
        );
    }

    public function testItEncodesSpaces(): void
    {
        static::assertSame(
            'https://shopware.com:80/so%20me/thing%20new.jpg',
            UrlEncoder::encodeUrl('https://shopware.com:80/so me/thing new.jpg')
        );
    }

    public function testItEncodesSpecialCharacters(): void
    {
        static::assertSame(
            'https://shopware.com:80/so%20me/thing%20new.jpg',
            UrlEncoder::encodeUrl('https://shopware.com:80/so me/thing new.jpg')
        );
    }

    public function testItEncodesUmlautsAndSpecialCharacters(): void
    {
        static::assertSame(
            'https://shopware.com/path/%C3%A4%C3%B6%C3%BC%20test.jpg',
            UrlEncoder::encodeUrl('https://shopware.com/path/äöü test.jpg')
        );
    }

    public function testItHandlesComplexUrls(): void
    {
        static::assertSame(
            'https://example.com:8080/path/with%20spaces/and%20%28brackets%29/file%20name.jpg?param=value&other=test',
            UrlEncoder::encodeUrl('https://example.com:8080/path/with spaces/and (brackets)/file name.jpg?param=value&other=test')
        );
    }

    public function testItHandlesUrlsWithOnlyPath(): void
    {
        static::assertSame(
            '/media/folder/file%20with%20spaces.jpg',
            UrlEncoder::encodeUrl('/media/folder/file with spaces.jpg')
        );
    }

    public function testItReturnsEmptyStringForEmptyInput(): void
    {
        static::assertSame('', UrlEncoder::encodeUrl(''));
    }

    public function testItHandlesUrlsWithoutFragment(): void
    {
        static::assertSame(
            'https://shopware.com/path/file%20name.jpg',
            UrlEncoder::encodeUrl('https://shopware.com/path/file name.jpg#section')
        );
    }

    public function testItReturnsNullForMalformedUrls(): void
    {
        static::assertNull(UrlEncoder::encodeUrl('http://shopware.com:notaport/media/file.jpg'));
    }

    public function testItHandlesRelativePaths(): void
    {
        static::assertSame(
            '../media/file%20name.jpg',
            UrlEncoder::encodeUrl('../media/file name.jpg')
        );
    }

    /**
     * `parse_url()` replaces every byte the current libc reports as a control character with `_`.
     * On platforms where that covers 0x7F-0x9F it destroys the continuation bytes of these
     * characters, so the encoder must not rebuild the URL from `parse_url()` components.
     */
    #[DataProvider('nonAsciiFileNameProvider')]
    public function testItIdempotentEncodesNonAsciiFileNamesWithoutCorruption(string $url, string $expected): void
    {
        static::assertSame($expected, UrlEncoder::encodeUrl($expected));
        static::assertSame($expected, UrlEncoder::encodeUrl($url));
    }

    public static function nonAsciiFileNameProvider(): \Generator
    {
        yield 'uppercase umlauts and sharp s survive encoding' => [
            'https://shopware.com/media/Ärmel Öl Übung ß.jpg',
            'https://shopware.com/media/%C3%84rmel%20%C3%96l%20%C3%9Cbung%20%C3%9F.jpg',
        ];

        yield 'typographic punctuation and currency signs survive encoding' => [
            'https://shopware.com/media/Größe – „Zitat“ €.jpg',
            'https://shopware.com/media/Gr%C3%B6%C3%9Fe%20%E2%80%93%20%E2%80%9EZitat%E2%80%9C%20%E2%82%AC.jpg',
        ];

        yield 'uppercase accented latin characters survive encoding' => [
            'https://shopware.com/media/ÀÉÎÕÇ.jpg',
            'https://shopware.com/media/%C3%80%C3%89%C3%8E%C3%95%C3%87.jpg',
        ];

        yield 'cyrillic characters survive encoding' => [
            'https://shopware.com/media/Тест.jpg',
            'https://shopware.com/media/%D0%A2%D0%B5%D1%81%D1%82.jpg',
        ];

        yield 'multi byte characters survive encoding' => [
            'https://shopware.com/media/テスト.jpg',
            'https://shopware.com/media/%E3%83%86%E3%82%B9%E3%83%88.jpg',
        ];

        yield 'cache busting query is kept next to an encoded file name' => [
            'https://shopware.com/media/ab/cd/ef/Ärmel.jpg?ts=1755000000',
            'https://shopware.com/media/ab/cd/ef/%C3%84rmel.jpg?ts=1755000000',
        ];
    }

    #[DataProvider('nonAsciiPathProvider')]
    public function testEncodePathSegmentsEncodesRawPaths(string $path, string $expected): void
    {
        static::assertSame($expected, UrlEncoder::encodePathSegments($path));
    }

    public static function nonAsciiPathProvider(): \Generator
    {
        yield 'uppercase umlauts and sharp s' => [
            'media/ab/cd/Ärmel Öl ß.jpg',
            'media/ab/cd/%C3%84rmel%20%C3%96l%20%C3%9F.jpg',
        ];

        yield 'typographic punctuation and currency signs' => [
            'media/ab/cd/Größe – €.jpg',
            'media/ab/cd/Gr%C3%B6%C3%9Fe%20%E2%80%93%20%E2%82%AC.jpg',
        ];

        yield 'multi byte characters' => [
            'media/ab/cd/テスト.jpg',
            'media/ab/cd/%E3%83%86%E3%82%B9%E3%83%88.jpg',
        ];

        yield 'reserved characters are encoded' => [
            'media/ab/cd/a+b,c;d=e(f).jpg',
            'media/ab/cd/a%2Bb%2Cc%3Bd%3De%28f%29.jpg',
        ];
    }

    public function testItEncodesPercentSignsThatAreNotAnEscapeSequence(): void
    {
        static::assertSame(
            'https://shopware.com/media/50%25.jpg',
            UrlEncoder::encodeUrl('https://shopware.com/media/50%.jpg')
        );

        static::assertSame(
            'https://shopware.com/media/a%252Gb.jpg',
            UrlEncoder::encodeUrl('https://shopware.com/media/a%2Gb.jpg')
        );
    }

    public function testItKeepsTheAuthorityUntouched(): void
    {
        static::assertSame(
            'https://user:pass@shopware.com:8080/media/%C3%84.jpg',
            UrlEncoder::encodeUrl('https://user:pass@shopware.com:8080/media/Ä.jpg')
        );
    }

    public function testItKeepsProtocolRelativeUrls(): void
    {
        static::assertSame(
            '//shopware.com/media/file%20name.jpg',
            UrlEncoder::encodeUrl('//shopware.com/media/file name.jpg')
        );
    }

    public function testEncodePathSegmentsKeepsNonAsciiCharacters(): void
    {
        static::assertSame(
            'media/foo/%C3%84rmel%20bild.jpg',
            UrlEncoder::encodePathSegments('media/foo/Ärmel bild.jpg')
        );
    }

    public function testEncodePathSegmentsEncodesSpecialCharacters(): void
    {
        static::assertSame(
            'media/foo/my%20file.jpg',
            UrlEncoder::encodePathSegments('media/foo/my file.jpg')
        );
    }
}
