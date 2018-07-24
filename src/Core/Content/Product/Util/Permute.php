<?php

namespace Shopware\Core\Content\Product\Util;

class Permute
{
    public static function permute($arg): array
    {
        $array = \is_string($arg) ? str_split($arg) : $arg;

        if (\count($array) === 1) {
            return $array;
        }

        $result = [];
        foreach ($array as $key => $item) {
            $nested = self::permute(array_diff_key($array, [$key => $item]));

            foreach ($nested as $p) {
                $result[] = $item . ' ' . $p;
            }
        }

        return $result;
    }
}