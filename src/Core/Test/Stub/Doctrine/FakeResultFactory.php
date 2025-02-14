<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\Doctrine;

use Doctrine\DBAL\Cache\ArrayResult;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
class FakeResultFactory
{
    /**
     * @param list<array<array-key, mixed>> $data
     */
    public static function createResult(array $data, Connection $connection): Result
    {
        $columns = isset($data[0]) ? array_keys($data[0]) : [];

        // columns in rows should be in the same order as in columns
        // keys in rows should be numeric
        $rows = array_map(function ($row) use ($columns) {
            return array_map(fn ($column) => $row[$column], $columns);
        }, $data);

        return new Result(
            new ArrayResult($columns, $rows),
            $connection,
        );
    }
}
