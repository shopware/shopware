<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

/** This class is based on Rand.php of Component_ZendMath
 *
 * @see      https://github.com/zendframework/zf2/blob/master/library/Zend/Math/Rand.php
 * @see      https://github.com/ircmaxell/RandomLib
 */
#[Package('core')]
class Random
{
    public static function getBytes(int $length): string
    {
        if ($length <= 0) {
            throw new \DomainException('Length should be >= 1');
        }

        return random_bytes($length);
    }

    public static function getBoolean(): bool
    {
        $byte = static::getBytes(1);

        return (bool) (\ord($byte) % 2);
    }

    public static function getInteger(int $min, int $max): int
    {
        if ($min > $max) {
            throw new \DomainException(
                'The min parameter must be lower than max parameter'
            );
        }

        return random_int($min, $max);
    }

    public static function getString(int $length, ?string $charlist = null): string
    {
        if ($length < 1) {
            throw new \DomainException('Length should be >= 1');
        }

        // charlist is empty or not provided
        if (empty($charlist)) {
            $numBytes = ceil($length * 0.75);
            $bytes = static::getBytes((int) $numBytes);

            return mb_substr(rtrim(base64_encode($bytes), '='), 0, $length, '8bit');
        }

        $listLen = mb_strlen($charlist, '8bit');

        if ($listLen === 1) {
            return str_repeat($charlist, $length);
        }

        $result = '';
        for ($i = 0; $i < $length; ++$i) {
            $pos = static::getInteger(0, $listLen - 1);
            $result .= $charlist[$pos];
        }

        return $result;
    }

    /**
     * @see https://tools.ietf.org/html/rfc4648
     */
    public static function getBase64UrlString(int $length): string
    {
        $numBytes = ceil($length * 0.75);
        $bytes = static::getBytes((int) $numBytes);

        $base64 = mb_substr(rtrim(base64_encode($bytes), '='), 0, $length, '8bit');

        return str_replace(['+', '/'], ['-', '_'], $base64);
    }

    public static function getAlphanumericString(int $length): string
    {
        $charlist = implode('', range('a', 'z')) . implode('', range('A', 'Z')) . implode('', range(0, 9));

        return static::getString($length, $charlist);
    }

    public static function getRandomArrayElement(array $array)
    {
        return $array[self::getInteger(0, \count($array) - 1)];
    }
}
