<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

use Shopware\Core\Framework\Log\Package;

/**
 * Defines the current state of an entity in relation to the parent-child inheritance and
 * existence in the storage or command queue.
 */
#[Package('core')]
class EntityExistence
{
    public function __construct(
        private readonly ?string $entityName,
        // @see a hack in \Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer
        private readonly array $primaryKey,
        private readonly bool $exists,
        private readonly bool $isChild,
        private readonly bool $wasChild,
        private readonly array $state
    ) {
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }

    public function isChild(): bool
    {
        return $this->isChild;
    }

    public function wasChild(): bool
    {
        return $this->wasChild;
    }

    public function hasEntityName(): bool
    {
        return $this->entityName !== null;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }

    public function childChangedToParent(): bool
    {
        if (!$this->wasChild()) {
            return false;
        }

        return !$this->isChild();
    }

    public function getState(): array
    {
        return $this->state;
    }
}
