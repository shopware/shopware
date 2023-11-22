<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\DatabaseDiff;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('core')]
class DbState
{
    private const IGNORED = [
        'version_commit',
        'version_commit_data',
    ];

    /**
     * @var array<string, int>
     */
    public array $tableCounts = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function rememberCurrentDbState(): void
    {
        $tables = $this->connection->fetchAllAssociative('SHOW TABLES');

        $stateResult = [];
        foreach ($tables as $nested) {
            $tableName = (string) end($nested);

            $count = $this->connection->fetchOne('SELECT COUNT(*) FROM `' . $tableName . '`');

            $stateResult[$tableName] = (int) $count;
        }

        $this->tableCounts = $stateResult;
    }

    /**
     * @return array<string, array<int|string, int>>
     */
    public function getDiff(): array
    {
        $previousCounts = $this->tableCounts;

        $this->rememberCurrentDbState();

        $diff = [];

        /** @var array<string, int> $addedTables */
        $addedTables = array_diff(array_keys($this->tableCounts), array_keys($previousCounts));
        if ($addedTables) {
            $diff['added'] = array_values($addedTables);
        }

        /** @var array<string, int> $deletedTables */
        $deletedTables = array_diff(array_keys($previousCounts), array_keys($this->tableCounts));
        if ($deletedTables) {
            $diff['deleted'] = array_values($deletedTables);
        }

        $commonTables = array_intersect(array_keys($previousCounts), array_keys($this->tableCounts));

        $changed = [];
        /** @var string $table */
        foreach ($commonTables as $table) {
            $countDiff = $this->tableCounts[$table] - $previousCounts[$table];

            if ($countDiff !== 0 && !\in_array($table, self::IGNORED, true)) {
                $changed[$table] = $countDiff;
            }
        }

        if ($changed) {
            $diff['changed'] = $changed;
        }

        return $diff;
    }
}
