<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Uuid;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;
use Shopware\Core\Framework\Uuid\Uuid;

class UuidTest extends TestCase
{
    public function testRandomHex()
    {
        static::assertNotEquals(Uuid::randomHex(), Uuid::randomHex());
        static::assertTrue(Uuid::isValid(Uuid::randomHex()));
        static::assertStringNotContainsString('-', Uuid::randomHex());
    }

    public function testRandomBytes()
    {
        static::assertNotEquals(Uuid::randomBytes(), Uuid::randomBytes());
        static::assertSame(16, \strlen(Uuid::randomBytes()));
    }

    public function testHexRoundtrip()
    {
        $hex = Uuid::randomHex();
        $bytes = Uuid::fromHexToBytes($hex);

        static::assertSame($hex, Uuid::fromBytesToHex($bytes));
    }

    public function testBytesRoundtrip()
    {
        $bytes = Uuid::randomBytes();
        $hex = Uuid::fromBytesToHex($bytes);

        static::assertSame($bytes, Uuid::fromHexToBytes($hex));
    }

    public function testFromBytesToHexThrowsOnInvalidLength()
    {
        $this->expectException(InvalidUuidLengthException::class);
        Uuid::fromBytesToHex('a');
    }
}
