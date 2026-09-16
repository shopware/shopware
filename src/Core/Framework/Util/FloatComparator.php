<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class FloatComparator
{
    private const EPSILON = 0.00000001;

    public static function compare(float $a, float $b, string $operator): bool
    {
        return match ($operator) {
            '!=' => self::notEquals($a, $b),
            '>=' => self::greaterThanOrEquals($a, $b),
            '<=' => self::lessThanOrEquals($a, $b),
            '=' => self::equals($a, $b),
            '>' => self::greaterThan($a, $b),
            '<' => self::lessThan($a, $b),
            default => throw UtilException::operatorNotSupported($operator),
        };
    }

    public static function cast(float $a): float
    {
        return (float) (string) $a;
    }

    /**
     * Sums the given floats, snapping a near-zero residual (e.g. -7.1E-15 from prices that cancel
     * out to zero) to an exact 0.0, which {@see self::cast()} alone cannot do.
     *
     * @param array<float> $values
     */
    public static function sum(array $values): float
    {
        $sum = self::cast(array_sum($values));

        return self::equals($sum, 0.0) ? 0.0 : $sum;
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
