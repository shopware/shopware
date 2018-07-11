<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Ramsey\Uuid\UuidInterface;
use Shopware\Core\Framework\Exception\InvalidUuidException;
use Shopware\Core\Framework\Exception\InvalidUuidLengthException;

class Uuid
{
    /**
     * Regular expression pattern for matching a valid UUID of any variant.
     */
    public const VALID_PATTERN = '^[0-9A-Fa-f]{8}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{12}$';

    public static function uuid4(): UuidInterface
    {
        // TODO@all create our own Uuid object and do not expose the ramsey object - NEXT-251
        return \Ramsey\Uuid\Uuid::uuid4();
    }

    /**
     * @throws InvalidUuidException
     * @throws InvalidUuidLengthException
     */
    public static function fromBytesToHex(string $bytes): string
    {
        if (strlen($bytes) !== 16) {
            throw new InvalidUuidLengthException(strlen($bytes), bin2hex($bytes));
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
    public static function fromStringToBytes(string $uuid): string
    {
        if ($bin = @hex2bin(str_replace('-', '', $uuid))) {
            return $bin;
        }

        throw new InvalidUuidException($uuid);
    }

    /**
     * @throws InvalidUuidException
     */
    public static function fromHexToBytes(string $hex): string
    {
        if ($bin = @hex2bin($hex)) {
            return $bin;
        }

        throw new InvalidUuidException($hex);
    }

    public static function isValid($id): bool
    {
        if (!preg_match('/' . self::VALID_PATTERN . '/', $id)) {
            return false;
        }

        return true;
    }
}
