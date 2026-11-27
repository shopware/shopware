<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\AiOrigin;

use Shopware\Core\Framework\Log\Package;

/**
 * Reads values one after another from binary data, like a file pointer.
 * Integers are unsigned and big endian unless the reader is created for little endian data.
 *
 * Reading past the end never fails: strings are cut short and integers are 0.
 * Where a length comes from the data, callers compare it with {@see self::remaining()} first.
 *
 * @internal
 *
 * @experimental stableVersion:v6.8.0 feature:MEDIA_AI_ORIGIN
 */
#[Package('discovery')]
final class ByteReader
{
    public function __construct(
        private readonly string $data,
        private int $position = 0,
        private readonly bool $littleEndian = false,
    ) {
    }

    /**
     * Calls `$readEntry` for one entry after another, until the data ends or `$readEntry` returns `false`.
     * It also stops when `$readEntry` does not move forward, so broken data cannot loop forever.
     *
     * @param \Closure(self): bool $readEntry reads one entry, returns `false` to stop
     */
    public function readEach(\Closure $readEntry): void
    {
        while ($this->remaining() > 0) {
            $positionBefore = $this->position;

            if (!$readEntry($this) || $this->position <= $positionBefore) {
                return;
            }
        }
    }

    /**
     * Collects the entries that `$readEntry` returns, until the data ends or it returns `null`.
     *
     * @template T
     *
     * @param \Closure(self): (T|null) $readEntry reads one entry
     *
     * @return list<T>
     */
    public function readAll(\Closure $readEntry): array
    {
        $entries = [];

        $this->readEach(static function (self $reader) use ($readEntry, &$entries): bool {
            $positionBefore = $reader->position;
            $entry = $readEntry($reader);

            if ($entry === null || $reader->position <= $positionBefore) {
                return false;
            }

            $entries[] = $entry;

            return true;
        });

        return $entries;
    }

    /**
     * Reads one box of the ISO base media file format, the structure of AVIF/HEIF files and of the JUMBF boxes of C2PA:
     * a 4 byte size including the header (1: an 8 byte size follows, 0: up to the end), a 4 byte type, then the payload.
     *
     * @return array{string, string}|null type and payload, `null` if the box is broken or cut off
     */
    public function readIsoBox(): ?array
    {
        $boxSize = $this->readUint32();
        $type = $this->readString(4);
        $headerSize = 8;

        if ($boxSize === 1) {
            $boxSize = $this->readUint64();
            $headerSize = 16;
        } elseif ($boxSize === 0) {
            $boxSize = $headerSize + $this->remaining();
        }

        if ($boxSize < $headerSize || $boxSize - $headerSize > $this->remaining()) {
            return null;
        }

        return [$type, $this->readString($boxSize - $headerSize)];
    }

    public function remaining(): int
    {
        return max(0, \strlen($this->data) - $this->position);
    }

    public function skip(int $length): void
    {
        $this->position += $length;
    }

    /**
     * Finds the first text from `$start` up to and including `$end` anywhere in the data, without moving.
     * For example XMP, which is plain text in every image format: `findEnclosed('<x:xmpmeta', '</x:xmpmeta>')`.
     */
    public function findEnclosed(string $start, string $end): ?string
    {
        $startPosition = strpos($this->data, $start);
        $endPosition = $startPosition === false ? false : strpos($this->data, $end, $startPosition);

        if ($startPosition === false || $endPosition === false) {
            return null;
        }

        return substr($this->data, $startPosition, $endPosition - $startPosition + \strlen($end));
    }

    /**
     * Moves past `$expected` if the data continues with it, for example a signature or a magic number.
     */
    public function skipIfNext(string $expected): bool
    {
        if ($this->peek(\strlen($expected)) !== $expected) {
            return false;
        }

        $this->skip(\strlen($expected));

        return true;
    }

    /**
     * Returns the next bytes without moving forward.
     */
    public function peek(int $length): string
    {
        return substr($this->data, $this->position, $length);
    }

    public function readString(int $length): string
    {
        $string = $this->peek($length);
        $this->position += $length;

        return $string;
    }

    /**
     * Reads up to the next null byte and moves past it.
     * Returns `null` and does not move when there is no null byte.
     */
    public function readNullTerminatedString(): ?string
    {
        $end = strpos($this->data, "\0", $this->position);
        if ($end === false) {
            return null;
        }

        $string = $this->readString($end - $this->position);
        $this->skip(1); // the null byte

        return $string;
    }

    public function readRest(): string
    {
        return $this->readString($this->remaining());
    }

    public function readByte(): int
    {
        return $this->readInteger(1, 'C', 'C');
    }

    public function readUint16(): int
    {
        return $this->readInteger(2, 'n', 'v');
    }

    public function readUint32(): int
    {
        return $this->readInteger(4, 'N', 'V');
    }

    public function readUint64(): int
    {
        return $this->readInteger(8, 'J', 'P');
    }

    private function readInteger(int $length, string $bigEndianFormat, string $littleEndianFormat): int
    {
        $bytes = $this->readString($length);
        if (\strlen($bytes) < $length) {
            return 0;
        }

        $value = unpack($this->littleEndian ? $littleEndianFormat : $bigEndianFormat, $bytes);

        return \is_array($value) && \is_int($value[1] ?? null) ? $value[1] : 0;
    }
}
