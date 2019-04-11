<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\VersionTransformation;

interface ApiVersionTransformation
{
    public static function getVersion(): int;
}
