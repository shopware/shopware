<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;

/**
 * A record's row before and after the write. After the write, it is the payloads of its inserts and updates merged in
 * order, on top of its state in the database. Values are in storage format, keyed by column name, e.g. `customer.email`
 * is `email` and ids are 16 binary bytes. Only the columns passed to WriteOutcome can be read.
 */
#[Package('framework')]
final readonly class OutcomeRow
{
    /**
     * @internal
     *
     * @param array<string, string> $primaryKey the record's primary key by column name, e.g. `['id' => <bytes>, 'version_id' => <bytes>]`
     * @param string $path where to report an error for the record, e.g. `/1/0` for the first record of the second Sync operation
     * @param bool $isNew whether the request creates the record
     * @param bool $isDeleted whether the request deletes the stored record
     * @param array<string, mixed> $payload the columns the request sets for the record, merged in order. Writes before a delete are left out.
     * @param array<string, mixed> $state the requested columns as stored before the request. All null when the record is not stored.
     */
    public function __construct(
        public array $primaryKey,
        public string $path,
        public bool $isNew,
        public bool $isDeleted,
        private array $payload,
        private array $state,
    ) {
    }

    /**
     * Whether the write sets any of the columns. Works for any column, requested or not.
     */
    public function payloadContainsAny(string ...$columns): bool
    {
        return array_intersect($columns, array_keys($this->payload)) !== [];
    }

    /**
     * Whether the write sets no other columns than these, e.g. only switches a record on or off. False for a record
     * the write deletes. Works for any column, requested or not.
     */
    public function payloadContainsOnly(string ...$columns): bool
    {
        return !$this->isDeleted && array_diff(array_keys($this->payload), $columns) === [];
    }

    /**
     * The value the write sets for the column, null if the write does not set it. Throws for a column that was not
     * requested.
     */
    public function getPayloadValue(string $column): mixed
    {
        $this->ensureRequested($column);

        return $this->payload[$column] ?? null;
    }

    /**
     * The column's value after the write: from the payload if the write sets it, else from the database. Null for a
     * column a new record leaves out, and for a record the write deletes. Throws for a column that was not requested.
     */
    public function getResultingValue(string $column): mixed
    {
        $this->ensureRequested($column);

        if (\array_key_exists($column, $this->payload)) {
            return $this->payload[$column];
        }

        if ($this->isDeleted) {
            return null;
        }

        return $this->state[$column];
    }

    /**
     * The column's value in the database before the write, null for a record that is not stored. Throws for a column
     * that was not requested.
     */
    public function getStoredValue(string $column): mixed
    {
        $this->ensureRequested($column);

        return $this->state[$column];
    }

    /**
     * The record's primary key and the value of each requested column after the write, keyed by column name.
     *
     * @return array<string, mixed>
     */
    public function toResultingArray(): array
    {
        $row = $this->primaryKey;
        foreach (array_keys($this->state) as $column) {
            $row[$column] = $this->getResultingValue($column);
        }

        return $row;
    }

    /**
     * The record's primary key and the value of each requested column in the database before the write, keyed by
     * column name.
     *
     * @return array<string, mixed>
     */
    public function toStoredArray(): array
    {
        return [...$this->primaryKey, ...$this->state];
    }

    private function ensureRequested(string $column): void
    {
        if (!\array_key_exists($column, $this->state)) {
            throw DataAbstractionLayerException::outcomeRowColumnNotRequested($column);
        }
    }
}
