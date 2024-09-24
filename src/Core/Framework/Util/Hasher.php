<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class Hasher
{
    public const ALGO = 'xxh128';

    /**
     * @return string the generated hash, **note** that the hashing is not cryptographically secure and should not be used for security purposes
     */
    public static function hash(mixed $data, string $algo = self::ALGO): string
    {
        if (!\is_string($data)) {
            $data = \json_encode($data, \JSON_THROW_ON_ERROR);
        }

        return \hash($algo, $data);
    }

    /**
     * @return string the generated binary hash, **note** that the hashing is not cryptographically secure and should not be used for security purposes
     */
    public static function hashBinary(string $data, string $algo = self::ALGO): string
    {
        return \hash($algo, $data, true);
    }

    /**
     * @return string the generated hash, **note** that the hashing is not cryptographically secure and should not be used for security purposes
     */
    public static function hashFile(string $filename, string $algo = self::ALGO): string
    {
        $hash = \hash_file($algo, $filename);

        if ($hash === false) {
            throw UtilException::couldNotHashFile($filename);
        }

        return $hash;
    }
}
