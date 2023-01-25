<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class FetchModeHelper
{
    /**
     * User-land implementation of PDO::FETCH_KEY_PAIR
     */
    public static function keyPair(array $result): array
    {
        $firstRow = current($result);
        if (empty($firstRow)) {
            return $result;
        }

        [$keyName, $valueName] = array_keys($firstRow);

        return array_combine(
            array_column($result, $keyName),
            array_column($result, $valueName)
        );
    }

    /**
     * User-land implementation of PDO::FETCH_GROUP
     */
    public static function group(array $result): array
    {
        $firstRow = current($result);
        if (empty($firstRow)) {
            return $result;
        }

        $dataKeys = array_keys($firstRow);
        $groupKey = array_shift($dataKeys);

        $rows = [];
        foreach ($result as $row) {
            $index = $row[$groupKey];
            unset($row[$groupKey]);

            $rows[$index][] = $row;
        }

        return $rows;
    }

    /**
     * User-land implementation of PDO::FETCH_GROUP|PDO::FETCH_UNIQUE
     */
    public static function groupUnique(array $result): array
    {
        $firstRow = current($result);
        if (empty($firstRow)) {
            return $result;
        }

        $dataKeys = array_keys($firstRow);
        $groupKey = array_shift($dataKeys);

        $rows = [];
        foreach ($result as $row) {
            $index = $row[$groupKey];
            unset($row[$groupKey]);

            $rows[$index] = $row;
        }

        return $rows;
    }
}
