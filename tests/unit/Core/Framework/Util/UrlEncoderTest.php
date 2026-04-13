<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
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

    public function testItHandlesRelativePaths(): void
    {
        static::assertSame(
            '../media/file%20name.jpg',
            UrlEncoder::encodeUrl('../media/file name.jpg')
        );
    }
}
