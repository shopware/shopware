<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\DeviceBoundSession;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\DeviceBoundSession\DeviceBoundSessionHeader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeviceBoundSessionHeader::class)]
class DeviceBoundSessionHeaderTest extends TestCase
{
    #[DataProvider('itemProvider')]
    public function testParseItem(?string $header, ?string $expected): void
    {
        static::assertSame($expected, DeviceBoundSessionHeader::parseItem($header));
    }

    /**
     * @return iterable<string, array{0: ?string, 1: ?string}>
     */
    public static function itemProvider(): iterable
    {
        yield 'missing header has no item' => [null, null];
        yield 'empty header has no item' => ['  ', null];
        yield 'quoted string is unquoted' => ['"session-id"', 'session-id'];
        yield 'parameters of a quoted string are ignored' => ['"eyJ.eyJ.sig";foo="bar"', 'eyJ.eyJ.sig'];
        yield 'escaped quotes are unescaped' => ['"a\"b\\\\c"', 'a"b\c'];
        yield 'unterminated string is rejected' => ['"session-id', null];
        yield 'bare token is accepted' => ['eyJ.eyJ.sig', 'eyJ.eyJ.sig'];
        yield 'parameters of a bare token are ignored' => ['eyJ.eyJ.sig; foo=1', 'eyJ.eyJ.sig'];
    }

    public function testSerializedStringRoundTrips(): void
    {
        $serialized = DeviceBoundSessionHeader::serializeString('a"b\c');

        static::assertSame('"a\"b\\\\c"', $serialized);
        static::assertSame('a"b\c', DeviceBoundSessionHeader::parseItem($serialized));
    }
}
