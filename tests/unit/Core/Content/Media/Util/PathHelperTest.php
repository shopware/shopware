<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Util\PathHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(PathHelper::class)]
class PathHelperTest extends TestCase
{
    #[DataProvider('controlAndFormatCharsProvider')]
    public function testItStripsControlAndFormatChars(string $input, string $expected): void
    {
        $output = PathHelper::stripControlAndFormatChars($input);

        static::assertSame($expected, $output);
    }

    public static function controlAndFormatCharsProvider(): \Generator
    {
        yield ['#Föö' . "\u{200B}" . 'bár', '#Fööbár'];
        yield ["#Foo\tBar\nBaz", '#FooBarBaz'];
        yield ['#Föö🚀bár', '#Föö🚀bár'];
        yield ['#Foo' . "\u{202E}" . 'Bar', '#FooBar'];
        yield ['#Föö­bár', '#Fööbár'];
    }

    #[DataProvider('nonAsciiAndControlCharsProvider')]
    public function testItStripsNonAsciiAndControlChars(string $input, string $expected): void
    {
        $output = PathHelper::stripNonAsciiAndControlChars($input);

        static::assertSame($expected, $output);
    }

    public static function nonAsciiAndControlCharsProvider(): \Generator
    {
        yield ['#Fööbár', '#Fbr'];
        yield ['#Foo🚀Bar', '#FooBar'];
        yield ["#Foo\tBar\nBaz", '#FooBarBaz'];
        yield ['#Föo-123_bár!', '#Fo-123_br!'];
        yield ['#Foo-Bar_123', '#Foo-Bar_123'];
        yield ['#ääööüübär🚀', '#br'];
    }

    #[DataProvider('urlCredentialsProvider')]
    public function testItStripsCredentialsFromUrls(string $input, string $expected): void
    {
        static::assertSame($expected, PathHelper::stripCredentials($input));
    }

    public static function urlCredentialsProvider(): \Generator
    {
        yield 'user and password are removed' => ['ftp://user:s3cret@ftp.example.com/file.jpg', 'ftp://ftp.example.com/file.jpg'];
        yield 'user without password is removed' => ['https://user@example.com/file.jpg', 'https://example.com/file.jpg'];
        yield 'password containing an at sign is removed' => ['sftp://user:p@ss@sftp.example.com/file.jpg', 'sftp://sftp.example.com/file.jpg'];
        yield 'at sign in the path is kept' => ['https://example.com/user@domain.jpg', 'https://example.com/user@domain.jpg'];
        yield 'at sign in the query is kept' => ['https://example.com/file.jpg?mail=user@domain.com', 'https://example.com/file.jpg?mail=user@domain.com'];
        yield 'local file path is kept' => ['/var/www/media/user@domain.jpg', '/var/www/media/user@domain.jpg'];
    }
}
