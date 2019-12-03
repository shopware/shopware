<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

class MultiInsertQueryQueue
{
    /**
     * @var array[]
     */
    private $inserts = [];

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var int
     */
    private $chunkSize;

    /**
     * @var bool
     */
    private $ignoreErrors;

    /**
     * @var bool
     */
    private $useReplace;

    public function __construct(
        Connection $connection,
        int $chunkSize = 250,
        bool $ignoreErrors = false,
        bool $useReplace = false
    ) {
        $this->connection = $connection;
        $this->chunkSize = $chunkSize;
        $this->ignoreErrors = $ignoreErrors;
        $this->useReplace = $useReplace;
    }

    public function addInsert(string $table, array $data, ?array $types = null, bool $quoted = false): void
    {
        $columns = [];

        foreach ($data as $key => &$value) {
            $columns[] = $key;

            if ($quoted) {
                continue;
            }

            $type = ParameterType::STRING;

            if ($types !== null && isset($types[$key])) {
                $type = $types[$key];
            }

            $value = $this->connection->quote($value, $type);
        }

        $this->inserts[$table][] = [
            'data' => $data,
            'columns' => $columns,
            'types' => $types,
        ];
    }

    public function execute(): void
    {
        $grouped = $this->prepare();

        foreach ($grouped as $query) {
            $this->connection->executeUpdate($query);
        }
        unset($grouped);

        $this->inserts = [];
    }

    private function prepare(): array
    {
        $queries = [];
        $template = 'INSERT INTO %s (%s) VALUES %s;';
        if ($this->ignoreErrors) {
            $template = 'INSERT IGNORE INTO %s (%s) VALUES %s;';
        }

        if ($this->useReplace) {
            $template = 'REPLACE INTO %s (%s) VALUES %s;';
        }

        foreach ($this->inserts as $table => $rows) {
            $columns = $this->prepareColumns($rows);
            $data = $this->prepareValues($columns, $rows);

            $chunks = array_chunk($data, $this->chunkSize);
            foreach ($chunks as $chunk) {
                $queries[] = sprintf(
                    $template,
                    $table,
                    implode(', ', $columns),
                    implode(', ', $chunk)
                );
            }
        }

        return $queries;
    }

    private function prepareColumns(array $rows): array
    {
        $columns = [];
        foreach ($rows as $row) {
            foreach ($row['columns'] as $column) {
                $columns[$column] = 1;
            }
        }

        return array_keys($columns);
    }

    private function prepareValues(array $columns, array $rows): array
    {
        $stackedValues = [];
        $defaults = array_combine(
            $columns,
            array_fill(0, \count($columns), 'DEFAULT')
        );
        foreach ($rows as $row) {
            $data = $row['data'];
            $values = $defaults;
            foreach ($data as $key => $value) {
                $values[$key] = $value;
            }
            $stackedValues[] = '(' . implode(',', $values) . ')';
        }

        return $stackedValues;
    }
}
