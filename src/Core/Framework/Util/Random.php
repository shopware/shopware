<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

/** This class is based on Rand.php of Component_ZendMath
 *
 * @see      https://github.com/zendframework/zf2/blob/master/library/Zend/Math/Rand.php
 * @see      https://github.com/ircmaxell/RandomLib
 */
#[Package('framework')]
class Random
{
    /**
     * @param int<1, max> $length
     *
     * @return non-empty-string
     */
    public static function getBytes(int $length): string
    {
        if ($length < 1) {
            throw UtilException::lengthMustBeGreaterThanZero();
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
            throw UtilException::minMustNotBeGreaterThanMax();
        }

        return random_int($min, $max);
    }

    /**
     * @param int<1, max> $length
     *
     * @return non-empty-string
     */
    public static function getString(int $length, ?string $charlist = null): string
    {
        if ($length < 1) {
            throw UtilException::lengthMustBeGreaterThanZero();
        }

        // charlist is empty or not provided
        if (empty($charlist)) {
            /** @var int<1, max> $numBytes */
            $numBytes = (int) ceil($length * 0.75);
            $bytes = static::getBytes($numBytes);

            /** @var non-empty-string $result phpstan does not understand that some content of $bytes will remain */
            $result = mb_substr(rtrim(base64_encode($bytes), '='), 0, $length, '8bit');

            return $result;
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
        \assert($result !== '');

        return $result;
    }

    /**
     * @see https://tools.ietf.org/html/rfc4648
     *
     * @param int<1, max> $length
     *
     * @return non-empty-string
     */
    public static function getBase64UrlString(int $length): string
    {
        // getString without a charlist returns a base64 encoded string
        $base64 = static::getString($length);

        return str_replace(['+', '/'], ['-', '_'], $base64);
    }

    /**
     * @param int<1, max> $length
     *
     * @return non-empty-string
     */
    public static function getAlphanumericString(int $length): string
    {
        $charlist = implode('', range('a', 'z')) . implode('', range('A', 'Z')) . implode('', range(0, 9));

        return static::getString($length, $charlist);
    }

    /**
     * @param array<int, mixed> $array
     */
    public static function getRandomArrayElement(array $array): mixed
    {
        return $array[self::getInteger(0, \count($array) - 1)];
    }
}
