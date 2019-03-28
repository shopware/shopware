<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Uuid;

use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;

class Uuid4Converter
{
    /**
     * Regular expression pattern for matching a valid UUID of any variant.
     */
    public const VALID_PATTERN = '^[0-9A-Fa-f]{8}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{12}$';

    public static function uuid4(): Uuid4Value
    {
        return Uuid4Value::random();
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
    public static function fromStringToBytes(string $uuid): string
    {
        $uuid = strtolower($uuid);
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
        $hex = strtolower($hex);
        if ($bin = @hex2bin($hex)) {
            return $bin;
        }

        throw new InvalidUuidException($hex);
    }

    public static function isValid(string $id): bool
    {
        if (!preg_match('/' . self::VALID_PATTERN . '/', $id)) {
            return false;
        }

        return true;
    }

    /**
     * @throws InvalidUuidLengthException
     */
    public static function fromHexToString(string $hex): string
    {
        if (\strlen($hex) !== 32) {
            throw new InvalidUuidLengthException(\strlen($hex), $hex);
        }

        return \substr($hex, 0, 8)
            . '-'
            . \substr($hex, 8, 4)
            . '-'
            . \substr($hex, 12, 4)
            . '-'
            . \substr($hex, 16, 4)
            . '-'
            . \substr($hex, 20);
    }
}
