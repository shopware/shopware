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
    /**
     * @param array<string, mixed> $primaryKey
     * @param array<string, mixed> $state
     */
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

    /**
     * @internal
     *
     * @param array<string, mixed> $primaryKey
     */
    public static function createForEntity(?string $entity, array $primaryKey): self
    {
        return new self($entity, $primaryKey, false, false, false, []);
    }

    /**
     * @internal
     */
    public static function createEmpty(): self
    {
        return new self(null, [], false, false, false, []);
    }

    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * @return array<string, mixed>
     */
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

    /**
     * @return array<string, mixed>
     */
    public function getState(): array
    {
        return $this->state;
    }
}
