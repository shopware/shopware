<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\AiOrigin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\AiOrigin\ByteReader;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ByteReader::class)]
class ByteReaderTest extends TestCase
{
    public function testReadsBigEndianValuesOneAfterAnother(): void
    {
        $reader = new ByteReader("\x01\x02\x03\x00\x00\x00\x04\x00\x00\x00\x00\x00\x00\x00\x05ABCD");

        static::assertSame(1, $reader->readByte());
        static::assertSame(0x0203, $reader->readUint16());
        static::assertSame(4, $reader->readUint32());
        static::assertSame(5, $reader->readUint64());
        static::assertSame('AB', $reader->readString(2));
        static::assertSame(2, $reader->remaining());
        static::assertSame('CD', $reader->readRest());
        static::assertSame(0, $reader->remaining());
    }

    public function testReadsLittleEndianValues(): void
    {
        $reader = new ByteReader("\x03\x02\x04\x00\x00\x00\x05\x00\x00\x00\x00\x00\x00\x00", littleEndian: true);

        static::assertSame(0x0203, $reader->readUint16());
        static::assertSame(4, $reader->readUint32());
        static::assertSame(5, $reader->readUint64());
    }

    public function testStartsAtTheGivenPositionAndSkips(): void
    {
        $reader = new ByteReader('headerABCDEF', position: 6);
        $reader->skip(2);

        static::assertSame('CD', $reader->peek(2));
        static::assertSame('CD', $reader->readString(2));
    }

    public function testReadsNullTerminatedStrings(): void
    {
        $reader = new ByteReader("manifest\0rest");

        static::assertSame('manifest', $reader->readNullTerminatedString());
        static::assertSame('rest', $reader->readRest());
    }

    public function testWithoutNullByteDoesNotMove(): void
    {
        $reader = new ByteReader('no terminator');

        static::assertNull($reader->readNullTerminatedString());
        static::assertSame('no terminator', $reader->readRest());
    }

    public function testReadingPastTheEndCutsStringsShortAndYieldsZero(): void
    {
        $reader = new ByteReader("\x01\x02\x03");

        static::assertSame(0, $reader->readUint32());
        static::assertSame(0, $reader->remaining());
        static::assertSame('', $reader->readString(4));
        static::assertSame(0, $reader->readByte());
    }

    public function testReadEachCallsTheClosureUntilTheEndOrUntilItReturnsFalse(): void
    {
        $read = [];
        (new ByteReader('ABCD'))->readEach(static function (ByteReader $reader) use (&$read): bool {
            $read[] = $reader->readString(1);

            return $read !== ['A', 'B'];
        });

        static::assertSame(['A', 'B'], $read);

        $read = [];
        (new ByteReader('ABCD'))->readEach(static function (ByteReader $reader) use (&$read): bool {
            $read[] = $reader->readString(1);

            return true;
        });

        static::assertSame(['A', 'B', 'C', 'D'], $read);
    }

    public function testReadEachStopsWhenTheClosureDoesNotMoveForward(): void
    {
        $calls = 0;
        (new ByteReader('ABCD'))->readEach(static function (ByteReader $reader) use (&$calls): bool {
            ++$calls;

            return true;
        });

        static::assertSame(1, $calls);
    }

    public function testReadAllCollectsEntriesUntilTheEnd(): void
    {
        $entries = (new ByteReader("\x01A\x02BC"))->readAll(
            static fn (ByteReader $reader): string => $reader->readString($reader->readByte())
        );

        static::assertSame(['A', 'BC'], $entries);
    }

    public function testReadAllStopsWhenTheClosureReturnsNull(): void
    {
        $entries = (new ByteReader('ABCD'))->readAll(
            static fn (ByteReader $reader): ?string => ($byte = $reader->readString(1)) === 'C' ? null : $byte
        );

        static::assertSame(['A', 'B'], $entries);
    }

    public function testReadAllStopsWhenTheClosureDoesNotMoveForward(): void
    {
        $entries = (new ByteReader('ABCD'))->readAll(static fn (ByteReader $reader): string => $reader->peek(1));

        static::assertSame([], $entries);
    }

    public function testReadsIsoBoxes(): void
    {
        $data = pack('N', 12) . 'ftypavif' // regular box
            . pack('N', 1) . 'free' . pack('J', 18) . 'xy' // large box with an 8 byte size
            . pack('N', 0) . 'mdatto the end'; // box up to the end

        $boxes = (new ByteReader($data))->readAll(static fn (ByteReader $reader): ?array => $reader->readIsoBox());

        static::assertSame([['ftyp', 'avif'], ['free', 'xy'], ['mdat', 'to the end']], $boxes);
    }

    public function testIsoBoxLargerThanTheDataIsNull(): void
    {
        static::assertNull((new ByteReader(pack('N', 100) . 'mdatshort'))->readIsoBox());
        static::assertNull((new ByteReader(pack('N', 4) . 'mdat'))->readIsoBox());
    }

    public function testFindsEnclosedTextAnywhereWithoutMoving(): void
    {
        $reader = new ByteReader("binary\0<x:xmpmeta a=\"1\"></x:xmpmeta>more");

        static::assertSame('<x:xmpmeta a="1"></x:xmpmeta>', $reader->findEnclosed('<x:xmpmeta', '</x:xmpmeta>'));
        static::assertNull($reader->findEnclosed('<x:xmpmeta', '</missing>'));
        static::assertSame('binary', $reader->readString(6));
    }
}
