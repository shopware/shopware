<?php

declare(strict_types=1);

namespace Shopware\Core\Framework\Util\Database;

use Doctrine\DBAL\Schema\Index as DbalIndex;
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class Index
{
    /**
     * @param list<string> $columnNames
     */
    public function __construct(
        public string $name,
        public string $type,
        public array $columnNames = [],
    ) {
    }

    public static function createFromDbalIndex(DbalIndex $dbalIndex): self
    {
        return new Index(
            name: $dbalIndex->getObjectName()->getIdentifier()->getValue(),
            type: $dbalIndex->getType()->name,
            columnNames: array_values(array_map(
                static fn (IndexedColumn $column): string => $column->getColumnName()->getIdentifier()->getValue(),
                $dbalIndex->getIndexedColumns()
            )),
        );
    }

    /**
     * Checks if the index spans the given columns in the exact order.
     *
     * @param list<string> $columns
     */
    public function spansColumns(array $columns): bool
    {
        $indexColumns = $this->columnNames;

        foreach ($columns as $column) {
            $indexColumn = array_shift($indexColumns);

            if ($indexColumn === null || strcasecmp($indexColumn, $column) !== 0) {
                return false;
            }
        }

        return true;
    }
}
