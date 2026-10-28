<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;

/**
 * A record's row as it will be after the write: the payloads of its inserts and updates merged in order, on top of
 * its state in the database. Values are in storage format, keyed by column name, e.g. `customer.email` is `email`
 * and ids are 16 binary bytes. Only the columns passed to WriteOutcome::rows() can be read.
 */
#[Package('framework')]
final readonly class OutcomeRow
{
    /**
     * @internal
     *
     * @param array<string, string> $primaryKey the record's primary key, keyed by column name, e.g. `['id' => <bytes>, 'version_id' => <bytes>]`
     * @param string $path where the record's last write to a requested column sits in the request, e.g. `/1/0` for the first row of the second Sync operation; use it as the error path
     * @param bool $isNew whether the write creates the record
     * @param array<string, mixed> $payload the merged payloads of the write
     * @param array<string, mixed> $state the requested columns of the row in the database, all null for a new record
     */
    public function __construct(
        public array $primaryKey,
        public string $path,
        public bool $isNew,
        private array $payload,
        private array $state,
    ) {
    }

    /**
     * Whether the write sets the column. Throws for a column that was not requested.
     */
    public function isInPayload(string $column): bool
    {
        $this->ensureRequested($column);

        return \array_key_exists($column, $this->payload);
    }

    /**
     * The column's value after the write: from the payload if the write sets it, else from the database, null for a
     * new record. Throws for a column that was not requested.
     */
    public function getResultingValue(string $column): mixed
    {
        if ($this->isInPayload($column)) {
            return $this->payload[$column];
        }

        return $this->state[$column];
    }

    private function ensureRequested(string $column): void
    {
        if (!\array_key_exists($column, $this->state)) {
            throw DataAbstractionLayerException::outcomeRowColumnNotRequested($column);
        }
    }
}
