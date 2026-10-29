<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * The new id of every nested row a clone copies, by entity name and source id.
 *
 * @internal
 */
#[Package('framework')]
final class CloneIdMap
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $ids = [];

    /**
     * Returns the clone's id for a source row, generating it on the first call.
     */
    public function cloneIdFor(string $entityName, string $sourceId): string
    {
        return $this->ids[$entityName][$sourceId] ??= Uuid::randomHex();
    }

    /**
     * Returns the clone's id for a source row, or null when the row was not cloned.
     */
    public function get(string $entityName, string $sourceId): ?string
    {
        return $this->ids[$entityName][$sourceId] ?? null;
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }
}
