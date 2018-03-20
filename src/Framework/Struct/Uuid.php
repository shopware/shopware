<?php declare(strict_types=1);

namespace Shopware\Framework\Struct;

use Ramsey\Uuid\UuidInterface;

class Uuid
{
    /**
     * Regular expression pattern for matching a valid UUID of any variant.
     */
    const VALID_PATTERN = '^[0-9A-Fa-f]{8}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{4}-?[0-9A-Fa-f]{12}$';

    public static function uuid4(): UuidInterface
    {
        // TODO@all create our own Uuid object and do not expose the ramsey object
        return \Ramsey\Uuid\Uuid::uuid4();
    }

    public static function fromBytesToHex(string $bytes): string
    {
        return strtolower(bin2hex($bytes));
    }

    public static function fromBytesToString(string $bytes): string
    {
        return strtolower(bin2hex($bytes));
    }

    public static function fromStringToBytes(string $uuid): string
    {
        return hex2bin(self::optimize($uuid));
    }

    public static function fromStringToHex(string $uuid): string
    {
        return self::optimize($uuid);
    }

    public static function fromHexToBytes(string $hex): string
    {
        return hex2bin(strtolower($hex));
    }

    public static function fromHexToString(string $hex): string
    {
        return \Ramsey\Uuid\Uuid::fromString($hex)->toString();
    }

    public static function isValid($id): bool
    {
        if (!preg_match('/' . self::VALID_PATTERN . '/', $id)) {
            return false;
        }

        return true;
    }

    public static function optimize(string $id): string
    {
        return str_replace('-', '', strtolower($id));
    }
}
