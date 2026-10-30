<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\JsonUpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;

/**
 * @final
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Write\Validation\WriteOutcomeTest
 */
#[Package('framework')]
class WriteOutcome
{
    /**
     * @internal
     */
    public function __construct(private readonly RowLoader $rowLoader)
    {
    }

    /**
     * Each record that is new or written in one of the columns, as it will be after the write: one per primary key,
     * with its inserts and updates merged in order. A record whose last write is a delete is left out, and a record
     * the write deletes and creates again is new, without its stored row.
     *
     * @param list<WriteCommand> $commands the writes of one entity, in the order they run, e.g. from PreWriteValidationEvent::getCommandsForEntity()
     * @param non-empty-list<string> $columns the columns to read, from the write or else from the stored row
     *
     * @return list<OutcomeRow>
     */
    public function resultingRows(array $commands, array $columns): array
    {
        $this->assertColumnsNotEmpty($columns);

        $records = $this->commandsPerRecord($commands, $columns);
        $primaryKeys = $this->updatedPrimaryKeys($records);
        $storedRows = $this->loadStoredRows($commands, $primaryKeys, $columns);

        $rows = [];
        foreach ($records as $hash => $recordCommands) {
            $rows[] = $this->toOutcomeRow($recordCommands, $storedRows[$hash] ?? [], $columns);
        }

        return $rows;
    }

    /**
     * Each record the write inserts, updates or deletes, whatever columns it sets: one per primary key, with its row in
     * the database before the write and its values after the write.
     *
     * @param list<WriteCommand> $commands the writes of one entity, in the order they run, e.g. from PreWriteValidationEvent::getCommandsForEntity()
     * @param non-empty-list<string> $columns the columns to read
     *
     * @return list<OutcomeRow>
     */
    public function touchedRows(array $commands, array $columns): array
    {
        $this->assertColumnsNotEmpty($columns);

        $records = $this->touchedRecords($commands);
        $primaryKeys = $this->storedPrimaryKeys($records);
        $storedRows = $this->loadStoredRows($commands, $primaryKeys, $columns);

        $rows = [];
        foreach ($records as $hash => $recordCommands) {
            $rows[] = $this->toTouchedRow($recordCommands, $storedRows[$hash] ?? [], $columns);
        }

        return $rows;
    }

    /**
     * @param list<WriteCommand> $commands
     *
     * @return array<string, non-empty-list<WriteCommand>> every write of each record, in order, keyed by primary key hash
     */
    private function touchedRecords(array $commands): array
    {
        $records = [];
        foreach ($commands as $command) {
            $records[$this->hashPrimaryKey($command->getPrimaryKey())][] = $command;
        }

        return $records;
    }

    /**
     * @param array<string, non-empty-list<WriteCommand>> $records
     *
     * @return list<array<string, string>> the primary keys of the records that exist before the write
     */
    private function storedPrimaryKeys(array $records): array
    {
        $primaryKeys = [];
        foreach ($records as $recordCommands) {
            if (!$recordCommands[0] instanceof InsertCommand) {
                $primaryKeys[] = $recordCommands[0]->getPrimaryKey();
            }
        }

        return $primaryKeys;
    }

    /**
     * @param non-empty-list<WriteCommand> $recordCommands
     * @param array<string, mixed> $storedRow
     * @param list<string> $columns
     */
    private function toTouchedRow(array $recordCommands, array $storedRow, array $columns): OutcomeRow
    {
        $isStored = !$recordCommands[0] instanceof InsertCommand;
        $last = $recordCommands[array_key_last($recordCommands)];

        $writes = [];
        $isDeleted = false;
        foreach ($recordCommands as $command) {
            if ($command instanceof DeleteCommand) {
                $writes = [];
                $isDeleted = $isStored;
            } elseif ($command instanceof InsertCommand || $command instanceof UpdateCommand) {
                $writes[] = $command;
            }
        }

        $isNew = ($writes[0] ?? null) instanceof InsertCommand;

        return new OutcomeRow(
            primaryKey: $last->getPrimaryKey(),
            path: $last->getPath(),
            isNew: $isNew,
            isDeleted: $isDeleted,
            payload: $this->mergedPayload($writes, $isNew ? [] : $storedRow),
            state: [...array_fill_keys($columns, null), ...$this->only($storedRow, $columns)],
        );
    }

    /**
     * @param array<string, non-empty-list<InsertCommand|UpdateCommand>> $records
     *
     * @return list<array<string, string>> the primary keys of the records that already exist
     */
    private function updatedPrimaryKeys(array $records): array
    {
        $primaryKeys = [];
        foreach ($records as $recordCommands) {
            if ($recordCommands[0] instanceof UpdateCommand) {
                $primaryKeys[] = $recordCommands[0]->getPrimaryKey();
            }
        }

        return $primaryKeys;
    }

