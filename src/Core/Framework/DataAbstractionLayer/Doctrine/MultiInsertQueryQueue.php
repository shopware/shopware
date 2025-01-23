<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class MultiInsertQueryQueue
{
    /**
     * @var array<string, list<array{data: array<string, mixed>, columns: list<string>}>>
     */
    private array $inserts = [];

    /**
     * @var array<string, list<string>>
     */
    private array $updateFieldsOnDuplicateKey = [];

    /**
     * @var int<1, max>
     */
    private readonly int $chunkSize;

    public function __construct(
        private readonly Connection $connection,
        int $chunkSize = 250,
        private readonly bool $ignoreErrors = false,
        private readonly bool $useReplace = false
    ) {
        if ($chunkSize < 1) {
            throw DataAbstractionLayerException::invalidChunkSize($chunkSize);
        }
        $this->chunkSize = $chunkSize;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, ParameterType::*>|null $types
     */
    public function addInsert(string $table, array $data, ?array $types = null): void
    {
        $this->inserts[$table][] = [
            'data' => $data,
            'types' => $types,
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param array<string, ParameterType::*>|null $types
     */
    public function addInserts(string $table, array $rows, ?array $types = null): void
    {
        foreach ($rows as $row) {
            $this->addInsert($table, $row, $types);
        }
    }

    public function execute(): void
    {
        if (empty($this->inserts)) {
            return;
        }

        $grouped = $this->prepare();
        RetryableTransaction::retryable($this->connection, function () use ($grouped): void {
            foreach ($grouped as $query) {
                $this->connection->executeStatement($query['query'], $query['values'], $query['types']);
            }
        });
        unset($grouped);

        $this->inserts = [];
    }

    /**
     * You can add fields which should be updated with the new values on duplicate keys
     */
    public function addUpdateFieldOnDuplicateKey(string $table, string $updateField): void
    {
        $this->updateFieldsOnDuplicateKey[$table][] = $updateField;
    }

    /**
     * @return array<array{query: string, values: list<string>, types: array<string, ParameterType::*>}>
     */
    private function prepare(): array
    {
        $queries = [];
        $template = 'INSERT INTO %s (%s) VALUES %s';

        if ($this->ignoreErrors) {
            $template = 'INSERT IGNORE INTO %s (%s) VALUES %s';
        }

        if ($this->useReplace) {
            $template = 'REPLACE INTO %s (%s) VALUES %s';
        }

        foreach ($this->inserts as $table => $rows) {
            $tableTemplate =
                $template
                . $this->prepareOnDuplicateKeyUpdatePart($this->updateFieldsOnDuplicateKey[$table] ?? [])
                . ';';

            $columns = $this->prepareColumns($rows);

            $rowsChunks = array_chunk($rows, $this->chunkSize);
            foreach ($rowsChunks as $rowsChunk) {
                $data = $this->prepareValues($columns, $rowsChunk);
                $queries[] = [
                    'query' => \sprintf(
                        $tableTemplate,
                        EntityDefinitionQueryHelper::escape($table),
                        implode(', ', array_map(EntityDefinitionQueryHelper::escape(...), $columns)),
                        implode(', ', $data['placeholders']),
                    ),
                    'values' => $data['values'],
                    'types' => $data['types'],
                ];
            }
        }

        return $queries;
    }

    private function prepareOnDuplicateKeyUpdatePart(array $fieldsToUpdate): string {
        if (count($fieldsToUpdate) === 0) {
            return '';
        }

        foreach ($fieldsToUpdate as $field) {
            // see https://stackoverflow.com/a/2714653/10064036
            $updateParts[] = \sprintf('%s = VALUES(%s)', EntityDefinitionQueryHelper::escape($field), EntityDefinitionQueryHelper::escape($field));
        }

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updateParts);
    }

    /**
     * @param list<array{columns: list<string>}> $rows
     *
     * @return list<string>
     */
    private function prepareColumns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach (array_keys($row['data']) as $column) {
                $columns[$column] = 1;
            }
        }

        return array_keys($columns);
    }

    /**
     * @param list<string> $columns
     * @param list<array{data: array<string, mixed>, columns: list<string>}> $rows
     *
     * @return array{placeholders: list<string>, values: list<mixed>, types: array<string, ParameterType::*>}
     */
    private function prepareValues(array $columns, array $rows): array
    {
        $placeholders = [];
        $values = [];
        $types = [];

        foreach ($rows as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $column) {
                if (!\array_key_exists($column, $row['data'])) { // to use default values if the column is not set
                    $rowPlaceholders[] = 'DEFAULT';
                    continue;
                }
                if ($row['data'][$column] === null) { // to insert nulls if the value is null
                    $rowPlaceholders[] = 'NULL';
                    continue;
                }

                $rowPlaceholders[] = '?';
                $values[] = $row['data'][$column];
                $types[] = $row['types'][$column] ?? ParameterType::STRING;
            }
            $placeholders[] = '(' . implode(',', $rowPlaceholders) . ')';
        }

        return [
            'placeholders' => $placeholders,
            'values' => $values,
            'types' => $types,
        ];
    }
}
