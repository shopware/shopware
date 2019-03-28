<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Struct;

use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;
use Shopware\Core\Framework\Uuid\Uuid as CoreUuid;

/**
 * @deprecated use Shopware\Core\Framework\Uuid\Uuid4Converter instead
 */
class Uuid extends CoreUuid
{
    /**
     * @deprecated use Shopware\Core\Framework\Uuid\Uuid4Converter::random*** instead
     */
    public static function uuid4(): UuidInterface
    {
        return RamseyUuid::uuid4();
    }
}
