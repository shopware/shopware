<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Uuid;

use Ramsey\Uuid\Uuid as RamseyUuid;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;

class Uuid
{
    /**
     * Regular expression pattern for matching a valid UUID of any variant.
     */
    public const VALID_PATTERN = '^[0-9A-Fa-f]{8}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{12}$';

    public static function randomHex(): string
    {
        return RamseyUuid::uuid4()->getHex();
    }

    public static function randomBytes(): string
    {
        return RamseyUuid::uuid4()->getBytes();
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     */
    public static function fromBytesToHex(string $bytes): string
    {
        if (\strlen($bytes) !== 16) {
            throw new InvalidUuidLengthException(\strlen($bytes), bin2hex($bytes));
        }
        $uuid = bin2hex($bytes);

        if (!self::isValid($uuid)) {
            throw new InvalidUuidException($uuid);
        }

        return $uuid;
    }

    /**
     * @throws InvalidUuidException
     */
    public static function fromHexToBytes(string $uuid): string
    {
        $uuid = strtolower($uuid);
        if ($bin = @hex2bin(str_replace('-', '', $uuid))) {
            return $bin;
        }

        throw new InvalidUuidException($uuid);
    }

    public static function isValid(string $id): bool
    {
        if (!preg_match('/' . self::VALID_PATTERN . '/', $id)) {
            return false;
        }

        return true;
    }
}
