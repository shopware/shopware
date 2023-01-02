<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class FloatComparator
{
    private const EPSILON = 0.00000001;

    public static function cast(float $a): float
    {
        return (float) (string) $a;
    }

    public static function equals(float $a, float $b): bool
    {
        return abs($a - $b) < self::EPSILON;
    }

    public static function lessThan(float $a, float $b): bool
    {
        return $a - $b < -self::EPSILON;
    }

    public static function greaterThan(float $a, float $b): bool
    {
        return $b - $a < -self::EPSILON;
    }

    public static function lessThanOrEquals(float $a, float $b): bool
    {
        return $a - $b < self::EPSILON;
    }

    public static function greaterThanOrEquals(float $a, float $b): bool
    {
        return $b - $a < self::EPSILON;
    }

    public static function notEquals(float $a, float $b): bool
    {
        return !static::equals($a, $b);
    }
}
