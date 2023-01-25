<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Util;

use Shopware\Core\Framework\Log\Package;

#[Package('core
Flattens or expands arrays by concatenating string keys')]
class ArrayNormalizer
{
    /**
     * @param iterable<mixed> $input
     *
     * @return array<mixed>
     */
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

    /**
     * @param iterable<mixed> $input
     *
     * @return array<mixed>
     */
    public static function expand(iterable $input): array
    {
        $result = [];
        foreach ($input as $key => $value) {
            if (\is_string($key) && str_contains($key, '.')) {
                $first = mb_strstr($key, '.', true);
                $rest = mb_strstr($key, '.');
                // occurrence of dot is checked in if clause, so `mb_strstr` can't return false and the assert should not cause an exception
                \assert(\is_string($rest));

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
