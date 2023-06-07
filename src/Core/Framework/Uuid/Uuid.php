<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Uuid;

use Ramsey\Uuid\BinaryUtils;
use Ramsey\Uuid\Generator\RandomGeneratorFactory;
use Ramsey\Uuid\Generator\UnixTimeGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;

#[Package('core')]
class Uuid
{
    /**
     * Regular expression pattern for matching a valid UUID of any variant.
     */
    final public const VALID_PATTERN = '^[0-9a-f]{32}$';

    private static ?UnixTimeGenerator $generator = null;

    public static function randomHex(): string
    {
        return bin2hex(self::randomBytes());
    }

    /**
     * same as Ramsey\Uuid\UuidFactory->uuidFromBytesAndVersion without using a transfer object
     */
    public static function randomBytes(): string
    {
        if (self::$generator === null) {
            self::$generator = new UnixTimeGenerator((new RandomGeneratorFactory())->getGenerator());
        }
        $bytes = self::$generator->generate();

        /** @var array<int> $unpackedTime */
        $unpackedTime = unpack('n*', substr($bytes, 6, 2));
        $timeHi = (int) $unpackedTime[1];
        $timeHiAndVersion = pack('n*', BinaryUtils::applyVersion($timeHi, 7));

        /** @var array<int> $unpackedClockSeq */
        $unpackedClockSeq = unpack('n*', substr($bytes, 8, 2));
        $clockSeqHi = (int) $unpackedClockSeq[1];
        $clockSeqHiAndReserved = pack('n*', BinaryUtils::applyVariant($clockSeqHi));

        $bytes = substr_replace($bytes, $timeHiAndVersion, 6, 2);

        return substr_replace($bytes, $clockSeqHiAndReserved, 8, 2);
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     */
    public static function fromBytesToHex(string $bytes): string
    {
        if (mb_strlen($bytes, '8bit') !== 16) {
            throw new InvalidUuidLengthException(mb_strlen($bytes, '8bit'), bin2hex($bytes));
        }
        $uuid = bin2hex($bytes);

        if (!self::isValid($uuid)) {
            throw new InvalidUuidException($uuid);
        }

        return $uuid;
    }

    public static function fromBytesToHexList(array $bytesList): array
    {
        $converted = [];
        foreach ($bytesList as $key => $bytes) {
            $converted[$key] = self::fromBytesToHex($bytes);
        }

        return $converted;
    }

    public static function fromHexToBytesList(array $uuids): array
    {
        $converted = [];
        foreach ($uuids as $key => $uuid) {
            $converted[$key] = self::fromHexToBytes($uuid);
        }

        return $converted;
    }

    /**
     * @throws InvalidUuidException
     */
    public static function fromHexToBytes(string $uuid): string
    {
        if ($bin = @hex2bin($uuid)) {
            return $bin;
        }

        throw new InvalidUuidException($uuid);
    }

    /**
     * Generates a md5 binary, to hash the string and returns a UUID in hex
     */
    public static function fromStringToHex(string $string): string
    {
        return self::fromBytesToHex(md5($string, true));
    }

    public static function isValid(string $id): bool
    {
        if (!preg_match('/' . self::VALID_PATTERN . '/', $id)) {
            return false;
        }

        return true;
    }
}
