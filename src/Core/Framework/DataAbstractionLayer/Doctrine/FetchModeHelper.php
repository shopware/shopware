<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class FetchModeHelper
{
    /**
     * User-land implementation of PDO::FETCH_KEY_PAIR
     *
     * @param array<array<string>> $result
     *
     * @return array<string, string>
     */
    public static function keyPair(array $result): array
    {
        $firstRow = current($result);
        if (empty($firstRow)) {
            return [];
        }

        [$keyName, $valueName] = array_keys($firstRow);

        return array_combine(
            array_column($result, $keyName),
            array_column($result, $valueName)
        );
    }

    /**
     * User-land implementation of PDO::FETCH_GROUP
     *
     * @phpstan-template CReturn
     *
     * @param list<array<string, string|null>> $result
     * @param callable(array<string, string|null>):CReturn|null $mapper
     *
     * @return ($mapper is callable ? array<string, list<CReturn>> : array<string, list<array<string, string|null>>>)
     */
    public static function group(array $result, ?callable $mapper = null): array
    {
        $firstRow = current($result);
        if (empty($firstRow)) {
            return [];
        }

        $dataKeys = array_keys($firstRow);
        $groupKey = array_shift($dataKeys);

        $rows = [];
        foreach ($result as $row) {
            $index = $row[$groupKey];
            unset($row[$groupKey]);

            $rows[$index][] = $mapper ? $mapper($row) : $row;
        }

        return $rows;
    }

    /**
     * User-land implementation of PDO::FETCH_GROUP|PDO::FETCH_UNIQUE
     *
     * @param list<array<string, string|null>> $result
     *
     * @return array<string, array<string, string|null>>
     */
    public static function groupUnique(array $result): array
    {
        $firstRow = current($result);
        if (empty($firstRow)) {
            return [];
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
