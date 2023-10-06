<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Uuid;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidLengthException;

#[Package('core')]
class UuidException extends HttpException
{
    public static function invalidUuid(string $uuid): ShopwareHttpException
    {
        return new InvalidUuidException($uuid);
    }

    public static function invalidUuidLength(int $length, string $hex): ShopwareHttpException
    {
        return new InvalidUuidLengthException($length, $hex);
    }
}
