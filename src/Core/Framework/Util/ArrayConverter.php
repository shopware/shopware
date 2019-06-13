<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

/**
 * Flattens or expands arrays by concatenating string keys
 */
class ArrayConverter
{
    public static function flatten(array $input): array
    {
        $result = [];
        foreach ($input as $key => $value) {
            if (is_array($value)) {
                foreach (self::flatten($value) as $innerKey => $innerValue) {
                    $result[$key . '.' . $innerKey] = $innerValue;
                }
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    public static function expand(array $input): array
    {
        $result = [];
        foreach ($input as $key => $value) {
            if (is_string($key) && strpos($key, '.') !== false) {
                $first = strstr($key, '.', true);
                $rest = strstr($key, '.');
                if (isset($result[$first])) {
                    $result[$first] = array_merge($result[$first], self::expand([substr($rest, 1) => $value]));
                } else {
                    $result[$first] = self::expand([substr($rest, 1) => $value]);
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
