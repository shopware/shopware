<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Uuid;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class UuidTest extends TestCase
{
    public function testRandomHex(): void
    {
        static::assertNotEquals(Uuid::randomHex(), Uuid::randomHex());
        static::assertTrue(Uuid::isValid(Uuid::randomHex()));
        static::assertStringNotContainsString('-', Uuid::randomHex());
    }

    public function testRandomBytes(): void
    {
        static::assertNotEquals(Uuid::randomBytes(), Uuid::randomBytes());
        static::assertSame(16, mb_strlen(Uuid::randomBytes(), '8bit'));
    }

    public function testHexRoundtrip(): void
    {
        $hex = Uuid::randomHex();
        $bytes = Uuid::fromHexToBytes($hex);

        static::assertSame($hex, Uuid::fromBytesToHex($bytes));
    }

    public function testBytesRoundtrip(): void
    {
        $bytes = Uuid::randomBytes();
        $hex = Uuid::fromBytesToHex($bytes);

        static::assertSame($bytes, Uuid::fromHexToBytes($hex));
    }

    public function testFromBytesToHexThrowsOnInvalidLength(): void
    {
        $this->expectException(InvalidUuidLengthException::class);
        Uuid::fromBytesToHex('a');
    }

    public function testValidity(): void
    {
        static::assertTrue(Uuid::isValid('bd5303139e5e47c68eeda68746b73436'));
        static::assertTrue(Uuid::isValid('1111aaabbbfff1111111111ccc111111'));

        static::assertTrue(Uuid::isValid('11111111111111111111111111111111'));
        static::assertFalse(Uuid::isValid('G1111111111111111111111111111111'));
        static::assertFalse(Uuid::isValid('g1111111111111111111111111111111'));

        static::assertFalse(Uuid::isValid('1111111111111111111111111111111'));
        static::assertFalse(Uuid::isValid('111111111111111111111111111111111'));

        static::assertFalse(Uuid::isValid('!1111111111111111111111111111111'));

        static::assertFalse(Uuid::isValid('1111aaabbbFFF1111111111CCC111111'));
        static::assertFalse(Uuid::isValid('74d25156-60e6-444c-a177-a96e67ecfc5f'));
    }

    public function testUuidFormat(): void
    {
        for ($i = 0; $i < 100; ++$i) {
            $uuid = Uuid::randomHex();
            static::assertSame(32, mb_strlen($uuid));
            // uuid 4 is mostly random except the version is at pos 13 and pos 17 is either 8, 9, a or b
            static::assertSame('4', $uuid[12]);
            static::assertContains($uuid[16], ['8', '9', 'a', 'b']);
            static::assertTrue($uuid === mb_strtolower($uuid));
        }
    }
}
