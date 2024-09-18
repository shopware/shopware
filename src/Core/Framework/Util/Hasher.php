<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class Hasher
{
    public const ALGO = 'xxh128';

    public static function hash(mixed $data, string $algo = self::ALGO, bool $binary = false): string
    {
        if (!\is_string($data)) {
            $data = \json_encode($data, \JSON_THROW_ON_ERROR);
        }

        return \hash($algo, $data, $binary);
    }

    public static function hash_file(string $filename, string $algo = self::ALGO, bool $binary = false): string|false
    {
        return \hash_file($algo, $filename, $binary);
    }
}
