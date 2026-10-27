<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\RowLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Log\Package;

/**
 * @final
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
     * with its inserts and updates merged in order. A record whose last write is a delete is left out.
     *
     * @param list<WriteCommand> $commands the writes of one entity, in the order they run, e.g. from PreWriteValidationEvent::getCommandsForEntity()
     * @param non-empty-list<string> $columns the columns to read, from the write or else from the stored row
     *
     * @return list<OutcomeRow>
     */
    public function rows(array $commands, array $columns): array
    {
        if ($columns === []) {
            throw DataAbstractionLayerException::writeOutcomeWithoutColumns();
        }

        $primaryKeys = $this->updatedPrimaryKeys($commands, $columns);
        $storedRows = $this->loadStoredRows($commands, $primaryKeys, $columns);

        $rows = [];
        foreach ($this->commandsPerRecord($commands, $columns) as $hash => $recordCommands) {
            $rows[] = $this->toOutcomeRow($recordCommands, $storedRows[$hash] ?? [], $columns);
        }

        return $rows;
    }

    /**
     * @param list<WriteCommand> $commands
     * @param list<string> $columns
     *
     * @return list<array<string, string>>
     */
    private function updatedPrimaryKeys(array $commands, array $columns): array
    {
        $primaryKeys = [];
        foreach ($commands as $command) {
            if ($command instanceof UpdateCommand && $command->hasAnyField(...$columns)) {
                $primaryKeys[self::hashPrimaryKey($command->getPrimaryKey())] = $command->getPrimaryKey();
            }
        }

        return array_values($primaryKeys);
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
            $storedRows[self::hashPrimaryKey(self::only($row, $primaryKeyColumns))] = $row;
        }

        return $storedRows;
    }

    /**
     * @param list<WriteCommand> $commands
     * @param list<string> $columns
     *
     * @return array<string, non-empty-list<InsertCommand|UpdateCommand>> keyed by primary key hash
     */
    private function commandsPerRecord(array $commands, array $columns): array
    {
        $records = [];
        foreach ($commands as $command) {
            $hash = self::hashPrimaryKey($command->getPrimaryKey());

            if ($command instanceof DeleteCommand) {
                unset($records[$hash]);
            } elseif ($command instanceof InsertCommand) {
                $records[$hash] = [$command];
            } elseif ($command instanceof UpdateCommand && $command->hasAnyField(...$columns)) {
                $records[$hash][] = $command;
            }
        }

        return $records;
    }

    /**
     * @param non-empty-list<InsertCommand|UpdateCommand> $recordCommands
     * @param array<string, mixed> $storedRow
     * @param list<string> $columns
     */
    private function toOutcomeRow(array $recordCommands, array $storedRow, array $columns): OutcomeRow
    {
        $first = $recordCommands[0];
        $isNew = $first instanceof InsertCommand;

        $state = array_fill_keys($columns, null);
        if (!$isNew) {
            $state = [...$state, ...$storedRow];
        }

        return new OutcomeRow(
            primaryKey: $first->getPrimaryKey(),
            decodedPrimaryKey: $first->getDecodedPrimaryKey(),
            path: $first->getPath(),
            isNew: $isNew,
            payload: self::mergedPayload($recordCommands),
            state: $state,
        );
    }

    /**
     * @param non-empty-list<InsertCommand|UpdateCommand> $commands
     *
     * @return array<string, mixed>
     */
    private static function mergedPayload(array $commands): array
    {
        $payload = [];
        foreach ($commands as $command) {
            $payload = array_replace($payload, $command->getPayload());
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $primaryKey
     */
    private static function hashPrimaryKey(array $primaryKey): string
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
    private static function only(array $row, array $columns): array
    {
        return array_intersect_key($row, array_flip($columns));
    }
}
