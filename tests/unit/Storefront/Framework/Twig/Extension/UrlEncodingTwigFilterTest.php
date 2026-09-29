<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Params\UrlParams;
use Shopware\Core\Content\Media\Infrastructure\Path\MediaUrlGenerator;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Twig\Extension\UrlEncodingTwigFilter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UrlEncodingTwigFilter::class)]
class UrlEncodingTwigFilterTest extends TestCase
{
    public function testHappyPath(): void
    {
        $filter = new UrlEncodingTwigFilter();
        $url = 'https://shopware.com:80/some/thing';
        static::assertEquals($url, $filter->encodeUrl($url));
    }

    public function testReturnsNullsIfNoUrlIsGiven(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertNull($filter->encodeUrl(null));
    }

    public function testItEncodesWithoutPort(): void
    {
        $filter = new UrlEncodingTwigFilter();
        $url = 'https://shopware.com/some/thing';
        static::assertEquals($url, $filter->encodeUrl($url));
    }

    public function testRespectsQueryParameter(): void
    {
        $filter = new UrlEncodingTwigFilter();
        $url = 'https://shopware.com/some/thing?a=3&b=25';
        static::assertEquals($url, $filter->encodeUrl($url));
    }

    public function testReturnsEncodedPathsWithoutHostAndScheme(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertEquals(
            'shopware.com/some/thing',
            $filter->encodeUrl('shopware.com/some/thing')
        );
    }

    public function testItEncodesSpaces(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertEquals(
            'https://shopware.com:80/so%20me/thing%20new.jpg',
            $filter->encodeUrl('https://shopware.com:80/so me/thing new.jpg')
        );
    }

    public function testItEncodesSpecialCharacters(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertEquals(
            'https://shopware.com:80/so%20me/thing%20new.jpg',
            $filter->encodeUrl('https://shopware.com:80/so me/thing new.jpg')
        );
    }

    public function testItReturnsNullIfMediaIsNull(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertNull($filter->encodeMediaUrl(null));
    }

    public function testNullIfNoMediaIsUploaded(): void
    {
        $filter = new UrlEncodingTwigFilter();
        $media = new MediaEntity();

        static::assertNull($filter->encodeMediaUrl($media));
    }

    public function testItEncodesTheUrl(): void
    {
        $filter = new UrlEncodingTwigFilter();

        $filesystem = new Filesystem(new InMemoryFilesystemAdapter(), ['public_url' => 'http://localhost:8000']);

        $urlGenerator = new MediaUrlGenerator($filesystem);
        $uploadTime = new \DateTime();

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setMimeType('image/png');
        $media->setFileExtension('png');
        $media->setUploadedAt($uploadTime);
        $media->setFileName('(image with spaces and brackets)');
        $media->setPath('(image with spaces and brackets).png');

        $urls = $urlGenerator->generate(['foo' => UrlParams::fromMedia($media)]);

        static::assertArrayHasKey('foo', $urls);
        $url = $urls['foo'];

        $media->setUrl((string) $url);

        static::assertStringEndsWith('%28image%20with%20spaces%20and%20brackets%29.png', (string) $filter->encodeMediaUrl($media));
    }

    public function testItEncodesUmlautsAndSpecialCharacters(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertSame(
            'https://shopware.com/path/%C3%A4%C3%B6%C3%BC%20test.jpg',
            $filter->encodeUrl('https://shopware.com/path/äöü test.jpg')
        );
    }

    public function testItHandlesComplexUrls(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertSame(
            'https://example.com:8080/path/with%20spaces/and%20%28brackets%29/file%20name.jpg?param=value&other=test',
            $filter->encodeUrl('https://example.com:8080/path/with spaces/and (brackets)/file name.jpg?param=value&other=test')
        );
    }

    public function testItHandlesUrlsWithOnlyPath(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertSame(
            '/media/folder/file%20with%20spaces.jpg',
            $filter->encodeUrl('/media/folder/file with spaces.jpg')
        );
    }

    public function testItReturnsEmptyStringForEmptyInput(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertSame('', $filter->encodeUrl(''));
    }

    public function testItHandlesUrlsWithoutFragment(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertSame(
            'https://shopware.com/path/file%20name.jpg',
            $filter->encodeUrl('https://shopware.com/path/file name.jpg#section')
        );
    }

    public function testItReturnsNullForMalformedUrls(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertNull($filter->encodeUrl('http://shopware.com:notaport/media/file.jpg'));
    }

    public function testItHandlesRelativePaths(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertSame(
            '../media/file%20name.jpg',
            $filter->encodeUrl('../media/file name.jpg')
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
        $filter = new UrlEncodingTwigFilter();
        static::assertSame($expected, $filter->encodeUrl($expected));
        static::assertSame($expected, $filter->encodeUrl($url));
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

    public function testItEncodesPercentSignsThatAreNotAnEscapeSequence(): void
    {
        $filter = new UrlEncodingTwigFilter();

        static::assertSame(
            'https://shopware.com/media/50%25.jpg',
            $filter->encodeUrl('https://shopware.com/media/50%.jpg')
        );

        static::assertSame(
            'https://shopware.com/media/a%252Gb.jpg',
            $filter->encodeUrl('https://shopware.com/media/a%2Gb.jpg')
        );
    }

    public function testItKeepsEncodedPathSeparatorsInsideASegment(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertSame(
            'https://cdn.example.com/media/object%2Fid%20%C3%84.jpg',
            $filter->encodeUrl('https://cdn.example.com/media/object%2Fid Ä.jpg')
        );
    }

    public function testItKeepsTheAuthorityUntouched(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertSame(
            'https://user:pass@shopware.com:8080/media/%C3%84.jpg',
            $filter->encodeUrl('https://user:pass@shopware.com:8080/media/Ä.jpg')
        );
    }

    public function testItKeepsProtocolRelativeUrls(): void
    {
        $filter = new UrlEncodingTwigFilter();
        static::assertSame(
            '//shopware.com/media/file%20name.jpg',
            $filter->encodeUrl('//shopware.com/media/file name.jpg')
        );
    }
}
