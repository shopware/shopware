<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

/**
 * Flattens or expands arrays by concatenating string keys
 */
class ArrayNormalizer
{
    public static function flatten(iterable $input): array
    {
        $result = [];
        foreach ($input as $key => $value) {
            if (is_iterable($value)) {
                foreach (self::flatten($value) as $innerKey => $innerValue) {
                    $result[$key . '.' . $innerKey] = $innerValue;
                }

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    public static function expand(iterable $input): array
    {
        $result = [];
        foreach ($input as $key => $value) {
            if (is_string($key) && mb_strpos($key, '.') !== false) {
                $first = mb_strstr($key, '.', true);
                $rest = mb_strstr($key, '.');
                if (isset($result[$first])) {
                    $result[$first] = array_merge_recursive($result[$first], self::expand([mb_substr($rest, 1) => $value]));
                } else {
                    $result[$first] = self::expand([mb_substr($rest, 1) => $value]);
                }
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
