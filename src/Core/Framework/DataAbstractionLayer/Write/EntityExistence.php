<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write;

/**
 * Defines the current state of an entity in relation to the parent-child inheritance and
 * existence in the storage or command queue.
 */
class EntityExistence
{
    /**
     * @var array
     */
    private $primaryKey;

    /**
     * @var bool
     */
    private $exists;

    /**
     * @var bool
     */
    private $isChild;

    /**
     * @var bool
     */
    private $wasChild;

    /**
     * @var array
     */
    private $state;

    /**
     * @var string|null
     */
    private $entityName;

    public function __construct(
        ?string $entityName = null, // @see a hack in \Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer
        array $primaryKey,
        bool $exists,
        bool $isChild,
        bool $wasChild,
        array $state
    ) {
        $this->entityName = $entityName;
        $this->primaryKey = $primaryKey;
        $this->exists = $exists;
        $this->isChild = $isChild;
        $this->wasChild = $wasChild;
        $this->state = $state;
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
