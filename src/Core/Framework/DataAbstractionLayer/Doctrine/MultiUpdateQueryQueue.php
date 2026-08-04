<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Log\Package;

/**
 * Collects single-row UPDATE payloads for one table and executes them as batched
 * CASE WHEN statements instead of one statement (round trip) per row.
 *
 * Rows updating the same set of columns are combined into one statement per chunk.
 * Additional equality conditions (e.g. the version id) can be applied to the whole
 * batch on execute. Adding a second update for the same key and column set replaces
 * the first one, matching the last-write-wins semantics of sequential updates.
 *
 * @internal
 *
 * @phpstan-type UpdateRow array{key: string, columns: array<string, string|int|float|null>}
 */
#[Package('framework')]
class MultiUpdateQueryQueue
{
    /**
     * @var array<string, array<string, UpdateRow>>
     */
    private array $updates = [];

    /**
     * @var int<1, max>
     */
    private readonly int $chunkSize;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table,
        private readonly string $keyColumn = 'id',
        int $chunkSize = 250,
    ) {
        if ($chunkSize < 1) {
            throw DataAbstractionLayerException::invalidChunkSize($chunkSize);
        }
        $this->chunkSize = $chunkSize;
    }

    /**
     * @param string $key value of the key column identifying the row, e.g. the binary primary key
     * @param array<string, string|int|float|null> $columns storage column => new value
     */
    public function addUpdate(string $key, array $columns): void
    {
        if ($columns === []) {
            return;
        }

        $signature = implode(',', array_keys($columns));

        $this->updates[$signature][$key] = ['key' => $key, 'columns' => $columns];
    }

    /**
     * @param array<string, string|int|float> $conditions additional equality conditions applied to every batched row
     */
    public function execute(array $conditions = []): void
    {
        if ($this->updates === []) {
            return;
        }

        $queries = $this->prepareQueries($conditions);

        RetryableTransaction::retryable($this->connection, function () use ($queries): void {
            foreach ($queries as $query) {
                $this->connection->executeStatement($query['query'], $query['values']);
            }
        });

        $this->updates = [];
    }

    /**
     * @param array<string, string|int|float> $conditions
     *
     * @return list<array{query: string, values: list<string|int|float>}>
     */
    private function prepareQueries(array $conditions): array
    {
        $table = EntityDefinitionQueryHelper::escape($this->table);
        $key = EntityDefinitionQueryHelper::escape($this->keyColumn);

        $queries = [];

        foreach ($this->updates as $rows) {
            foreach (array_chunk(array_values($rows), $this->chunkSize) as $chunk) {
                $values = [];

                $sets = [];
                foreach (array_keys($chunk[0]['columns']) as $column) {
                    $cases = [];
                    foreach ($chunk as $row) {
                        if ($row['columns'][$column] === null) {
                            $cases[] = 'WHEN ? THEN NULL';
                            $values[] = $row['key'];

                            continue;
                        }

                        $cases[] = 'WHEN ? THEN ?';
                        $values[] = $row['key'];
                        $values[] = $row['columns'][$column];
                    }

                    $escapedColumn = EntityDefinitionQueryHelper::escape($column);
                    // the ELSE fallback keeps rows untouched that match the WHERE clause but no CASE key
                    $sets[] = $escapedColumn . ' = CASE ' . $key . ' ' . implode(' ', $cases) . ' ELSE ' . $escapedColumn . ' END';
                }

                $wheres = [$key . ' IN (' . implode(',', array_fill(0, \count($chunk), '?')) . ')'];
                foreach ($chunk as $row) {
                    $values[] = $row['key'];
                }

                foreach ($conditions as $column => $value) {
                    $wheres[] = EntityDefinitionQueryHelper::escape($column) . ' = ?';
                    $values[] = $value;
                }

                $queries[] = [
                    'query' => \sprintf('UPDATE %s SET %s WHERE %s;', $table, implode(', ', $sets), implode(' AND ', $wheres)),
                    'values' => $values,
                ];
            }
        }

        return $queries;
    }
}