    /**
     * @param list<WriteCommand> $commands
     * @param list<array<string, string>> $primaryKeys
     * @param list<string> $columns
     *
     * @return array<string, array<string, mixed>> keyed by primary key hash
     */
    private function loadStoredRows(array $commands, array $primaryKeys, array $columns): array
    {
        if ($primaryKeys === []) {
            return [];
        }

        $entity = $commands[0]->getEntityName();
        $primaryKeyColumns = array_keys($primaryKeys[0]);

        $storedRows = [];
        foreach ($this->rowLoader->load($entity, $primaryKeys, [...$primaryKeyColumns, ...$columns]) as $row) {
            $storedRows[$this->hashPrimaryKey($this->only($row, $primaryKeyColumns))] = $row;
        }

        return $storedRows;
    }

    /**
     * @param list<WriteCommand> $commands
     * @param list<string> $columns
     *
     * @return array<string, non-empty-list<InsertCommand|UpdateCommand>> the writes after each record's last delete, for the records that are new or written in one of the columns, keyed by primary key hash
     */
    private function commandsPerRecord(array $commands, array $columns): array
    {
        $records = [];
        foreach ($commands as $command) {
            $hash = $this->hashPrimaryKey($command->getPrimaryKey());

            if ($command instanceof DeleteCommand) {
                unset($records[$hash]);
            } elseif ($command instanceof InsertCommand) {
                $records[$hash] = [$command];
            } elseif ($command instanceof UpdateCommand) {
                $records[$hash][] = $command;
            }
        }

        return array_filter($records, fn (array $recordCommands): bool => $this->lastWriteToColumns($recordCommands, $columns) !== null);
    }

    /**
     * @param non-empty-list<InsertCommand|UpdateCommand> $recordCommands
     * @param list<string> $columns
     */
    private function lastWriteToColumns(array $recordCommands, array $columns): InsertCommand|UpdateCommand|null
    {
        $lastWrite = null;
        foreach ($recordCommands as $command) {
            if ($command instanceof InsertCommand || $this->setsAnyColumn($command, $columns)) {
                $lastWrite = $command;
            }
        }

        return $lastWrite;
    }

    /**
     * @param non-empty-list<InsertCommand|UpdateCommand> $recordCommands
     * @param array<string, mixed> $storedRow
     * @param list<string> $columns
     */
    private function toOutcomeRow(array $recordCommands, array $storedRow, array $columns): OutcomeRow
    {
        $first = $recordCommands[0];
        $last = $this->lastWriteToColumns($recordCommands, $columns) ?? $recordCommands[array_key_last($recordCommands)];
        $isNew = $first instanceof InsertCommand;

        $state = array_fill_keys($columns, null);
        if (!$isNew) {
            $state = [...$state, ...$this->only($storedRow, $columns)];
        }

        return new OutcomeRow(
            primaryKey: $first->getPrimaryKey(),
            path: $last->getPath(),
            isNew: $isNew,
            isDeleted: false,
            payload: $this->mergedPayload($recordCommands, $storedRow),
            state: $state,
        );
    }

    /**
     * @param list<string> $columns
     */
    private function setsAnyColumn(UpdateCommand $command, array $columns): bool
    {
        if ($command instanceof JsonUpdateCommand) {
            return \in_array($command->getStorageName(), $columns, true);
        }

        return $command->hasAnyField(...$columns);
    }

    /**
     * @param list<InsertCommand|UpdateCommand> $commands
     * @param array<string, mixed> $storedRow
     *
     * @return array<string, mixed>
     */
    private function mergedPayload(array $commands, array $storedRow): array
    {
        $payload = [];
        foreach ($commands as $command) {
            if ($command instanceof JsonUpdateCommand) {
                $column = $command->getStorageName();
                $payload[$column] = $this->patchJson($payload[$column] ?? $storedRow[$column] ?? null, $command->getPayload());

                continue;
            }

            $payload = array_replace($payload, $command->getPayload());
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $patch
     */
    private function patchJson(mixed $json, array $patch): string
    {
        $values = \is_string($json) ? Json::decodeToArray($json) : [];

        return Json::encode(array_replace($values, $patch));
    }

    /**
     * @param array<string, mixed> $primaryKey
     */
    private function hashPrimaryKey(array $primaryKey): string
    {
        ksort($primaryKey);

        return serialize($primaryKey);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $columns
     *
     * @return array<string, mixed>
     */
    private function only(array $row, array $columns): array
    {
        return array_intersect_key($row, array_flip($columns));
    }

    /**
     * @param list<string> $columns
     */
    private function assertColumnsNotEmpty(array $columns): void
    {
        if ($columns === []) {
            throw DataAbstractionLayerException::writeOutcomeWithoutColumns();
        }
    }
}
