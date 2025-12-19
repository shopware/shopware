<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\Base64;
use Shopware\Core\Framework\Util\UtilException;

/**
 * @internal
 */
#[CoversClass(Base64::class)]
class Base64Test extends TestCase
{
    #[DataProvider('urlEncodeDecodeProvider')]
    public function testUrlEncodeAndDecode(string $input, string $expectedEncoded): void
    {
        $encoded = Base64::urlEncode($input);
        static::assertSame($expectedEncoded, $encoded);

        // Verify URL safety: no +, /, or = characters
        static::assertStringNotContainsString('+', $encoded);
        static::assertStringNotContainsString('/', $encoded);
        static::assertStringNotContainsString('=', $encoded);

        // Test decode
        $decoded = Base64::urlDecode($encoded);
        static::assertSame($input, $decoded);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function urlEncodeDecodeProvider(): iterable
    {
        yield 'simple string' => [
            'hello world',
            'aGVsbG8gd29ybGQ',
        ];

        yield 'string with special characters' => [
            'test+data/value=',
            'dGVzdCtkYXRhL3ZhbHVlPQ',
        ];

        yield 'empty string' => [
            '',
            '',
        ];

        yield 'unicode characters' => [
            'Héllo Wörld! 你好',
            'SMOpbGxvIFfDtnJsZCEg5L2g5aW9',
        ];

        yield 'long string' => [
            str_repeat('Lorem ipsum dolor sit amet, ', 10),
            'TG9yZW0gaXBzdW0gZG9sb3Igc2l0IGFtZXQsIExvcmVtIGlwc3VtIGRvbG9yIHNpdCBhbWV0LCBMb3JlbSBpcHN1bSBkb2xvciBzaXQgYW1ldCwgTG9yZW0gaXBzdW0gZG9sb3Igc2l0IGFtZXQsIExvcmVtIGlwc3VtIGRvbG9yIHNpdCBhbWV0LCBMb3JlbSBpcHN1bSBkb2xvciBzaXQgYW1ldCwgTG9yZW0gaXBzdW0gZG9sb3Igc2l0IGFtZXQsIExvcmVtIGlwc3VtIGRvbG9yIHNpdCBhbWV0LCBMb3JlbSBpcHN1bSBkb2xvciBzaXQgYW1ldCwgTG9yZW0gaXBzdW0gZG9sb3Igc2l0IGFtZXQsIA',
        ];

        yield 'special characters' => [
            "\xff\xfe\xfd",
            '__79',
        ];

        yield 'padding handling case 1' => [
            'a',
            'YQ',
        ];

        yield 'encode handling case 2' => [
            'ab',
            'YWI',
        ];
    }

    #[DataProvider('invalidBase64Provider')]
    public function testUrlDecodeWithInvalidInput(string $input): void
    {
        static::expectExceptionObject(UtilException::base64DecodingFailed());

        Base64::urlDecode($input);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBase64Provider(): iterable
    {
        yield 'invalid characters' => ['invalid!@#$%^&*()'];
        yield 'malformed base64' => ['ÄÖÜ'];
    }
}
