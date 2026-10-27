<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\Validation;

use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\Log\Package;

/**
 * A record's row as it will be after the write: the payloads of its inserts and updates merged in order, on top of
 * its state in the database. Values are in storage format, keyed by column name.
 */
#[Package('framework')]
final readonly class OutcomeRow
{
    /**
     * @internal
     *
     * @param array<string, string> $primaryKey in storage format
     * @param array<string, string> $decodedPrimaryKey
     * @param array<string, mixed> $payload the merged payloads of the write
     * @param array<string, mixed> $state the loaded columns of the row in the database, all null for a new record
     */
    public function __construct(
        public array $primaryKey,
        public array $decodedPrimaryKey,
        public string $path,
        public bool $isNew,
        private array $payload,
        private array $state,
    ) {
    }

    public function isInPayload(string $column): bool
    {
        return \array_key_exists($column, $this->payload);
    }

    /**
     * The value from the payload, else from the state.
     */
    public function getResultingValue(string $column): mixed
    {
        if ($this->isInPayload($column)) {
            return $this->payload[$column];
        }

        if (!\array_key_exists($column, $this->state)) {
            throw DataAbstractionLayerException::outcomeRowColumnNotLoaded($column);
        }

        return $this->state[$column];
    }
}
