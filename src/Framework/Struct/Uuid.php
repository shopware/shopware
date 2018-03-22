<?php

namespace Shopware\Framework\Struct;

use Ramsey\Uuid\UuidInterface;

class Uuid
{
    public static function uuid4(): UuidInterface
    {
        return \Ramsey\Uuid\Uuid::uuid4();
    }

    public static function fromBytesToHex(string $bytes): string
    {
        return strtolower(bin2hex($bytes));
    }

    public static function fromBytesToString(string $bytes): string
    {
        return \Ramsey\Uuid\Uuid::fromBytes($bytes)->toString();
    }

    public static function fromStringToBytes(string $uuid): string
    {
        return \Ramsey\Uuid\Uuid::fromString($uuid)->getBytes();
    }

    public static function fromStringToHex(string $uuid): string
    {
        return \Ramsey\Uuid\Uuid::fromString($uuid)->getHex();
    }

    public static function fromHexToBytes(string $hex): string
    {
        return \Ramsey\Uuid\Uuid::fromString($hex)->getBytes();
    }

    public static function fromHexToString(string $hex): string
    {
        return \Ramsey\Uuid\Uuid::fromString($hex)->toString();
    }

    public static function isValid($id): bool
    {
        $id = \Ramsey\Uuid\Uuid::fromString($id)->toString();

        return \Ramsey\Uuid\Uuid::isValid($id);
    }
}